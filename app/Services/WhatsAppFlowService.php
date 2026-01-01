<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\Table;
use App\Models\WhatsAppContact;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppFlowService
{
    private WhatsAppAccountService $accountService;

    public function __construct(WhatsAppAccountService $accountService)
    {
        $this->accountService = $accountService;
    }

    /**
     * Create a reservation flow in WhatsApp Business API
     */
    public function createReservationFlow(int $userId): array
    {
        $account = $this->accountService->getActiveAccount($userId);

        if (! $account) {
            throw new \Exception('No active WhatsApp account found');
        }

        $wabaId = $account->waba_id ?? $account->business_account_id;
        $flowJson = $this->getReservationFlowJson();

        $response = Http::withToken($account->access_token)
            ->post("https://graph.facebook.com/v21.0/{$wabaId}/flows", [
                'name' => 'Reservasi_'.Str::random(6),
                'categories' => ['APPOINTMENT_BOOKING'],
                'flow_json' => json_encode($flowJson),
            ]);

        if ($response->failed()) {
            Log::error('Failed to create WhatsApp Flow', [
                'response' => $response->json(),
                'user_id' => $userId,
            ]);

            throw new \Exception('Failed to create flow: '.($response->json()['error']['message'] ?? 'Unknown error'));
        }

        return $response->json();
    }

    /**
     * Get list of flows for a user
     */
    public function listFlows(int $userId): array
    {
        $account = $this->accountService->getActiveAccount($userId);

        if (! $account) {
            return [];
        }

        $wabaId = $account->waba_id ?? $account->business_account_id;

        $response = Http::withToken($account->access_token)
            ->get("https://graph.facebook.com/v21.0/{$wabaId}/flows");

        if ($response->failed()) {
            Log::error('Failed to list WhatsApp Flows', [
                'response' => $response->json(),
                'user_id' => $userId,
            ]);

            return [];
        }

        return $response->json()['data'] ?? [];
    }

    /**
     * Get flow details
     */
    public function getFlow(int $userId, string $flowId): ?array
    {
        $account = $this->accountService->getActiveAccount($userId);

        if (! $account) {
            return null;
        }

        $response = Http::withToken($account->access_token)
            ->get("https://graph.facebook.com/v21.0/{$flowId}", [
                'fields' => 'id,name,status,categories,validation_errors,json_version,data_api_version,endpoint_uri,preview',
            ]);

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Publish a flow (make it live)
     * 
     * Before publishing, this method ensures:
     * 1. Endpoint URI is configured
     * 2. Public signing key is uploaded
     * 3. Health check passes
     */
    public function publishFlow(int $userId, string $flowId): bool
    {
        $account = $this->accountService->getActiveAccount($userId);

        if (! $account) {
            \Log::warning('publishFlow: No active account found', ['user_id' => $userId]);
            return false;
        }

        // Step 1: Configure endpoint URI
        $endpointUri = config('app.url') . '/api/whatsapp/flow/endpoint';
        
        \Log::info('publishFlow: Setting endpoint URI', [
            'flow_id' => $flowId,
            'endpoint_uri' => $endpointUri,
        ]);

        $updateResponse = Http::withToken($account->access_token)
            ->post("https://graph.facebook.com/v21.0/{$flowId}", [
                'endpoint_uri' => $endpointUri,
            ]);

        if (! $updateResponse->successful()) {
            \Log::error('publishFlow: Failed to set endpoint URI', [
                'flow_id' => $flowId,
                'status' => $updateResponse->status(),
                'response' => $updateResponse->json(),
            ]);
            // Continue anyway, maybe it's already set
        } else {
            \Log::info('publishFlow: Endpoint URI set successfully');
        }

        // Step 2: Upload public signing key
        $publicKeyUploaded = $this->uploadPublicSigningKey($account);
        if (! $publicKeyUploaded) {
            \Log::warning('publishFlow: Public key upload may have failed, continuing anyway');
        }

        // Step 3: Publish the flow
        $response = Http::withToken($account->access_token)
            ->post("https://graph.facebook.com/v21.0/{$flowId}/publish");

        if (! $response->successful()) {
            \Log::error('publishFlow failed', [
                'user_id' => $userId,
                'flow_id' => $flowId,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
        }

        return $response->successful();
    }

    /**
     * Upload public signing key to Meta
     * Required for WhatsApp Flow encryption
     */
    public function uploadPublicSigningKey($account): bool
    {
        $privateKeyRaw = config('services.whatsapp.flow_private_key');
        
        if (empty($privateKeyRaw)) {
            \Log::error('uploadPublicSigningKey: Private key not configured');
            return false;
        }

        // Decode private key and extract public key
        $privateKeyPem = $this->decodePrivateKey($privateKeyRaw);
        $passphrase = config('services.whatsapp.flow_passphrase', '');
        
        $privateKey = openssl_pkey_get_private($privateKeyPem, $passphrase);
        if ($privateKey === false) {
            \Log::error('uploadPublicSigningKey: Failed to load private key', [
                'error' => openssl_error_string(),
            ]);
            return false;
        }

        $keyDetails = openssl_pkey_get_details($privateKey);
        $publicKey = $keyDetails['key'];

        \Log::info('uploadPublicSigningKey: Uploading public key', [
            'phone_number_id' => $account->phone_number_id,
            'key_bits' => $keyDetails['bits'],
        ]);

        $apiVersion = config('services.whatsapp.api_version', 'v21.0');
        $url = "https://graph.facebook.com/{$apiVersion}/{$account->phone_number_id}/whatsapp_business_encryption";

        $response = Http::withToken($account->access_token)
            ->post($url, [
                'business_public_key' => $publicKey,
            ]);

        if ($response->successful() && ($response->json()['success'] ?? false)) {
            \Log::info('uploadPublicSigningKey: Public key uploaded successfully');
            return true;
        }

        \Log::error('uploadPublicSigningKey: Failed to upload public key', [
            'status' => $response->status(),
            'response' => $response->json(),
        ]);

        return false;
    }

    /**
     * Decode private key from various formats
     */
    private function decodePrivateKey(string $key): string
    {
        // If it already looks like a PEM key, return as-is
        if (str_contains($key, '-----BEGIN')) {
            return $key;
        }

        // Try base64 decoding
        $decoded = base64_decode($key, true);
        if ($decoded !== false && str_contains($decoded, '-----BEGIN')) {
            return $decoded;
        }

        // Try replacing literal \n with newlines
        $withNewlines = str_replace('\\n', "\n", $key);
        if (str_contains($withNewlines, '-----BEGIN')) {
            return $withNewlines;
        }

        // Assume it's a base64-encoded key without headers
        return "-----BEGIN PRIVATE KEY-----\n" .
            chunk_split($key, 64, "\n") .
            "-----END PRIVATE KEY-----\n";
    }

    /**
     * Delete a flow
     */
    public function deleteFlow(int $userId, string $flowId): bool
    {
        $account = $this->accountService->getActiveAccount($userId);

        if (! $account) {
            return false;
        }

        $response = Http::withToken($account->access_token)
            ->delete("https://graph.facebook.com/v21.0/{$flowId}");

        return $response->successful();
    }

    /**
     * Send a reservation flow message to a customer
     */
    public function sendReservationFlow(int $userId, string $phoneNumber, string $flowId): array
    {
        $account = $this->accountService->getActiveAccount($userId);

        if (! $account) {
            throw new \Exception('No active WhatsApp account found');
        }

        // Flow token format: {user_id}_{uuid} for extracting user context in endpoint
        $flowToken = $userId.'_'.Str::uuid()->toString();

        $response = Http::withToken($account->access_token)
            ->post("https://graph.facebook.com/v21.0/{$account->phone_number_id}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $this->formatPhoneNumber($phoneNumber),
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'flow',
                    'header' => [
                        'type' => 'text',
                        'text' => '📅 Buat Reservasi',
                    ],
                    'body' => [
                        'text' => 'Silakan isi form di bawah ini untuk membuat reservasi di tempat kami. Anda dapat memilih tanggal, waktu, menu, dan metode pembayaran.',
                    ],
                    'footer' => [
                        'text' => 'Powered by QashierWise',
                    ],
                    'action' => [
                        'name' => 'flow',
                        'parameters' => [
                            'flow_message_version' => '3',
                            'flow_token' => $flowToken,
                            'flow_id' => $flowId,
                            'flow_cta' => 'Buat Reservasi',
                            'flow_action' => 'data_exchange',
                        ],
                    ],
                ],
            ]);

        if ($response->failed()) {
            Log::error('Failed to send reservation flow', [
                'response' => $response->json(),
                'user_id' => $userId,
                'phone' => $phoneNumber,
            ]);

            throw new \Exception('Failed to send flow: '.($response->json()['error']['message'] ?? 'Unknown error'));
        }

        return [
            'success' => true,
            'flow_token' => $flowToken,
            'message_id' => $response->json()['messages'][0]['id'] ?? null,
        ];
    }

    /**
     * Process flow response and create reservation (v7.3 with payment support)
     */
    public function processFlowResponse(
        int $userId,
        string $flowToken,
        array $responseData,
        ?WhatsAppContact $contact = null
    ): Reservation {
        // Validate required fields
        $required = ['customer_name', 'phone', 'reservation_date', 'reservation_time', 'guest_count'];
        foreach ($required as $field) {
            if (empty($responseData[$field])) {
                throw new \Exception("Missing required field: {$field}");
            }
        }

        // Parse guest count if it's a range string
        $guestCount = $this->parseGuestCount($responseData['guest_count']);

        // Extract payment and pricing data
        $menuTotal = (float) ($responseData['menu_total'] ?? 0);
        $tableFee = (float) ($responseData['table_fee'] ?? config('services.whatsapp.flow_table_fee', 100000));
        $grandTotal = (float) ($responseData['grand_total'] ?? ($menuTotal + $tableFee));
        $dpAmount = (float) ($responseData['dp_amount'] ?? ceil($grandTotal * 0.5));
        $paymentType = $responseData['payment_type'] ?? 'dp';
        $paymentMethod = $responseData['payment_method'] ?? 'cash';
        $paymentAmount = (float) ($responseData['payment_amount'] ?? ($paymentType === 'lunas' ? $grandTotal : $dpAmount));

        // Determine status and payment label based on payment type
        $status = $paymentType === 'lunas' ? 'confirmed' : 'pending';
        $paymentLabel = strtoupper($paymentType); // 'DP' or 'LUNAS'

        // Create reservation in a transaction
        return DB::transaction(function () use (
            $userId, $contact, $responseData, $flowToken, $guestCount,
            $menuTotal, $tableFee, $grandTotal, $paymentAmount, $paymentType,
            $paymentMethod, $paymentLabel, $status
        ) {
            // Create the reservation
            $reservation = Reservation::create([
                'user_id' => $userId,
                'whatsapp_contact_id' => $contact?->id,
                'customer_name' => $responseData['customer_name'],
                'phone' => $this->formatPhoneNumber($responseData['phone']),
                'reservation_date' => $responseData['reservation_date'],
                'reservation_time' => $responseData['reservation_time'],
                'guest_count' => $guestCount,
                'email' => $responseData['email'] ?? null,
                'event_type' => $responseData['event_type'] ?? null,
                'special_notes' => $responseData['special_notes'] ?? null,
                'preferences' => $responseData['preferences'] ?? [],
                'pre_order_items' => $responseData['selected_products'] ?? [],
                'table_id' => ! empty($responseData['table_id']) ? (int) $responseData['table_id'] : null,
                'table_fee' => $tableFee,
                'menu_total' => $menuTotal,
                'total_amount' => $grandTotal,
                'paid_amount' => $paymentMethod === 'qris' ? $paymentAmount : 0, // QRIS is prepaid
                'deposit' => $paymentAmount,
                'deposit_paid' => $paymentMethod === 'qris',
                'payment_type' => $paymentType,
                'payment_method' => $paymentMethod,
                'payment_label' => $paymentLabel,
                'flow_token' => $flowToken,
                'flow_id' => $responseData['flow_id'] ?? null,
                'status' => $status,
                'confirmed_at' => $status === 'confirmed' ? now() : null,
            ]);

            // Update table status to reserved if table was selected
            if (! empty($responseData['table_id'])) {
                Table::where('id', $responseData['table_id'])
                    ->where('user_id', $userId)
                    ->update(['status' => Table::STATUS_RESERVED]);
            }

            // Create Order if there are selected products
            $selectedProducts = $responseData['selected_products'] ?? [];
            if (! empty($selectedProducts)) {
                $this->createOrderFromReservation($reservation, $selectedProducts, $userId);
            }

            Log::info('Reservation created from WhatsApp Flow v7.3', [
                'reservation_id' => $reservation->id,
                'user_id' => $userId,
                'flow_token' => $flowToken,
                'payment_type' => $paymentType,
                'payment_method' => $paymentMethod,
                'payment_label' => $paymentLabel,
                'total_amount' => $grandTotal,
                'paid_amount' => $paymentAmount,
            ]);

            return $reservation;
        });
    }

    /**
     * Create an Order from reservation pre-order items
     */
    private function createOrderFromReservation(Reservation $reservation, array $productIds, int $userId): ?Order
    {
        if (empty($productIds)) {
            return null;
        }

        // Get products
        $products = Product::where('user_id', $userId)
            ->whereIn('id', $productIds)
            ->get();

        if ($products->isEmpty()) {
            return null;
        }

        // Calculate totals
        $subtotal = $products->sum('price');
        $tableFee = $reservation->table_fee ?? 0;
        $total = $subtotal + $tableFee;

        // Determine order status based on payment
        $orderStatus = $reservation->payment_type === 'lunas' ? 'completed' : 'pending';

        // Create the order
        $order = Order::create([
            'user_id' => $userId,
            'table_id' => $reservation->table_id,
            'order_number' => 'RES-'.str_pad($reservation->id, 6, '0', STR_PAD_LEFT),
            'status' => $orderStatus,
            'subtotal' => $subtotal,
            'tax' => 0,
            'discount' => 0,
            'total' => $total,
            'notes' => "Reservasi #{$reservation->id} - {$reservation->customer_name}",
            'payment_method' => $reservation->payment_method,
            'payment_label' => $reservation->payment_label,
            'reservation_id' => $reservation->id,
        ]);

        // Create order items
        foreach ($products as $product) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => $product->price,
                'subtotal' => $product->price,
            ]);
        }

        // Link reservation to order
        $reservation->update(['order_id' => $order->id]);

        Log::info('Order created from reservation', [
            'order_id' => $order->id,
            'reservation_id' => $reservation->id,
            'product_count' => count($products),
            'total' => $total,
        ]);

        return $order;
    }

    /**
     * Build flow response for confirmation screen
     */
    public function buildConfirmationResponse(Reservation $reservation): array
    {
        $confirmationText = sprintf(
            "Reservasi atas nama %s untuk %d tamu pada %s jam %s telah diterima.\n\nStatus: Menunggu konfirmasi\nKode: #RES%06d",
            $reservation->customer_name,
            $reservation->guest_count,
            $reservation->reservation_date->format('d M Y'),
            $reservation->reservation_time->format('H:i'),
            $reservation->id
        );

        return [
            'screen' => 'CONFIRMATION_SCREEN',
            'data' => [
                'confirmation_text' => $confirmationText,
                'reservation_id' => $reservation->id,
            ],
        ];
    }

    /**
     * Get the reservation flow JSON structure
     */
    public function getReservationFlowJson(): array
    {
        $flowPath = resource_path('whatsapp/flows/reservation-flow.json');

        if (file_exists($flowPath)) {
            return json_decode(file_get_contents($flowPath), true);
        }

        // Return default flow structure
        return $this->getDefaultReservationFlowStructure();
    }

    /**
     * Get default reservation flow structure
     */
    private function getDefaultReservationFlowStructure(): array
    {
        return [
            'version' => '3.0',
            'screens' => [
                [
                    'id' => 'RESERVATION_FORM',
                    'title' => 'Buat Reservasi',
                    'data' => [],
                    'layout' => [
                        'type' => 'SingleColumnLayout',
                        'children' => [
                            [
                                'type' => 'Form',
                                'name' => 'reservation_form',
                                'children' => [
                                    [
                                        'type' => 'TextInput',
                                        'name' => 'customer_name',
                                        'label' => 'Nama Lengkap',
                                        'required' => true,
                                        'input-type' => 'text',
                                    ],
                                    [
                                        'type' => 'TextInput',
                                        'name' => 'phone',
                                        'label' => 'Nomor WhatsApp',
                                        'required' => true,
                                        'input-type' => 'phone',
                                        'helper-text' => 'Untuk konfirmasi reservasi',
                                    ],
                                    [
                                        'type' => 'DatePicker',
                                        'name' => 'reservation_date',
                                        'label' => 'Tanggal Reservasi',
                                        'required' => true,
                                    ],
                                    [
                                        'type' => 'Dropdown',
                                        'name' => 'reservation_time',
                                        'label' => 'Jam Reservasi',
                                        'required' => true,
                                        'data-source' => [
                                            ['id' => '11:00', 'title' => '11:00 WIB'],
                                            ['id' => '12:00', 'title' => '12:00 WIB'],
                                            ['id' => '13:00', 'title' => '13:00 WIB'],
                                            ['id' => '18:00', 'title' => '18:00 WIB'],
                                            ['id' => '19:00', 'title' => '19:00 WIB'],
                                            ['id' => '20:00', 'title' => '20:00 WIB'],
                                            ['id' => '21:00', 'title' => '21:00 WIB'],
                                        ],
                                    ],
                                    [
                                        'type' => 'Dropdown',
                                        'name' => 'guest_count',
                                        'label' => 'Jumlah Tamu',
                                        'required' => true,
                                        'data-source' => [
                                            ['id' => '1', 'title' => '1 orang'],
                                            ['id' => '2', 'title' => '2 orang'],
                                            ['id' => '3', 'title' => '3-4 orang'],
                                            ['id' => '5', 'title' => '5-6 orang'],
                                            ['id' => '7', 'title' => '7-10 orang'],
                                            ['id' => '10', 'title' => 'Lebih dari 10'],
                                        ],
                                    ],
                                    [
                                        'type' => 'TextInput',
                                        'name' => 'email',
                                        'label' => 'Email (Opsional)',
                                        'required' => false,
                                        'input-type' => 'email',
                                    ],
                                    [
                                        'type' => 'Dropdown',
                                        'name' => 'event_type',
                                        'label' => 'Tipe Acara',
                                        'required' => false,
                                        'data-source' => [
                                            ['id' => 'regular', 'title' => 'Makan biasa'],
                                            ['id' => 'birthday', 'title' => 'Ulang tahun'],
                                            ['id' => 'meeting', 'title' => 'Meeting/Bisnis'],
                                            ['id' => 'anniversary', 'title' => 'Anniversary'],
                                            ['id' => 'other', 'title' => 'Lainnya'],
                                        ],
                                    ],
                                    [
                                        'type' => 'TextArea',
                                        'name' => 'special_notes',
                                        'label' => 'Catatan Khusus',
                                        'required' => false,
                                        'helper-text' => 'Contoh: kursi bayi, alergi, dll',
                                    ],
                                    [
                                        'type' => 'Footer',
                                        'label' => 'Kirim',
                                        'on-click-action' => [
                                            'name' => 'complete',
                                            'payload' => [
                                                'customer_name' => '${form.customer_name}',
                                                'phone' => '${form.phone}',
                                                'reservation_date' => '${form.reservation_date}',
                                                'reservation_time' => '${form.reservation_time}',
                                                'guest_count' => '${form.guest_count}',
                                                'email' => '${form.email}',
                                                'event_type' => '${form.event_type}',
                                                'special_notes' => '${form.special_notes}',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'CONFIRMATION_SCREEN',
                    'title' => 'Reservasi Diterima',
                    'terminal' => true,
                    'success' => true,
                    'data' => [
                        'confirmation_text' => [
                            'type' => 'string',
                            '__example__' => 'Reservasi Anda telah diterima',
                        ],
                    ],
                    'layout' => [
                        'type' => 'SingleColumnLayout',
                        'children' => [
                            [
                                'type' => 'TextHeading',
                                'text' => '✅ Reservasi Diterima!',
                            ],
                            [
                                'type' => 'TextBody',
                                'text' => '${data.confirmation_text}',
                            ],
                            [
                                'type' => 'TextCaption',
                                'text' => 'Kami akan menghubungi Anda untuk konfirmasi.',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Parse guest count from string like "3-4" to integer
     */
    private function parseGuestCount(string $guestCount): int
    {
        // If it's a range like "3-4", take the first number
        if (str_contains($guestCount, '-')) {
            $parts = explode('-', $guestCount);

            return (int) trim($parts[0]);
        }

        // If it contains "+", it's "10+" or similar
        if (str_contains($guestCount, '+')) {
            return (int) str_replace('+', '', $guestCount);
        }

        return (int) $guestCount;
    }

    /**
     * Format phone number for WhatsApp API
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^\d+]/', '', $phone);

        // Remove leading + if present
        $phone = ltrim($phone, '+');

        // Handle Indonesian numbers
        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        }

        return $phone;
    }

    // ==================== Flow Data Helper Methods ====================

    /**
     * Get available dates for the next N days.
     */
    public function getAvailableDates(int $days = 30): array
    {
        $dates = [];
        $start = Carbon::tomorrow();

        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i);
            $dates[] = [
                'id' => $date->format('Y-m-d'),
                'title' => $date->locale('id')->isoFormat('ddd, DD MMM YYYY'),
            ];
        }

        return $dates;
    }

    /**
     * Get available time slots for a specific date (10:00 - 21:00, 1 hour interval).
     * Excludes already booked time slots.
     */
    public function getAvailableTimeSlots(string $date, int $userId): array
    {
        $allSlots = [];
        for ($hour = 10; $hour <= 21; $hour++) {
            $time = sprintf('%02d:00', $hour);
            $allSlots[] = [
                'id' => $time,
                'title' => "{$time} WIB",
            ];
        }

        if (! $date) {
            return $allSlots;
        }

        // Get booked times for this date
        $bookedTimes = Reservation::where('user_id', $userId)
            ->whereDate('reservation_date', $date)
            ->whereIn('status', ['pending', 'confirmed'])
            ->pluck('reservation_time')
            ->map(fn ($time) => Carbon::parse($time)->format('H:i'))
            ->toArray();

        // Mark booked slots as disabled
        return array_map(function ($slot) use ($bookedTimes) {
            if (in_array($slot['id'], $bookedTimes)) {
                $slot['enabled'] = false;
                $slot['title'] .= ' (Terisi)';
            }

            return $slot;
        }, $allSlots);
    }

    /**
     * Get event types for dropdown.
     */
    public function getEventTypes(): array
    {
        return [
            ['id' => 'regular', 'title' => 'Makan biasa'],
            ['id' => 'birthday', 'title' => 'Ulang Tahun'],
            ['id' => 'meeting', 'title' => 'Meeting/Bisnis'],
            ['id' => 'anniversary', 'title' => 'Anniversary'],
            ['id' => 'family', 'title' => 'Gathering Keluarga'],
            ['id' => 'other', 'title' => 'Lainnya'],
        ];
    }

    /**
     * Get products formatted for CheckboxGroup.
     */
    public function getProductsForCheckbox(int $userId): array
    {
        return Product::where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($product) => [
                'id' => (string) $product->id,
                'title' => "{$product->name} - {$this->formatCurrency($product->price)}",
            ])
            ->toArray();
    }

    /**
     * Get available tables for dropdown.
     */
    public function getTablesForDropdown(int $userId): array
    {
        return Table::where('user_id', $userId)
            ->where('status', Table::STATUS_AVAILABLE)
            ->orderBy('number')
            ->get()
            ->map(fn ($table) => [
                'id' => (string) $table->id,
                'title' => "Meja {$table->number} ({$table->capacity} orang)",
            ])
            ->toArray();
    }

    /**
     * Calculate total from selected product IDs.
     */
    public function calculateTotal(array $productIds, int $userId): float
    {
        if (empty($productIds)) {
            return 0;
        }

        $menuTotal = Product::where('user_id', $userId)
            ->whereIn('id', $productIds)
            ->sum('price');

        $tableFee = (float) config('services.whatsapp.flow_table_fee', 100000);

        return $menuTotal + $tableFee;
    }

    /**
     * Calculate deposit amount (50% of total).
     */
    public function calculateDeposit(float $total): float
    {
        return ceil($total * 0.5);
    }

    /**
     * Format currency in Indonesian Rupiah.
     */
    public function formatCurrency(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }

    /**
     * Get table fee from config.
     */
    public function getTableFee(): float
    {
        return (float) config('services.whatsapp.flow_table_fee', 100000);
    }

    // ==================== Config-based Flow Methods ====================

    /**
     * Create a reservation flow with config.
     */
    public function createReservationFlowWithConfig(int $userId, \App\Models\ReservationFlowConfig $config): array
    {
        $account = $this->accountService->getActiveAccount($userId);

        if (!$account) {
            throw new \Exception('No active WhatsApp account found');
        }

        $wabaId = $account->waba_id ?? $account->business_account_id;
        $flowJson = $this->buildFlowJsonFromConfig($config);
        $endpointUri = config('app.url') . '/api/whatsapp/flow/endpoint';

        $response = Http::withToken($account->access_token)
            ->post("https://graph.facebook.com/v21.0/{$wabaId}/flows", [
                'name' => $config->flow_name . '_' . Str::random(6),
                'categories' => ['APPOINTMENT_BOOKING'],
                'endpoint_uri' => $endpointUri,
                'data_api_version' => '3.0',
            ]);

        if ($response->failed()) {
            Log::error('Failed to create WhatsApp Flow with config', [
                'response' => $response->json(),
                'user_id' => $userId,
            ]);

            throw new \Exception('Failed to create flow: ' . ($response->json()['error']['message'] ?? 'Unknown error'));
        }

        $result = $response->json();
        $flowId = $result['id'] ?? null;

        // Update flow JSON
        if ($flowId) {
            $updateResponse = Http::withToken($account->access_token)
                ->post("https://graph.facebook.com/v21.0/{$flowId}/assets", [
                    'name' => 'flow.json',
                    'asset_type' => 'FLOW_JSON',
                    'file' => json_encode($flowJson),
                ]);

            if ($updateResponse->failed()) {
                Log::warning('Failed to update flow JSON', [
                    'flow_id' => $flowId,
                    'response' => $updateResponse->json(),
                ]);
            }
        }

        return $result;
    }

    /**
     * Send a reservation flow with config.
     */
    public function sendReservationFlowWithConfig(
        int $userId,
        string $phoneNumber,
        \App\Models\ReservationFlowConfig $config
    ): array {
        $account = $this->accountService->getActiveAccount($userId);

        if (!$account) {
            throw new \Exception('No active WhatsApp account found');
        }

        $flowToken = $userId . '_' . Str::uuid()->toString();

        $response = Http::withToken($account->access_token)
            ->post("https://graph.facebook.com/v21.0/{$account->phone_number_id}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $this->formatPhoneNumber($phoneNumber),
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'flow',
                    'header' => [
                        'type' => 'text',
                        'text' => $config->header_text,
                    ],
                    'body' => [
                        'text' => $config->body_text ?? 'Silakan isi form di bawah ini untuk membuat reservasi.',
                    ],
                    'footer' => [
                        'text' => $config->footer_text,
                    ],
                    'action' => [
                        'name' => 'flow',
                        'parameters' => [
                            'flow_message_version' => '3',
                            'flow_token' => $flowToken,
                            'flow_id' => $config->flow_id,
                            'flow_cta' => $config->cta_text,
                            'flow_action' => 'data_exchange',
                        ],
                    ],
                ],
            ]);

        if ($response->failed()) {
            Log::error('Failed to send reservation flow with config', [
                'response' => $response->json(),
                'user_id' => $userId,
                'phone' => $phoneNumber,
            ]);

            throw new \Exception('Failed to send flow: ' . ($response->json()['error']['message'] ?? 'Unknown error'));
        }

        return [
            'success' => true,
            'flow_token' => $flowToken,
            'message_id' => $response->json()['messages'][0]['id'] ?? null,
        ];
    }

    /**
     * Build flow JSON from config.
     * Follows Meta's WhatsApp Flow JSON specification v3.0
     * @see https://developers.facebook.com/docs/whatsapp/flows/reference/flowjson
     */
    private function buildFlowJsonFromConfig(\App\Models\ReservationFlowConfig $config): array
    {
        $screens = [];
        $hasDetails = $config->enable_menu_selection || $config->enable_table_selection;

        // Screen 1: Appointment (Date, Time, Customer Info)
        $screens[] = $this->buildAppointmentScreen($config, $hasDetails);

        // Screen 2: Details (Menu, Table, Event Type) - if enabled
        if ($hasDetails) {
            $screens[] = $this->buildDetailsScreen($config);
        }

        // Screen 3: Summary
        $screens[] = $this->buildSummaryScreen($config);

        // Screen 4: Payment - if enabled
        if ($config->enable_payment) {
            $screens[] = $this->buildPaymentScreen($config);
        }

        // Screen 5: Success (terminal screen)
        $screens[] = $this->buildSuccessScreen();

        // Build routing model - all possible transitions
        $routingModel = [];
        
        if ($hasDetails) {
            $routingModel['APPOINTMENT'] = ['DETAILS'];
            $routingModel['DETAILS'] = ['SUMMARY', 'APPOINTMENT'];
        } else {
            $routingModel['APPOINTMENT'] = ['SUMMARY'];
        }
        
        if ($config->enable_payment) {
            $routingModel['SUMMARY'] = ['PAYMENT', 'APPOINTMENT', 'DETAILS'];
            $routingModel['PAYMENT'] = ['SUCCESS', 'SUMMARY'];
        } else {
            $routingModel['SUMMARY'] = ['SUCCESS', 'APPOINTMENT', 'DETAILS'];
        }

        return [
            'version' => '3.0',
            'data_api_version' => '3.0',
            'routing_model' => $routingModel,
            'screens' => $screens,
        ];
    }

    /**
     * Build SUCCESS terminal screen
     */
    private function buildSuccessScreen(): array
    {
        return [
            'id' => 'SUCCESS',
            'title' => 'Reservasi Berhasil',
            'terminal' => true,
            'success' => true,
            'data' => [
                'confirmation_message' => [
                    'type' => 'string',
                    '__example__' => 'Reservasi Anda telah berhasil dibuat!',
                ],
                'reservation_code' => [
                    'type' => 'string',
                    '__example__' => 'RES-000001',
                ],
            ],
            'layout' => [
                'type' => 'SingleColumnLayout',
                'children' => [
                    [
                        'type' => 'TextHeading',
                        'text' => '✅ Reservasi Berhasil!',
                    ],
                    [
                        'type' => 'TextBody',
                        'text' => '${data.confirmation_message}',
                    ],
                    [
                        'type' => 'TextSubheading',
                        'text' => 'Kode Reservasi:',
                    ],
                    [
                        'type' => 'TextHeading',
                        'text' => '${data.reservation_code}',
                    ],
                    [
                        'type' => 'TextCaption',
                        'text' => 'Kami akan menghubungi Anda untuk konfirmasi. Terima kasih!',
                    ],
                ],
            ],
        ];
    }

    private function buildAppointmentScreen(\App\Models\ReservationFlowConfig $config, bool $hasDetails = true): array
    {
        // Build guest count options
        $guestOptions = [];
        for ($i = $config->min_guests; $i <= min($config->max_guests, 20); $i++) {
            $guestOptions[] = ['id' => (string) $i, 'title' => "{$i} orang"];
        }
        if ($config->max_guests > 20) {
            $guestOptions[] = ['id' => '20+', 'title' => 'Lebih dari 20 orang'];
        }

        $nextScreen = $hasDetails ? 'DETAILS' : 'SUMMARY';

        return [
            'id' => 'APPOINTMENT',
            'title' => 'Reservasi',
            'data' => [
                'dates' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'title' => ['type' => 'string'],
                        ],
                    ],
                    '__example__' => [['id' => '2026-01-15', 'title' => 'Kam, 15 Jan 2026']],
                ],
                'times' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'title' => ['type' => 'string'],
                            'enabled' => ['type' => 'boolean'],
                        ],
                    ],
                    '__example__' => [['id' => '12:00', 'title' => '12:00 WIB', 'enabled' => true]],
                ],
                'is_time_enabled' => [
                    'type' => 'boolean',
                    '__example__' => false,
                ],
                'guest_options' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'title' => ['type' => 'string'],
                        ],
                    ],
                    '__example__' => $guestOptions,
                ],
            ],
            'layout' => [
                'type' => 'SingleColumnLayout',
                'children' => [
                    [
                        'type' => 'Form',
                        'name' => 'appointment_form',
                        'children' => [
                            [
                                'type' => 'Dropdown',
                                'name' => 'reservation_date',
                                'label' => 'Tanggal Reservasi',
                                'required' => true,
                                'data-source' => '${data.dates}',
                                'on-select-action' => [
                                    'name' => 'data_exchange',
                                    'payload' => [
                                        'trigger' => 'date_selected',
                                        'reservation_date' => '${form.reservation_date}',
                                    ],
                                ],
                            ],
                            [
                                'type' => 'Dropdown',
                                'name' => 'reservation_time',
                                'label' => 'Jam Reservasi',
                                'required' => true,
                                'data-source' => '${data.times}',
                                'enabled' => '${data.is_time_enabled}',
                            ],
                            [
                                'type' => 'TextInput',
                                'name' => 'customer_name',
                                'label' => 'Nama Lengkap',
                                'required' => true,
                                'input-type' => 'text',
                            ],
                            [
                                'type' => 'TextInput',
                                'name' => 'phone',
                                'label' => 'Nomor WhatsApp',
                                'required' => true,
                                'input-type' => 'phone',
                            ],
                            [
                                'type' => 'Dropdown',
                                'name' => 'guest_count',
                                'label' => 'Jumlah Tamu',
                                'required' => true,
                                'data-source' => '${data.guest_options}',
                            ],
                            [
                                'type' => 'Footer',
                                'label' => 'Lanjut',
                                'on-click-action' => [
                                    'name' => 'navigate',
                                    'next' => [
                                        'type' => 'screen',
                                        'name' => $nextScreen,
                                    ],
                                    'payload' => [
                                        'reservation_date' => '${form.reservation_date}',
                                        'reservation_time' => '${form.reservation_time}',
                                        'customer_name' => '${form.customer_name}',
                                        'phone' => '${form.phone}',
                                        'guest_count' => '${form.guest_count}',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function buildDetailsScreen(\App\Models\ReservationFlowConfig $config): array
    {
        $formChildren = [];

        if ($config->enable_table_selection) {
            $formChildren[] = [
                'type' => 'Dropdown',
                'name' => 'table_id',
                'label' => 'Pilih Meja',
                'required' => false,
                'data-source' => '${data.tables}',
            ];
        }

        if ($config->enable_menu_selection) {
            $formChildren[] = [
                'type' => 'CheckboxGroup',
                'name' => 'selected_products',
                'label' => 'Pilih Menu (Opsional)',
                'required' => $config->require_menu_selection,
                'data-source' => '${data.products}',
            ];
        }

        if ($config->require_event_type) {
            $formChildren[] = [
                'type' => 'Dropdown',
                'name' => 'event_type',
                'label' => 'Tipe Acara',
                'required' => true,
                'data-source' => '${data.event_types}',
            ];
        }

        if ($config->require_email) {
            $formChildren[] = [
                'type' => 'TextInput',
                'name' => 'email',
                'label' => 'Email',
                'required' => true,
                'input-type' => 'email',
            ];
        }

        $formChildren[] = [
            'type' => 'TextArea',
            'name' => 'special_notes',
            'label' => 'Catatan Khusus (Opsional)',
            'required' => false,
        ];

        $formChildren[] = [
            'type' => 'Footer',
            'label' => 'Lihat Ringkasan',
            'on-click-action' => [
                'name' => 'navigate',
                'next' => [
                    'type' => 'screen',
                    'name' => 'SUMMARY',
                ],
                'payload' => [
                    'table_id' => '${form.table_id}',
                    'selected_products' => '${form.selected_products}',
                    'event_type' => '${form.event_type}',
                    'email' => '${form.email}',
                    'special_notes' => '${form.special_notes}',
                ],
            ],
        ];

        return [
            'id' => 'DETAILS',
            'title' => 'Detail Reservasi',
            'data' => [
                'tables' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'title' => ['type' => 'string'],
                        ],
                    ],
                    '__example__' => [['id' => '1', 'title' => 'Meja 1 (4 orang)']],
                ],
                'products' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'title' => ['type' => 'string'],
                        ],
                    ],
                    '__example__' => [['id' => '1', 'title' => 'Nasi Goreng - Rp25.000']],
                ],
                'event_types' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'title' => ['type' => 'string'],
                        ],
                    ],
                    '__example__' => [['id' => 'regular', 'title' => 'Makan biasa']],
                ],
            ],
            'layout' => [
                'type' => 'SingleColumnLayout',
                'children' => [
                    [
                        'type' => 'Form',
                        'name' => 'details_form',
                        'children' => $formChildren,
                    ],
                ],
            ],
        ];
    }

    private function buildSummaryScreen(\App\Models\ReservationFlowConfig $config): array
    {
        $nextScreen = $config->enable_payment ? 'PAYMENT' : 'SUCCESS';
        
        return [
            'id' => 'SUMMARY',
            'title' => 'Ringkasan',
            'data' => [
                'appointment_summary' => [
                    'type' => 'string',
                    '__example__' => '📅 Senin, 15 Jan 2026 jam 12:00 WIB\n👤 John Doe (+6281234567890)\n👥 4 tamu',
                ],
                'menu_summary' => [
                    'type' => 'string',
                    '__example__' => '🍽️ Menu yang dipilih:\n• Nasi Goreng - Rp25.000',
                ],
                'table_summary' => [
                    'type' => 'string',
                    '__example__' => '🪑 Meja 1 (4 orang)',
                ],
                'price_breakdown' => [
                    'type' => 'string',
                    '__example__' => '💰 Total: Rp125.000\nDP (50%): Rp62.500',
                ],
            ],
            'layout' => [
                'type' => 'SingleColumnLayout',
                'children' => [
                    ['type' => 'TextHeading', 'text' => '📋 Ringkasan Reservasi'],
                    ['type' => 'TextBody', 'text' => '${data.appointment_summary}'],
                    ['type' => 'TextBody', 'text' => '${data.menu_summary}'],
                    ['type' => 'TextBody', 'text' => '${data.table_summary}'],
                    ['type' => 'TextBody', 'text' => '${data.price_breakdown}'],
                    [
                        'type' => 'Footer',
                        'label' => $config->enable_payment ? 'Lanjut ke Pembayaran' : 'Konfirmasi',
                        'on-click-action' => [
                            'name' => 'navigate',
                            'next' => [
                                'type' => 'screen',
                                'name' => $nextScreen,
                            ],
                            'payload' => [],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function buildPaymentScreen(\App\Models\ReservationFlowConfig $config): array
    {
        return [
            'id' => 'PAYMENT',
            'title' => 'Pembayaran',
            'data' => [
                'payment_types' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'title' => ['type' => 'string'],
                        ],
                    ],
                    '__example__' => [
                        ['id' => 'dp', 'title' => 'DP (50%) - Rp62.500'],
                        ['id' => 'lunas', 'title' => 'Lunas - Rp125.000'],
                    ],
                ],
                'payment_methods' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'title' => ['type' => 'string'],
                        ],
                    ],
                    '__example__' => [
                        ['id' => 'qris', 'title' => 'QRIS'],
                        ['id' => 'cash', 'title' => 'Bayar di Tempat'],
                    ],
                ],
                'is_payment_method_enabled' => [
                    'type' => 'boolean',
                    '__example__' => true,
                ],
                'payment_instruction' => [
                    'type' => 'string',
                    '__example__' => 'Total pembayaran: Rp62.500',
                ],
            ],
            'layout' => [
                'type' => 'SingleColumnLayout',
                'children' => [
                    ['type' => 'TextHeading', 'text' => '💳 Pembayaran'],
                    [
                        'type' => 'Form',
                        'name' => 'payment_form',
                        'children' => [
                            [
                                'type' => 'Dropdown',
                                'name' => 'payment_type',
                                'label' => 'Tipe Pembayaran',
                                'required' => true,
                                'data-source' => '${data.payment_types}',
                            ],
                            [
                                'type' => 'Dropdown',
                                'name' => 'payment_method',
                                'label' => 'Metode Pembayaran',
                                'required' => true,
                                'data-source' => '${data.payment_methods}',
                                'enabled' => '${data.is_payment_method_enabled}',
                            ],
                            [
                                'type' => 'Footer',
                                'label' => 'Konfirmasi Pembayaran',
                                'on-click-action' => [
                                    'name' => 'navigate',
                                    'next' => [
                                        'type' => 'screen',
                                        'name' => 'SUCCESS',
                                    ],
                                    'payload' => [
                                        'payment_type' => '${form.payment_type}',
                                        'payment_method' => '${form.payment_method}',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    ['type' => 'TextBody', 'text' => '${data.payment_instruction}'],
                ],
            ],
        ];
    }

    /**
     * Get flow config for user.
     */
    public function getFlowConfig(int $userId): ?\App\Models\ReservationFlowConfig
    {
        return \App\Models\ReservationFlowConfig::where('user_id', $userId)->first();
    }
}
