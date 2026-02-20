<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\ReservationConfig;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing reservation reminder scheduling.
 * Handles scheduling and cancelling reminders when reservations are created/updated.
 */
class ReservationReminderService
{
    protected QstashSchedulerService $scheduler;

    protected TemplateParameterService $parameterService;

    public function __construct(
        QstashSchedulerService $scheduler,
        TemplateParameterService $parameterService
    ) {
        $this->scheduler = $scheduler;
        $this->parameterService = $parameterService;
    }

    /**
     * Schedule reminders for a reservation.
     * Called when a new reservation is created or when reminder config is updated.
     */
    public function scheduleReminders(Reservation $reservation): array
    {
        // Get the config for this reservation
        $config = ReservationConfig::where('user_id', $reservation->user_id)
            ->where('store_id', $reservation->store_id)
            ->first();

        if (! $config || ! $config->isReminderConfigured()) {
            Log::info('Reminder not configured, skipping scheduling', [
                'reservation_id' => $reservation->id,
            ]);

            return [];
        }

        // Get the reservation datetime
        $reservationDateTime = $reservation->date.' '.$reservation->time;

        // Check if reservation is in the past
        if (\Carbon\Carbon::parse($reservationDateTime)->isPast()) {
            Log::info('Reservation is in the past, skipping reminder scheduling', [
                'reservation_id' => $reservation->id,
                'reservation_datetime' => $reservationDateTime,
            ]);

            return [];
        }

        // Get the timings (hours before reservation)
        $timings = $config->reminder_timing ?? [];

        if (empty($timings)) {
            Log::info('No reminder timings configured', [
                'reservation_id' => $reservation->id,
            ]);

            return [];
        }

        // Schedule multiple reminders
        $scheduledIds = $this->scheduler->scheduleMultiple(
            $reservation->id,
            $reservationDateTime,
            $timings
        );

        Log::info('Reminders scheduled for reservation', [
            'reservation_id' => $reservation->id,
            'scheduled_count' => count($scheduledIds),
            'schedule_ids' => $scheduledIds,
        ]);

        return $scheduledIds;
    }

    /**
     * Cancel all scheduled reminders for a reservation.
     * Called when a reservation is cancelled.
     */
    public function cancelReminders(Reservation $reservation): int
    {
        // Get stored schedule IDs from reservation if available
        $scheduleIds = $reservation->scheduled_reminder_jobs ?? [];

        if (empty($scheduleIds)) {
            Log::info('No scheduled reminders to cancel', [
                'reservation_id' => $reservation->id,
            ]);

            return 0;
        }

        // Cancel all scheduled jobs
        $cancelled = $this->scheduler->cancelMultiple($scheduleIds);

        Log::info('Cancelled scheduled reminders', [
            'reservation_id' => $reservation->id,
            'cancelled_count' => $cancelled,
        ]);

        return $cancelled;
    }

    /**
     * Reschedule reminders for a reservation.
     * Called when a reservation is updated (e.g., date/time changed).
     */
    public function rescheduleReminders(Reservation $reservation): array
    {
        // Cancel existing reminders first
        $this->cancelReminders($reservation);

        // Schedule new reminders
        return $this->scheduleReminders($reservation);
    }

    /**
     * Validate that the reminder configuration is valid.
     * Returns validation errors if any.
     */
    public function validateConfig(ReservationConfig $config, string $templateName): array
    {
        $errors = [];

        if (! $config->reminder_enabled) {
            return $errors; // No validation needed if disabled
        }

        if (empty($templateName)) {
            $errors[] = 'Template name is required when reminder is enabled';

            return $errors;
        }

        if (empty($config->reminder_timing)) {
            $errors[] = 'At least one reminder timing is required';
        }

        if (empty($config->reminder_param_mapping)) {
            $errors[] = 'Parameter mapping is required';
        }

        return $errors;
    }
}
