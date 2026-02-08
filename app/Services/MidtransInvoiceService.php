<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service for communicating with Midtrans Invoice API.
 *
 * Handles invoice creation and sending to customers after successful subscription payments.
 * Documentation: https://docs.midtrans.com/reference/create-invoice
 */
class MidtransInvoiceService
{
    private string $baseUrl;

    private string $serverKey;

    private bool $isProduction;

    public function __construct()
    {
        $this->serverKey = config('subscription.midtrans.server_key', '');
        $this->isProduction = config('subscription.midtrans.is_production', false);
        $this->baseUrl = $this->isProduction
            ? 'https://api.midtrans.com/v1'
            : 'https://api.sandbox.midtrans.com/v1';

        if (empty($this->serverKey)) {
            Log::warning('MidtransInvoiceService: Midtrans server key is not configured');
        }
    }

    /**
     * Create and send an invoice for a subscription payment.
     *
     * @param  User  $user  The user who made the payment
     * @param  string  $orderId  The order ID from the payment
     * @param  string  $planName  The name of the subscription plan
     * @param  string  $durationName  The duration name (e.g., "1 Bulan", "3 Bulan")
     * @param  float|int  $amount  The payment amount
     * @param  string  $currency  The currency code (default: IDR)
     * @param  array  $additionalItems  Additional items to include in the invoice
     * @return array|null Invoice data with PDF URL and status, or null on failure
     */
    public function createSubscriptionInvoice(
        User $user,
        string $orderId,
        string $planName,
        string $durationName,
        float|int $amount,
        string $currency = 'IDR',
        array $additionalItems = []
    ): ?array {
        // Check if invoice feature is enabled
        if (! config('subscription.invoice.enabled', true)) {
            Log::info('Invoice creation skipped: feature is disabled', [
                'userId' => $user->id,
                'orderId' => $orderId,
            ]);

            return null;
        }

        if (empty($this->serverKey)) {
            Log::error('Cannot create invoice: Midtrans server key not configured');

            return null;
        }

        // Generate unique invoice number
        $invoiceNumber = $this->generateInvoiceNumber($orderId);

        // Build invoice payload
        $payload = $this->buildInvoicePayload(
            $user,
            $orderId,
            $invoiceNumber,
            $planName,
            $durationName,
            $amount,
            $currency,
            $additionalItems
        );

        try {
            $startTime = microtime(true);

            Log::info('Creating Midtrans invoice', [
                'event' => 'invoice.create.started',
                'userId' => $user->id,
                'orderId' => $orderId,
                'invoiceNumber' => $invoiceNumber,
                'amount' => $amount,
                'currency' => $currency,
            ]);

            $response = Http::withBasicAuth($this->serverKey, '')
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("{$this->baseUrl}/invoices", $payload);

            $duration = microtime(true) - $startTime;

            if (! $response->successful()) {
                Log::error('Failed to create Midtrans invoice', [
                    'event' => 'invoice.create.failed',
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'userId' => $user->id,
                    'orderId' => $orderId,
                    'invoiceNumber' => $invoiceNumber,
                    'duration_ms' => round($duration * 1000, 2),
                ]);

                return null;
            }

            $data = $response->json();

            Log::info('Midtrans invoice created successfully', [
                'event' => 'invoice.create.success',
                'invoiceId' => $data['id'] ?? null,
                'invoiceNumber' => $invoiceNumber,
                'pdfUrl' => $data['pdf_url'] ?? null,
                'userId' => $user->id,
                'userEmail' => $user->email,
                'orderId' => $orderId,
                'status' => $data['status'] ?? null,
                'duration_ms' => round($duration * 1000, 2),
            ]);

            return $data;
        } catch (\Exception $e) {
            Log::error('Exception creating Midtrans invoice', [
                'error' => $e->getMessage(),
                'userId' => $user->id,
                'orderId' => $orderId,
                'invoiceNumber' => $invoiceNumber,
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Get invoice details by invoice ID.
     *
     * @param  string  $invoiceId  The Midtrans invoice ID
     * @return array|null Invoice data or null on failure
     */
    public function getInvoice(string $invoiceId): ?array
    {
        if (empty($this->serverKey)) {
            Log::error('Cannot get invoice: Midtrans server key not configured');

            return null;
        }

        try {
            Log::debug('Fetching Midtrans invoice', [
                'invoiceId' => $invoiceId,
            ]);

            $response = Http::withBasicAuth($this->serverKey, '')
                ->get("{$this->baseUrl}/invoices/{$invoiceId}");

            if (! $response->successful()) {
                Log::error('Failed to get Midtrans invoice', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'invoiceId' => $invoiceId,
                ]);

                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Exception getting Midtrans invoice', [
                'error' => $e->getMessage(),
                'invoiceId' => $invoiceId,
            ]);

            return null;
        }
    }

    /**
     * Void an invoice.
     *
     * @param  string  $invoiceId  The Midtrans invoice ID
     * @return bool True on success, false on failure
     */
    public function voidInvoice(string $invoiceId): bool
    {
        if (empty($this->serverKey)) {
            Log::error('Cannot void invoice: Midtrans server key not configured');

            return false;
        }

        try {
            Log::info('Voiding Midtrans invoice', [
                'invoiceId' => $invoiceId,
            ]);

            $response = Http::withBasicAuth($this->serverKey, '')
                ->post("{$this->baseUrl}/invoices/{$invoiceId}/void");

            if (! $response->successful()) {
                Log::error('Failed to void Midtrans invoice', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'invoiceId' => $invoiceId,
                ]);

                return false;
            }

            Log::info('Midtrans invoice voided successfully', [
                'invoiceId' => $invoiceId,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Exception voiding Midtrans invoice', [
                'error' => $e->getMessage(),
                'invoiceId' => $invoiceId,
            ]);

            return false;
        }
    }

    /**
     * Generate a unique invoice number based on order ID.
     *
     * @param  string  $orderId  The original order ID
     * @return string Formatted invoice number
     */
    private function generateInvoiceNumber(string $orderId): string
    {
        // Format: INV-YYYYMMDD-{short_uuid}
        $date = now()->format('Ymd');
        $shortUuid = Str::upper(Str::substr(Str::uuid()->toString(), 0, 8));

        return "INV-{$date}-{$shortUuid}";
    }

    /**
     * Build the invoice payload for Midtrans API.
     *
     * @param  User  $user  The user
     * @param  string  $orderId  The order ID
     * @param  string  $invoiceNumber  The invoice number
     * @param  string  $planName  The plan name
     * @param  string  $durationName  The duration name
     * @param  float|int  $amount  The amount
     * @param  string  $currency  The currency
     * @param  array  $additionalItems  Additional items
     * @return array The invoice payload
     */
    private function buildInvoicePayload(
        User $user,
        string $orderId,
        string $invoiceNumber,
        string $planName,
        string $durationName,
        float|int $amount,
        string $currency,
        array $additionalItems
    ): array {
        $now = now();
        $dueDays = (int) config('subscription.invoice.due_days', 7);
        $dueDate = $now->copy()->addDays($dueDays);

        // Generate shorter IDs for Midtrans constraints
        // order_id max 36 chars, item_id max 30 chars
        $shortOrderId = Str::substr(md5($orderId), 0, 16);
        $invoiceOrderId = "INV-{$shortOrderId}"; // 20 chars
        $itemId = "ITEM-{$shortOrderId}"; // 21 chars

        // Build item details
        $itemDetails = [
            [
                'item_id' => $itemId,
                'description' => "Subscription {$planName} - {$durationName}",
                'quantity' => 1,
                'price' => (int) $amount,
            ],
        ];

        // Add any additional items (e.g., discounts shown as separate line)
        foreach ($additionalItems as $item) {
            $itemDetails[] = [
                'item_id' => $item['id'] ?? Str::uuid()->toString(),
                'description' => $item['description'] ?? 'Additional item',
                'quantity' => $item['quantity'] ?? 1,
                'price' => (int) ($item['price'] ?? 0),
            ];
        }

        $appName = config('app.name', 'QashierWise');

        return [
            'order_id' => $invoiceOrderId,
            'invoice_number' => $invoiceNumber,
            'due_date' => $dueDate->format('Y-m-d H:i:s O'),
            'invoice_date' => $now->format('Y-m-d H:i:s O'),
            'invoice_title' => 'INVOICE',
            'invoice_paid_title' => 'PAID',
            'customer_details' => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
            ],
            'payment_type' => 'payment_link',
            'reference' => $orderId,
            'item_details' => $itemDetails,
            'notes' => "Terima kasih telah berlangganan {$planName} di {$appName}. Invoice ini dikirim sebagai konfirmasi pembayaran Anda.",
            'payment_link' => [
                'is_custom_expiry' => true,
                'enabled_payments' => config('subscription.invoice.payment_methods', [
                    'bca_va',
                    'bni_va',
                    'bri_va',
                    'permata_va',
                    'gopay',
                    'shopeepay',
                ]),
                'expiry' => [
                    'unit' => 'days',
                    'duration' => $dueDays,
                ],
            ],
        ];
    }

    /**
     * Check if the service is properly configured.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->serverKey);
    }
}
