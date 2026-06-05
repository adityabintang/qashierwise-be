<?php

namespace App\Services;

// Manually load Google API client aliases until composer autoloader is regenerated
if (file_exists(__DIR__ . '/../../vendor/google/apiclient/src/aliases.php')) {
    require_once __DIR__ . '/../../vendor/google/apiclient/src/aliases.php';
}

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
        // Lazy initialization - client will be created when needed
    }

    /**
     * Get or create Google Client instance.
     */
    protected function getClient(): \Google_Client
    {
        if (! $this->client) {
            $this->client = new \Google_Client;
            $this->client->setClientId(config('services.google.client_id'));
            $this->client->setClientSecret(config('services.google.client_secret'));
            $this->client->setRedirectUri(config('services.google.redirect_uri'));
            $this->client->addScope(\Google_Service_Calendar::CALENDAR);
            $this->client->setAccessType('offline');
            $this->client->setPrompt('consent');
        }

        return $this->client;
    }

    /**
     * Set access token for the Google Client.
     */
    public function setAccessToken(string $token): void
    {
        try {
            $client = $this->getClient();
            $client->setAccessToken($token);

            // Refresh token if expired
            if ($client->isAccessTokenExpired()) {
                $refreshToken = $client->getRefreshToken();
                if ($refreshToken) {
                    $newToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);
                    $client->setAccessToken($newToken);
                }
            }
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
            $client = $this->getClient();

            if (! $client->getAccessToken()) {
                throw new Exception('Google Calendar access token not set');
            }

            $service = new \Google_Service_Calendar($client);

            // Set event time with proper timezone handling
            $timezone = $reservation->store->timezone ?? 'Asia/Jakarta';
            $startDateTime = $reservation->reservation_date->copy();

            // If reservation has specific time, use it; otherwise use 12:00
            if ($reservation->reservation_time) {
                $timeParts = explode(':', $reservation->reservation_time);
                $startDateTime->setTime((int) $timeParts[0], (int) $timeParts[1]);
            } else {
                $startDateTime->setTime(12, 0);
            }

            $endDateTime = $startDateTime->copy()->addHours(2);

            $event = new \Google_Service_Calendar_Event([
                'summary' => 'Reservasi - '.$reservation->customer_name,
                'description' => $this->buildEventDescription($reservation),
                'start' => [
                    'dateTime' => $startDateTime->toRfc3339String(),
                    'timeZone' => $timezone,
                ],
                'end' => [
                    'dateTime' => $endDateTime->toRfc3339String(),
                    'timeZone' => $timezone,
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
                'location' => $reservation->store->address ?? '',
            ]);

            // sendUpdates=all so the customer (added as an attendee) receives the
            // Google Calendar invitation email — this is how the buyer's calendar
            // gets marked without them going through OAuth.
            $createdEvent = $service->events->insert('primary', $event, ['sendUpdates' => 'all']);

            Log::info('Calendar event created successfully', [
                'reservation_id' => $reservation->id,
                'event_id' => $createdEvent->getId(),
                'customer' => $reservation->customer_name,
                'date' => $reservation->reservation_date->format('Y-m-d'),
                'email' => $reservation->email,
            ]);

            return $createdEvent->getId();

        } catch (Exception $e) {
            Log::error('Failed to create calendar event', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return a fallback ID so the reservation can still proceed
            return 'fallback_'.$reservation->id.'_'.time();
        }
    }

    /**
     * Build a "Add to Google Calendar" template URL for the customer.
     *
     * This requires NO OAuth: the buyer simply opens the link and Google's
     * "Save event" screen is pre-filled. It is the no-OAuth counterpart to the
     * merchant's attendee-invite flow, and is delivered to the buyer over
     * WhatsApp after a paid reservation.
     *
     * @see https://calendar.google.com/calendar/render?action=TEMPLATE
     */
    public function buildAddToCalendarUrl(Reservation $reservation): string
    {
        $timezone = $reservation->store->timezone ?? 'Asia/Jakarta';

        // Mirror createEvent()'s time handling so the link matches the real event.
        $start = $reservation->reservation_date instanceof \Carbon\Carbon
            ? $reservation->reservation_date->copy()
            : \Carbon\Carbon::parse((string) $reservation->reservation_date);
        $start->setTimezone($timezone);

        if ($reservation->reservation_time) {
            $timeParts = explode(':', (string) $reservation->reservation_time);
            $start->setTime((int) ($timeParts[0] ?? 12), (int) ($timeParts[1] ?? 0));
        } else {
            $start->setTime(12, 0);
        }

        $end = $start->copy()->addHours(2);

        // Local wall-clock time + an explicit ctz param (Google reads dates as
        // the calendar's timezone when ctz is supplied).
        $dates = $start->format('Ymd\THis').'/'.$end->format('Ymd\THis');

        $params = [
            'action' => 'TEMPLATE',
            'text' => 'Reservasi - '.$reservation->customer_name,
            'dates' => $dates,
            'ctz' => $timezone,
            'details' => $this->buildEventDescription($reservation),
            'location' => $reservation->store->address ?? '',
        ];

        return 'https://calendar.google.com/calendar/render?'.http_build_query($params);
    }

    /**
     * Update an existing calendar event.
     */
    public function updateEvent(string $eventId, Reservation $reservation): void
    {
        try {
            $client = $this->getClient();

            if (! $client->getAccessToken()) {
                throw new Exception('Google Calendar access token not set');
            }

            // Skip update for fallback event IDs
            if (str_starts_with($eventId, 'fallback_')) {
                Log::warning('Skipping calendar update for fallback event ID', [
                    'event_id' => $eventId,
                ]);

                return;
            }

            $service = new \Google_Service_Calendar($client);

            $event = $service->events->get('primary', $eventId);
            $event->setSummary('Reservasi - '.$reservation->customer_name);
            $event->setDescription($this->buildEventDescription($reservation));

            // Update time if changed
            $timezone = $reservation->store->timezone ?? 'Asia/Jakarta';
            $startDateTime = $reservation->reservation_date->copy();

            $endDateTime = $startDateTime->copy()->addHours(2);

            $event->setStart(new \Google_Service_Calendar_EventDateTime([
                'dateTime' => $startDateTime->toRfc3339String(),
                'timeZone' => $timezone,
            ]));
            $event->setEnd(new \Google_Service_Calendar_EventDateTime([
                'dateTime' => $endDateTime->toRfc3339String(),
                'timeZone' => $timezone,
            ]));

            $service->events->update('primary', $eventId, $event);

            Log::info('Calendar event updated successfully', [
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
            $client = $this->getClient();

            if (! $client->getAccessToken()) {
                throw new Exception('Google Calendar access token not set');
            }

            // Skip deletion for fallback event IDs
            if (str_starts_with($eventId, 'fallback_')) {
                Log::warning('Skipping calendar deletion for fallback event ID', [
                    'event_id' => $eventId,
                ]);

                return;
            }

            $service = new \Google_Service_Calendar($client);
            $service->events->delete('primary', $eventId);

            Log::info('Calendar event deleted successfully', [
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
     * Get OAuth authorization URL.
     *
     * @param  string|null  $state  Opaque value echoed back on the callback.
     *                              Used to carry the merchant identity since
     *                              this app is Bearer-token (not session) based.
     */
    public function getAuthorizationUrl(?string $state = null): string
    {
        $client = $this->getClient();

        if ($state !== null) {
            $client->setState($state);
        }

        return $client->createAuthUrl();
    }

    /**
     * Exchange authorization code for access token.
     */
    public function authenticate(string $code): array
    {
        $token = $this->getClient()->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new Exception('Failed to authenticate: '.($token['error_description'] ?? $token['error']));
        }

        return $token;
    }

    /**
     * Get user's calendar email.
     */
    public function getCalendarEmail(): ?string
    {
        try {
            $client = $this->getClient();

            if (! $client->getAccessToken()) {
                return null;
            }

            $service = new \Google_Service_Calendar($client);
            $calendarList = $service->calendarList->get('primary');

            return $calendarList->getSummary();
        } catch (Exception $e) {
            Log::error('Failed to get calendar email', [
                'error' => $e->getMessage(),
            ]);

            return null;
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

        if ($reservation->notes) {
            $description .= "\nCatatan: {$reservation->notes}\n";
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
