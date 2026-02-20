<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for scheduling jobs using Qstash (Upstash).
 *
 * Qstash is an HTTP-based messaging and scheduling solution for serverless.
 * It allows scheduling jobs to be executed at a specific time in the future.
 */
class QstashSchedulerService
{
    protected string $token;

    protected string $baseUrl;

    protected string $callbackUrl;

    public function __construct()
    {
        $this->token = config('qstash.token', env('QSTASH_TOKEN', ''));
        // Qstash server URL - can be local or cloud
        $this->baseUrl = config('qstash.api_url', env('QSTASH_API_URL', 'http://127.0.0.1:8080/v2'));
        // The callback URL is the endpoint that will handle the job
        $this->callbackUrl = config('app.url').'/api/internal/reservation-reminder';
    }

    /**
     * Schedule a reminder job to be sent at a specific time.
     *
     * @param  int  $reservationId  ID of the reservation
     * @param  string  $sendAt  When to send the reminder (Carbon or string that can be parsed)
     * @param  array  $customPayload  Additional payload for the job
     * @return string|null The scheduled job ID from Qstash
     */
    public function schedule(int $reservationId, string $sendAt, array $customPayload = []): ?string
    {
        if (empty($this->token)) {
            Log::warning('Qstash token not configured. Reminder will not be scheduled.');

            return null;
        }

        $payload = array_merge([
            'reservation_id' => $reservationId,
            'type' => 'reservation_reminder',
        ], $customPayload);

        try {
            // Convert to Unix timestamp if it's a date string
            $timestamp = is_string($sendAt) ? strtotime($sendAt) : $sendAt;

            // Format: POST /v2/publish/{callback_url} with schedule_at header
            // Or use /v2/schedule/{callback_url} for scheduled jobs
            $response = Http::withToken($this->token)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/publish/".$this->callbackUrl, [
                    'body' => json_encode($payload),
                    'schedule_at' => $timestamp,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                // Response format: { "taskId": "..." } or { "scheduleId": "..." }
                $scheduleId = $data['taskId'] ?? $data['scheduleId'] ?? null;

                Log::info('Reminder scheduled via Qstash', [
                    'reservation_id' => $reservationId,
                    'schedule_id' => $scheduleId,
                    'send_at' => $sendAt,
                ]);

                return $scheduleId;
            }

            Log::error('Failed to schedule reminder via Qstash', [
                'reservation_id' => $reservationId,
                'response' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Exception scheduling reminder via Qstash', [
                'reservation_id' => $reservationId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Schedule multiple reminders for a reservation.
     *
     * @param  int  $reservationId  ID of the reservation
     * @param  string  $reservationDateTime  When the reservation is (Carbon or string)
     * @param  array  $timings  Array of minutes before reservation to send reminders (e.g., [60, 1440])
     * @param  array  $customPayload  Additional payload for the job
     * @return array Array of scheduled job IDs
     */
    public function scheduleMultiple(int $reservationId, string $reservationDateTime, array $timings, array $customPayload = []): array
    {
        $scheduledIds = [];

        foreach ($timings as $minutes) {
            // Calculate the send time (timings are in minutes before reservation)
            $sendAt = \Carbon\Carbon::parse($reservationDateTime)
                ->subMinutes($minutes)
                ->toIso8601String();

            // Don't schedule if the time is in the past
            if (\Carbon\Carbon::parse($sendAt)->isPast()) {
                Log::info('Skipping reminder - time is in the past', [
                    'reservation_id' => $reservationId,
                    'minutes_before' => $minutes,
                    'send_at' => $sendAt,
                ]);

                continue;
            }

            // Check if more than 7 days in advance (Qstash limit)
            $daysAhead = \Carbon\Carbon::parse($sendAt)->diffInDays(now());
            if ($daysAhead > 7) {
                Log::info('Skipping reminder - more than 7 days ahead (Qstash limit)', [
                    'reservation_id' => $reservationId,
                    'minutes_before' => $minutes,
                    'days_ahead' => $daysAhead,
                ]);

                continue;
            }

            $scheduleId = $this->schedule($reservationId, $sendAt, array_merge($customPayload, [
                'minutes_before' => $minutes,
            ]));

            if ($scheduleId) {
                $scheduledIds[$minutes] = $scheduleId;
            }
        }

        return $scheduledIds;
    }

    /**
     * Cancel a scheduled job.
     */
    public function cancel(string $scheduleId): bool
    {
        if (empty($this->token)) {
            return false;
        }

        try {
            // Use /v2/schedule/{id} for Upstash cloud or /v2/{id} for local
            $response = Http::withToken($this->token)
                ->delete("{$this->baseUrl}/schedule/{$scheduleId}");

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Exception cancelling Qstash schedule', [
                'schedule_id' => $scheduleId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Cancel multiple scheduled jobs.
     */
    public function cancelMultiple(array $scheduleIds): int
    {
        $cancelled = 0;

        foreach ($scheduleIds as $id) {
            if ($this->cancel($id)) {
                $cancelled++;
            }
        }

        return $cancelled;
    }
}
