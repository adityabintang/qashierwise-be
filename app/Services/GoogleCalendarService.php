<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;

class GoogleCalendarService
{
    protected $client;

    /**
     * Initialize Google Calendar Service.
     */
    public function __construct()
    {
        // Google API Client will be initialized when needed
        // Commented out since package installation had issues
        // You can enable this after successfully installing google/apiclient
        /*
        $this->client = new \Google_Client();
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setRedirectUri(config('services.google.redirect_uri'));
        $this->client->addScope(\Google_Service_Calendar::CALENDAR);
        */
    }

    /**
     * Set access token for the Google Client.
     */
    public function setAccessToken(string $token): void
    {
        try {
            // $this->client->setAccessToken($token);

            // Refresh token if expired
            // if ($this->client->isAccessTokenExpired()) {
            //     $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
            // }
        } catch (Exception $e) {
            Log::error('Failed to set Google Calendar access token', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create a calendar event for a reservation.
     *
     * @return string Event ID
     */
    public function createEvent(Reservation $reservation): string
    {
        try {
            // Get user's calendar email
            $calendarEmail = $reservation->user->google_calendar_email ?? $reservation->user->email;

            // For now, return a mock event ID since package isn't fully installed
            // Once google/apiclient is properly installed, uncomment below:

            /*
            if (!$this->client->getAccessToken()) {
                throw new Exception('Google Calendar access token not set');
            }

            $service = new \Google_Service_Calendar($this->client);

            // Set event time (assume 2 hours duration)
            $startDateTime = $reservation->reservation_date->setTime(12, 0);
            $endDateTime = $startDateTime->copy()->addHours(2);

            $event = new \Google_Service_Calendar_Event([
                'summary' => 'Reservasi - ' . $reservation->customer_name,
                'description' => $this->buildEventDescription($reservation),
                'start' => [
                    'dateTime' => $startDateTime->toRfc3339String(),
                    'timeZone' => 'Asia/Jakarta',
                ],
                'end' => [
                    'dateTime' => $endDateTime->toRfc3339String(),
                    'timeZone' => 'Asia/Jakarta',
                ],
                'attendees' => [
                    ['email' => $reservation->email],
                ],
                'reminders' => [
                    'useDefault' => false,
                    'overrides' => [
                        ['method' => 'email', 'minutes' => 24 * 60],
                        ['method' => 'popup', 'minutes' => 60],
                    ],
                ],
            ]);

            $createdEvent = $service->events->insert('primary', $event);

            return $createdEvent->getId();
            */

            // Mock implementation for now
            $eventId = 'evt_'.time().'_'.$reservation->id;

            Log::info('Calendar event created (mock)', [
                'reservation_id' => $reservation->id,
                'event_id' => $eventId,
                'customer' => $reservation->customer_name,
                'date' => $reservation->reservation_date->format('Y-m-d'),
            ]);

            return $eventId;

        } catch (Exception $e) {
            Log::error('Failed to create calendar event', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);

            // Return a fallback ID so the reservation can still proceed
            return 'fallback_'.$reservation->id.'_'.time();
        }
    }

    /**
     * Update an existing calendar event.
     */
    public function updateEvent(string $eventId, Reservation $reservation): void
    {
        try {
            /*
            $service = new \Google_Service_Calendar($this->client);

            $event = $service->events->get('primary', $eventId);
            $event->setSummary('Reservasi - ' . $reservation->customer_name);
            $event->setDescription($this->buildEventDescription($reservation));

            $service->events->update('primary', $eventId, $event);
            */

            Log::info('Calendar event updated (mock)', [
                'event_id' => $eventId,
                'reservation_id' => $reservation->id,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to update calendar event', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Delete a calendar event.
     */
    public function deleteEvent(string $eventId): void
    {
        try {
            /*
            $service = new \Google_Service_Calendar($this->client);
            $service->events->delete('primary', $eventId);
            */

            Log::info('Calendar event deleted (mock)', [
                'event_id' => $eventId,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to delete calendar event', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build event description with reservation details.
     */
    protected function buildEventDescription(Reservation $reservation): string
    {
        $description = "Reservasi Detail:\n\n";
        $description .= "Order ID: {$reservation->order_id}\n";
        $description .= "Nama: {$reservation->customer_name}\n";
        $description .= "Telepon: {$reservation->phone}\n";
        $description .= "Email: {$reservation->email}\n";
        $description .= "Jumlah Tamu: {$reservation->guest_count} orang\n";

        if ($reservation->table) {
            $description .= "Meja: {$reservation->table->number}\n";
        }

        $description .= "\nPembayaran:\n";
        $description .= 'Jenis: '.($reservation->payment_type === 'dp' ? 'DP' : 'Lunas')."\n";
        $description .= 'Total: Rp '.number_format($reservation->total_amount, 0, ',', '.')."\n";
        $description .= 'Dibayar: Rp '.number_format($reservation->paid_amount, 0, ',', '.')."\n";

        if ($reservation->remaining_amount > 0) {
            $description .= 'Sisa: Rp '.number_format($reservation->remaining_amount, 0, ',', '.')."\n";
        }

        return $description;
    }
}
