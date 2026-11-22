<?php

namespace App\Jobs;

use App\Models\Reservation;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppAccountService;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;

/**
 * Job for sending WhatsApp notifications for reservations.
 *
 * This handles:
 * - Success notifications (payment confirmed)
 * - Failure notifications (payment failed)
 */
class SendReservationNotification implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [10, 30, 60];

    /**
     * The reservation.
     */
    protected Reservation $reservation;

    /**
     * The notification type ('success' or 'failure').
     */
    protected string $type;

    /**
     * Create a new job instance.
     *
     * @param  string  $type  'success' or 'failure'
     */
    public function __construct(Reservation $reservation, string $type)
    {
        $this->reservation = $reservation;
        $this->type = $type;
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsAppAccountService $whatsappService): void
    {
        Log::info('SendReservationNotification job started', [
            'reservation_id' => $this->reservation->id,
            'order_id' => $this->reservation->order_id,
            'type' => $this->type,
            'customer_phone' => $this->reservation->phone,
        ]);

        try {
            // Get merchant's WhatsApp account
            $whatsappAccount = $whatsappService->getActiveAccount($this->reservation->user_id);

            if (! $whatsappAccount) {
                Log::warning('Merchant does not have active WhatsApp account', [
                    'user_id' => $this->reservation->user_id,
                    'reservation_id' => $this->reservation->id,
                ]);

                return;
            }

            // Initialize WhatsApp API
            $whatsapp = new WhatsAppCloudApi([
                'from_phone_number_id' => $whatsappAccount->phone_number_id,
                'access_token' => $whatsappAccount->access_token,
            ]);

            // Debug logging
            Log::info('WhatsApp credentials loaded', [
                'user_id' => $this->reservation->user_id,
                'phone_number_id' => $whatsappAccount->phone_number_id,
                'business_account_id' => $whatsappAccount->business_account_id,
                'display_phone_number' => $whatsappAccount->display_phone_number,
                'status' => $whatsappAccount->status,
                'quality_rating' => $whatsappAccount->quality_rating,
                'is_active' => $whatsappAccount->is_active,
                'verified_at' => $whatsappAccount->verified_at?->toDateTimeString(),
            ]);

            // Format phone number (ensure it has country code)
            $customerPhone = $this->formatPhoneNumber($this->reservation->phone);

            // Build message based on type
            $message = $this->type === 'success'
                ? $this->buildSuccessMessage()
                : $this->buildFailureMessage();

            // Send message
            $response = $whatsapp->sendTextMessage(
                $customerPhone,
                $message,
                false // Don't preview URLs
            );

            $responseBody = $this->extractResponseBody($response);
            $messageId = $this->extractMessageId($responseBody);

            if ($messageId) {
                $this->persistOutgoingMessage(
                    userId: $this->reservation->user_id,
                    phoneNumberId: (string) $whatsappAccount->phone_number_id,
                    customerPhone: $customerPhone,
                    messageId: $messageId,
                    message: $message,
                    status: 'sent'
                );
            } else {
                Log::warning('Reservation notification sent but missing message_id in response', [
                    'reservation_id' => $this->reservation->id,
                    'order_id' => $this->reservation->order_id,
                    'type' => $this->type,
                    'customer_phone' => $customerPhone,
                ]);
            }

            Log::info('Reservation notification sent', [
                'reservation_id' => $this->reservation->id,
                'order_id' => $this->reservation->order_id,
                'type' => $this->type,
                'customer_phone' => $customerPhone,
                'message_id' => $messageId,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to send reservation notification', [
                'reservation_id' => $this->reservation->id,
                'order_id' => $this->reservation->order_id,
                'type' => $this->type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't throw - we don't want to fail the entire process if notification fails
            // The reservation is still valid even if notification fails
        }
    }

    /**
     * Build success notification message.
     */
    protected function buildSuccessMessage(): string
    {
        $message = "✅ *Pembayaran Berhasil!*\n\n";
        $message .= "Reservasi Anda telah dikonfirmasi.\n\n";
        $message .= "📋 *Detail Reservasi:*\n";
        $message .= "Order ID: *{$this->reservation->order_id}*\n";
        $message .= "Nama: {$this->reservation->customer_name}\n";
        $message .= "Tanggal: {$this->reservation->reservation_date->format('d M Y')}\n";
        $message .= "Jumlah Tamu: {$this->reservation->guest_count} orang\n";

        if ($this->reservation->table) {
            $message .= "Meja: {$this->reservation->table->number}\n";
        }

        $message .= "\n💰 *Pembayaran:*\n";
        $message .= 'Total: Rp '.number_format($this->reservation->total_amount, 0, ',', '.')."\n";
        $message .= 'Dibayar: Rp '.number_format($this->reservation->paid_amount, 0, ',', '.')."\n";

        if ($this->reservation->remaining_amount > 0) {
            $message .= 'Sisa: Rp '.number_format($this->reservation->remaining_amount, 0, ',', '.')."\n";
            $message .= "\n⚠️ *Pelunasan di tempat pada hari H*\n";
        }

        // Buyer calendar marking (no OAuth needed): a tap-to-save Google Calendar
        // link. If the merchant has connected their Google Calendar, the buyer
        // also receives a formal email invitation (attendee + sendUpdates=all).
        try {
            $calendarUrl = app(\App\Services\GoogleCalendarService::class)
                ->buildAddToCalendarUrl($this->reservation);
            $message .= "\n📅 *Tambahkan ke Google Calendar Anda:*\n{$calendarUrl}\n";
        } catch (\Throwable $e) {
            Log::warning('Failed to build add-to-calendar link for reservation', [
                'reservation_id' => $this->reservation->id,
                'error' => $e->getMessage(),
            ]);
        }

        if ($this->reservation->user?->google_calendar_refresh_token) {
            $message .= "\n📧 Undangan kalender juga telah dikirim ke email Anda.\n";
        }

        $message .= "\nTerima kasih! 🙏";

        return $message;
    }

    /**
     * Build failure notification message.
     */
    protected function buildFailureMessage(): string
    {
        $message = "❌ *Pembayaran Gagal*\n\n";
        $message .= "Maaf, pembayaran reservasi Anda tidak berhasil.\n\n";
        $message .= "Order ID: *{$this->reservation->order_id}*\n\n";
        $message .= "Silakan coba lagi atau hubungi kami untuk bantuan.\n";
        $message .= "\nTerima kasih.";

        return $message;
    }

    /**
     * Format phone number to include country code.
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // Remove any non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Add country code if not present (assuming Indonesia +62)
        if (substr($phone, 0, 2) !== '62') {
            // Remove leading 0 if present
            if (substr($phone, 0, 1) === '0') {
                $phone = '62'.substr($phone, 1);
            } else {
                $phone = '62'.$phone;
            }
        }

        return $phone;
    }

    protected function extractResponseBody(mixed $response): array
    {
        if (is_object($response)) {
            if (method_exists($response, 'decodedBody')) {
                $decoded = $response->decodedBody();

                return is_array($decoded) ? $decoded : [];
            }

            if (method_exists($response, 'json')) {
                $decoded = $response->json();

                return is_array($decoded) ? $decoded : [];
            }
        }

        return is_array($response) ? $response : [];
    }

    protected function extractMessageId(array $responseBody): ?string
    {
        $messageId = $responseBody['messages'][0]['id'] ?? null;

        return is_string($messageId) && $messageId !== '' ? $messageId : null;
    }

    protected function persistOutgoingMessage(
        int $userId,
        string $phoneNumberId,
        string $customerPhone,
        string $messageId,
        string $message,
        string $status
    ): void {
        $contact = WhatsAppContact::withoutGlobalScopes()->firstOrCreate(
            [
                'user_id' => $userId,
                'phone_number_id' => $phoneNumberId,
                'wa_id' => $customerPhone,
            ],
            [
                'name' => $this->reservation->customer_name ?: $customerPhone,
            ]
        );

        WhatsAppMessage::withoutGlobalScopes()->updateOrCreate(
            ['message_id' => $messageId],
            [
                'user_id' => $userId,
                'phone_number_id' => $phoneNumberId,
                'contact_id' => $contact->id,
                'direction' => 'outgoing',
                'type' => 'text',
                'content' => $message,
                'metadata' => [
                    'source' => 'reservation_notification',
                    'reservation_id' => $this->reservation->id,
                    'order_id' => $this->reservation->order_id,
                    'notification_type' => $this->type,
                ],
                'status' => $status,
                'is_read' => true,
                'sent_at' => now(),
            ]
        );

        $contact->update([
            'last_message_at' => now(),
            'last_message_text' => $message,
        ]);
    }
}
