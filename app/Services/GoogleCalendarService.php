<?php

namespace App\Services;

// Manually load Google API client aliases until composer autoloader is regenerated
if (file_exists(__DIR__ . '/../../vendor/google/apiclient/src/aliases.php')) {
    require_once __DIR__ . '/../../vendor/google/apiclient/src/aliases.php';
}

use App\Models\BuyerCalendarToken;
use App\Models\Reservation;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Crypt;
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

            $timezone = $reservation->store->timezone ?? 'Asia/Jakarta';
            // reservation_date is cast as 'date' → UTC midnight in Carbon.
            // Switch to the store's timezone BEFORE calling setTime() so that
            // "18:00" means 18:00 WIB, not 18:00 UTC (which would be 01:00 WIB
            // the following day when displayed in Google Calendar).
            $startDateTime = $reservation->reservation_date->copy()->setTimezone($timezone);

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

    // ===========================================================
    // Buyer calendar (customer-side OAuth)
    // ===========================================================

    /**
     * TTL (seconds) for the signed state used in the buyer OAuth flow.
     */
    private const BUYER_STATE_TTL = 900;

    /**
     * Build the Google OAuth consent URL for a BUYER.
     *
     * The signed `state` carries the buyer's email + the reservation that
     * triggered the connect request, so the callback can:
     *   1. Store the token keyed by email.
     *   2. Immediately create the calendar event for that reservation.
     *
     * Uses the narrower `calendar.events` scope — asks only for event
     * creation/editing, not full calendar management.
     */
    public function getBuyerAuthUrl(string $buyerEmail, int $reservationId): string
    {
        $client = new \Google_Client;
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.buyer_redirect_uri'));
        $client->addScope(\Google_Service_Calendar::CALENDAR_EVENTS);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        $state = Crypt::encryptString(
            implode('|', [$buyerEmail, $reservationId, time()])
        );
        $client->setState($state);

        return $client->createAuthUrl();
    }

    /**
     * Exchange the buyer's OAuth code for tokens and persist them.
     * Returns the stored BuyerCalendarToken.
     */
    public function authenticateBuyer(string $code, string $state): BuyerCalendarToken
    {
        // Validate + decrypt state.
        try {
            $parts = explode('|', Crypt::decryptString($state), 3);
        } catch (Exception $e) {
            throw new Exception('Invalid or tampered OAuth state.');
        }

        [$email, $reservationId, $issuedAt] = array_pad($parts, 3, null);

        if (! $email || (time() - (int) $issuedAt) > self::BUYER_STATE_TTL) {
            throw new Exception('OAuth state expired or invalid.');
        }

        $client = new \Google_Client;
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.buyer_redirect_uri'));

        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new Exception('Google OAuth failed: '.($token['error_description'] ?? $token['error']));
        }

        $stored = BuyerCalendarToken::updateOrCreate(
            ['email' => strtolower(trim($email))],
            [
                'access_token' => json_encode($token),
                'calendar_id'  => 'primary',
            ]
        );

        Log::info('Buyer Google Calendar connected', [
            'email'          => $email,
            'reservation_id' => $reservationId,
        ]);

        return $stored;
    }

    /**
     * Resolve the pending reservation_id from a buyer OAuth state string.
     * Used by the callback to create the calendar event after storing the token.
     */
    public function reservationIdFromBuyerState(string $state): ?int
    {
        try {
            $parts = explode('|', Crypt::decryptString($state), 3);
            $id = $parts[1] ?? null;
            return $id !== null ? (int) $id : null;
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Create a Google Calendar event on the BUYER's calendar using their
     * stored OAuth token. Returns the event ID, or null on failure.
     */
    public function createEventForBuyer(Reservation $reservation): ?string
    {
        $email = strtolower(trim((string) $reservation->email));
        if (! $email) {
            return null;
        }

        $stored = BuyerCalendarToken::findByEmail($email);
        if (! $stored) {
            return null;
        }

        try {
            $client = new \Google_Client;
            $client->setClientId(config('services.google.client_id'));
            $client->setClientSecret(config('services.google.client_secret'));
            $client->setRedirectUri(config('services.google.buyer_redirect_uri'));
            $client->setAccessToken($stored->access_token);

            if ($client->isAccessTokenExpired()) {
                $refreshToken = $client->getRefreshToken();
                if (! $refreshToken) {
                    Log::warning('Buyer calendar token expired and no refresh token', [
                        'email' => $email,
                    ]);
                    return null;
                }
                $newToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);
                $client->setAccessToken($newToken);
                // Persist the refreshed token.
                $stored->update(['access_token' => json_encode($newToken)]);
            }

            $service  = new \Google_Service_Calendar($client);
            $timezone = $reservation->store->timezone ?? 'Asia/Jakarta';

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

            $event = new \Google_Service_Calendar_Event([
                'summary'     => 'Reservasi - '.($reservation->store?->name ?? 'Qashierwise'),
                'description' => $this->buildEventDescription($reservation),
                'start'       => [
                    'dateTime' => $start->toRfc3339String(),
                    'timeZone' => $timezone,
                ],
                'end' => [
                    'dateTime' => $end->toRfc3339String(),
                    'timeZone' => $timezone,
                ],
                'location'  => $reservation->store?->address ?? '',
                'reminders' => [
                    'useDefault' => false,
                    'overrides'  => [
                        ['method' => 'popup', 'minutes' => 60],
                        ['method' => 'email', 'minutes' => 24 * 60],
                    ],
                ],
            ]);

            $created = $service->events->insert($stored->calendar_id, $event);

            Log::info('Calendar event created on buyer calendar', [
                'reservation_id' => $reservation->id,
                'event_id'       => $created->getId(),
                'buyer_email'    => $email,
            ]);

            return $created->getId();

        } catch (Exception $e) {
            Log::error('Failed to create calendar event on buyer calendar', [
                'reservation_id' => $reservation->id,
                'buyer_email'    => $email,
                'error'          => $e->getMessage(),
            ]);
            return null;
        }
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

            $timezone = $reservation->store->timezone ?? 'Asia/Jakarta';
            $startDateTime = $reservation->reservation_date->copy()->setTimezone($timezone);
            if ($reservation->reservation_time) {
                $tp = explode(':', $reservation->reservation_time);
                $startDateTime->setTime((int) $tp[0], (int) ($tp[1] ?? 0));
            } else {
                $startDateTime->setTime(12, 0);
            }

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
