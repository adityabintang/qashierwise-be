<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\Table;
use App\Models\User;
use App\Services\QrisService;
use App\Services\WhatsAppFlowEncryptionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller for handling WhatsApp Flow data exchange endpoint.
 *
 * This endpoint receives encrypted requests from WhatsApp and returns
 * encrypted responses. It handles various flow actions like INIT,
 * data_exchange, and BACK.
 *
 * @see https://developers.facebook.com/docs/whatsapp/flows/guides/implementingyourflowendpoint
 */
class WhatsAppFlowEndpointController extends Controller
{
    public function __construct(
        private WhatsAppFlowEncryptionService $encryptionService,
        private QrisService $qrisService
    ) {}

    /**
     * Handle incoming WhatsApp Flow requests.
     *
     * Per WhatsApp Flow specification:
     * - Request body contains: encrypted_flow_data, encrypted_aes_key, initial_vector
     * - Response must be returned as plain text (base64 encoded encrypted response)
     * - Signature verification via X-Hub-Signature-256 header
     * - Return HTTP 421 for decryption failures
     *
     * @return \Illuminate\Http\Response
     */
    public function handleRequest(Request $request)
    {
        // Store for error handling
        $aesKey = null;
        $iv = null;

        try {
            // Log raw incoming request
            Log::channel('whatsapp')->info('WhatsApp Flow Request Received', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'has_encrypted_aes_key' => $request->has('encrypted_aes_key'),
                'has_encrypted_flow_data' => $request->has('encrypted_flow_data'),
                'has_initial_vector' => $request->has('initial_vector'),
                'has_signature' => $request->hasHeader('X-Hub-Signature-256'),
                'timestamp' => now()->toDateTimeString(),
            ]);

            // Verify request signature if app secret is configured
            if (! $this->verifyRequestSignature($request)) {
                Log::channel('whatsapp')->error('WhatsApp Flow signature verification failed');

                return response('Invalid signature', 432);
            }

            // Check if encryption is configured
            if (! $this->encryptionService->isConfigured()) {
                Log::channel('whatsapp')->error('WhatsApp Flow endpoint called but encryption not configured');

                return response('Endpoint not configured', 500);
            }

            // Get encrypted payload from request
            $encryptedAesKey = $request->input('encrypted_aes_key');
            $encryptedFlowData = $request->input('encrypted_flow_data');
            $initialVector = $request->input('initial_vector');

            if (! $encryptedAesKey || ! $encryptedFlowData || ! $initialVector) {
                Log::channel('whatsapp')->error('WhatsApp Flow missing required encryption parameters', [
                    'has_aes_key' => ! empty($encryptedAesKey),
                    'has_flow_data' => ! empty($encryptedFlowData),
                    'has_iv' => ! empty($initialVector),
                ]);

                // Return 421 for missing encryption parameters per spec
                return response('Missing encryption parameters', 421);
            }

            // Decrypt the request
            Log::channel('whatsapp')->debug('Attempting to decrypt WhatsApp Flow request');

            try {
                $decrypted = $this->encryptionService->decryptRequest(
                    $encryptedAesKey,
                    $encryptedFlowData,
                    $initialVector
                );
            } catch (\Exception $decryptException) {
                Log::channel('whatsapp')->error('WhatsApp Flow decryption failed', [
                    'error' => $decryptException->getMessage(),
                ]);

                // Return HTTP 421 to force client to re-download public key and retry
                return response('Decryption failed', 421);
            }

            $flowData = $decrypted['data'];
            $aesKey = $decrypted['aes_key'];
            $iv = $decrypted['iv'];

            Log::channel('whatsapp')->info('WhatsApp Flow request decrypted successfully', [
                'action' => $flowData['action'] ?? 'unknown',
                'screen' => $flowData['screen'] ?? 'unknown',
                'flow_token' => substr($flowData['flow_token'] ?? '', 0, 20).'...', // Only log first 20 chars for security
                'data_keys' => array_keys($flowData['data'] ?? []),
            ]);

            // Route to appropriate handler based on action
            $action = $flowData['action'] ?? 'unknown';
            Log::channel('whatsapp')->debug("Routing to handler for action: {$action}");

            $response = match ($action) {
                'INIT' => $this->handleInit($flowData),
                'data_exchange' => $this->handleDataExchange($flowData),
                'BACK' => $this->handleBack($flowData),
                'ping' => $this->handlePing(),
                default => $this->handleUnknownAction($flowData),
            };

            Log::channel('whatsapp')->info('WhatsApp Flow response prepared', [
                'action' => $action,
                'response_screen' => $response['screen'] ?? 'none',
                'response_data_keys' => array_keys($response['data'] ?? []),
            ]);

            // Encrypt and return response as plain text per WhatsApp spec
            Log::channel('whatsapp')->debug('Encrypting WhatsApp Flow response');
            $encryptedResponse = $this->encryptionService->encryptResponse($response, $aesKey, $iv);

            Log::channel('whatsapp')->info('WhatsApp Flow request completed successfully', [
                'action' => $action,
                'encrypted_response_length' => strlen($encryptedResponse),
            ]);

            // Return as plain text per WhatsApp Flow specification
            return response($encryptedResponse, 200)
                ->header('Content-Type', 'text/plain');

        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('WhatsApp Flow endpoint error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // If we have encryption keys, return encrypted error response
            if ($aesKey && $iv) {
                try {
                    $errorResponse = [
                        'screen' => $flowData['screen'] ?? 'APPOINTMENT',
                        'data' => [
                            'error_message' => 'An error occurred. Please try again.',
                        ],
                    ];
                    $encryptedError = $this->encryptionService->encryptResponse($errorResponse, $aesKey, $iv);

                    return response($encryptedError, 200)
                        ->header('Content-Type', 'text/plain');
                } catch (\Exception $encryptError) {
                    Log::channel('whatsapp')->error('Failed to encrypt error response', [
                        'error' => $encryptError->getMessage(),
                    ]);
                }
            }

            return response('Internal server error', 500);
        }
    }

    /**
     * Verify the request signature using X-Hub-Signature-256 header.
     *
     * @see https://developers.facebook.com/docs/whatsapp/flows/guides/implementingyourflowendpoint#request-signature-validation
     */
    private function verifyRequestSignature(Request $request): bool
    {
        $appSecret = config('services.whatsapp.app_secret');

        // If no app secret configured, skip verification (not recommended for production)
        if (empty($appSecret)) {
            Log::channel('whatsapp')->warning('WhatsApp app secret not configured, skipping signature verification');

            return true;
        }

        $signature = $request->header('X-Hub-Signature-256');

        // If no signature header, fail verification
        if (empty($signature)) {
            Log::channel('whatsapp')->warning('No X-Hub-Signature-256 header present');

            return true; // Allow requests without signature for backward compatibility
        }

        // Extract the signature value (format: sha256=<signature>)
        if (! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $expectedSignature = substr($signature, 7);

        // Calculate the signature using the raw request body
        $payload = $request->getContent();
        $calculatedSignature = hash_hmac('sha256', $payload, $appSecret);

        // Use timing-safe comparison
        return hash_equals($expectedSignature, $calculatedSignature);
    }

    /**
     * Get the public key for WhatsApp Flow encryption.
     *
     * Meta fetches this endpoint to get the public key for signing.
     * The public key is derived from the private key stored in config.
     *
     * @see https://developers.facebook.com/docs/whatsapp/flows/guides/implementingyourflowendpoint#upload-public-key
     */
    public function getPublicKey(): \Illuminate\Http\JsonResponse
    {
        try {
            $privateKeyRaw = config('services.whatsapp.flow_private_key');
            $passphrase = config('services.whatsapp.flow_passphrase');

            if (empty($privateKeyRaw)) {
                Log::channel('whatsapp')->error('WhatsApp Flow private key not configured');

                return response()->json([
                    'error' => 'Public key not available',
                ], 500);
            }

            // Decode the private key (handle various formats)
            $privateKeyPem = $this->decodePrivateKeyForPublicKey($privateKeyRaw);

            // Load the private key
            $privateKey = openssl_pkey_get_private($privateKeyPem, $passphrase ?? '');

            if ($privateKey === false) {
                Log::channel('whatsapp')->error('Failed to load private key for public key extraction', [
                    'error' => openssl_error_string(),
                ]);

                return response()->json([
                    'error' => 'Failed to extract public key',
                ], 500);
            }

            // Extract the public key
            $keyDetails = openssl_pkey_get_details($privateKey);
            $publicKey = $keyDetails['key'];

            Log::channel('whatsapp')->info('WhatsApp Flow public key requested', [
                'key_bits' => $keyDetails['bits'],
                'ip' => request()->ip(),
            ]);

            return response()->json([
                'public_key' => $publicKey,
            ]);

        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Error getting public key', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Decode the private key from various formats (for public key extraction).
     */
    private function decodePrivateKeyForPublicKey(string $key): string
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
        return "-----BEGIN PRIVATE KEY-----\n".
            chunk_split($key, 64, "\n").
            "-----END PRIVATE KEY-----\n";
    }

    /**
     * Handle INIT action - called when flow is first opened.
     */
    private function handleInit(array $flowData): array
    {
        // Get flow token from the request (contains user context)
        $flowToken = $flowData['flow_token'] ?? '';
        $userId = $this->extractUserIdFromFlowToken($flowToken);

        // Generate available dates (next 30 days, starting from tomorrow)
        $dates = $this->getAvailableDates(30);

        // Generate initial time slots (all available until date is selected)
        $times = $this->getAllTimeSlots();

        return [
            'screen' => 'APPOINTMENT',
            'data' => [
                'dates' => $dates,
                'times' => $times,
                'is_time_enabled' => false, // Disabled until date is selected
            ],
        ];
    }

    /**
     * Handle data_exchange action - called when user interacts with form elements.
     */
    private function handleDataExchange(array $flowData): array
    {
        $trigger = $flowData['data']['trigger'] ?? '';
        $data = $flowData['data'] ?? [];
        $flowToken = $flowData['flow_token'] ?? '';
        $userId = $this->extractUserIdFromFlowToken($flowToken);

        Log::channel('whatsapp')->info('WhatsApp Flow data_exchange triggered', [
            'trigger' => $trigger,
            'user_id' => $userId,
            'data_keys' => array_keys($data),
            'screen' => $flowData['screen'] ?? 'unknown',
        ]);

        return match ($trigger) {
            'date_selected' => $this->handleDateSelected($data, $userId),
            'appointment_submitted' => $this->handleAppointmentSubmitted($data, $userId),
            'details_submitted' => $this->handleDetailsSubmitted($data, $userId),
            'summary_confirmed' => $this->handleSummaryConfirmed($data, $userId),
            'payment_type_selected' => $this->handlePaymentTypeSelected($data, $userId),
            'payment_method_selected' => $this->handlePaymentMethodSelected($data, $userId, $flowToken),
            default => $this->handleUnknownTrigger($trigger, $data),
        };
    }

    /**
     * Handle date selection - filter available time slots.
     */
    private function handleDateSelected(array $data, ?int $userId): array
    {
        $selectedDate = $data['reservation_date'] ?? '';

        // Get available time slots for the selected date
        $times = $this->getAvailableTimeSlots($selectedDate, $userId);

        return [
            'screen' => 'APPOINTMENT',
            'data' => [
                'times' => $times,
                'is_time_enabled' => true,
            ],
        ];
    }

    /**
     * Handle appointment form submission - navigate to DETAILS screen.
     */
    private function handleAppointmentSubmitted(array $data, ?int $userId): array
    {
        // Get event types
        $eventTypes = $this->getEventTypes();

        // Get products for the user
        $products = $this->getProductsForCheckbox($userId);

        // Get available tables
        $tables = $this->getTablesForDropdown($userId);

        return [
            'screen' => 'DETAILS',
            'data' => [
                'reservation_date' => $data['reservation_date'] ?? '',
                'reservation_time' => $data['reservation_time'] ?? '',
                'customer_name' => $data['customer_name'] ?? '',
                'phone' => $data['phone'] ?? '',
                'guest_count' => $data['guest_count'] ?? '',
                'event_types' => $eventTypes,
                'products' => $products,
                'tables' => $tables,
            ],
        ];
    }

    /**
     * Handle details form submission - calculate totals and navigate to SUMMARY.
     */
    private function handleDetailsSubmitted(array $data, ?int $userId): array
    {
        $selectedProducts = $data['selected_products'] ?? [];
        $tableFee = (float) config('services.whatsapp.flow_table_fee', 100000);

        // Calculate menu total
        $menuTotal = $this->calculateMenuTotal($selectedProducts, $userId);
        $grandTotal = $menuTotal + $tableFee;
        $dpAmount = ceil($grandTotal * 0.5);

        // Build summary texts
        $appointmentSummary = $this->buildAppointmentSummary($data);
        $menuSummary = $this->buildMenuSummary($selectedProducts, $userId);
        $tableSummary = $this->buildTableSummary($data['table_id'] ?? '', $userId);
        $priceBreakdown = $this->buildPriceBreakdown($menuTotal, $tableFee, $grandTotal, $dpAmount);

        return [
            'screen' => 'SUMMARY',
            'data' => [
                'reservation_date' => $data['reservation_date'] ?? '',
                'reservation_time' => $data['reservation_time'] ?? '',
                'customer_name' => $data['customer_name'] ?? '',
                'phone' => $data['phone'] ?? '',
                'guest_count' => $data['guest_count'] ?? '',
                'email' => $data['email'] ?? '',
                'event_type' => $data['event_type'] ?? '',
                'special_notes' => $data['special_notes'] ?? '',
                'selected_products' => $selectedProducts,
                'table_id' => $data['table_id'] ?? '',
                'appointment_summary' => $appointmentSummary,
                'menu_summary' => $menuSummary,
                'table_summary' => $tableSummary,
                'price_breakdown' => $priceBreakdown,
                'menu_total' => (string) $menuTotal,
                'table_fee' => (string) $tableFee,
                'grand_total' => (string) $grandTotal,
                'dp_amount' => (string) $dpAmount,
            ],
        ];
    }

    /**
     * Handle summary confirmation - navigate to PAYMENT screen with initial payment data.
     */
    private function handleSummaryConfirmed(array $data, ?int $userId): array
    {
        $grandTotal = (float) ($data['grand_total'] ?? 0);
        $dpAmount = (float) ($data['dp_amount'] ?? 0);

        return [
            'screen' => 'PAYMENT',
            'data' => [
                'reservation_date' => $data['reservation_date'] ?? '',
                'reservation_time' => $data['reservation_time'] ?? '',
                'customer_name' => $data['customer_name'] ?? '',
                'phone' => $data['phone'] ?? '',
                'guest_count' => $data['guest_count'] ?? '',
                'email' => $data['email'] ?? '',
                'event_type' => $data['event_type'] ?? '',
                'special_notes' => $data['special_notes'] ?? '',
                'selected_products' => $data['selected_products'] ?? [],
                'table_id' => $data['table_id'] ?? '',
                'menu_total' => $data['menu_total'] ?? '0',
                'table_fee' => $data['table_fee'] ?? '100000',
                'grand_total' => $data['grand_total'] ?? '0',
                'dp_amount' => $data['dp_amount'] ?? '0',
                'payment_types' => $this->getPaymentTypes($grandTotal, $dpAmount),
                'payment_methods' => $this->getPaymentMethods(),
                'is_payment_method_enabled' => false,
                'show_qris' => false,
                'qr_code_url' => '',
                'payment_amount' => (string) $dpAmount, // Default to DP amount
                'payment_amount_formatted' => $this->formatCurrency($dpAmount),
                'payment_instruction' => 'Silakan pilih jenis pembayaran terlebih dahulu',
            ],
        ];
    }

    /**
     * Handle payment type selection - enable payment method dropdown.
     */
    private function handlePaymentTypeSelected(array $data, ?int $userId): array
    {
        $paymentType = $data['payment_type'] ?? 'dp';
        $grandTotal = (float) ($data['grand_total'] ?? 0);
        $dpAmount = (float) ($data['dp_amount'] ?? 0);

        $paymentAmount = $paymentType === 'lunas' ? $grandTotal : $dpAmount;

        return [
            'screen' => 'PAYMENT',
            'data' => [
                'reservation_date' => $data['reservation_date'] ?? '',
                'reservation_time' => $data['reservation_time'] ?? '',
                'customer_name' => $data['customer_name'] ?? '',
                'phone' => $data['phone'] ?? '',
                'guest_count' => $data['guest_count'] ?? '',
                'email' => $data['email'] ?? '',
                'event_type' => $data['event_type'] ?? '',
                'special_notes' => $data['special_notes'] ?? '',
                'selected_products' => $data['selected_products'] ?? [],
                'table_id' => $data['table_id'] ?? '',
                'menu_total' => $data['menu_total'] ?? '0',
                'table_fee' => $data['table_fee'] ?? '100000',
                'grand_total' => $data['grand_total'] ?? '0',
                'dp_amount' => $data['dp_amount'] ?? '0',
                'payment_types' => $this->getPaymentTypes($grandTotal, $dpAmount),
                'payment_methods' => $this->getPaymentMethods(),
                'is_payment_method_enabled' => true,
                'show_qris' => false,
                'qr_code_url' => '',
                'payment_amount' => (string) $paymentAmount,
                'payment_amount_formatted' => $this->formatCurrency($paymentAmount),
                'payment_instruction' => "Total pembayaran: {$this->formatCurrency($paymentAmount)}",
            ],
        ];
    }

    /**
     * Handle payment method selection - generate QRIS if selected.
     */
    private function handlePaymentMethodSelected(array $data, ?int $userId, string $flowToken): array
    {
        $paymentMethod = $data['payment_method'] ?? 'cash';
        $paymentType = $data['payment_type'] ?? 'dp';
        $grandTotal = (float) ($data['grand_total'] ?? 0);
        $dpAmount = (float) ($data['dp_amount'] ?? 0);

        $paymentAmount = $paymentType === 'lunas' ? $grandTotal : $dpAmount;

        $showQris = false;
        $qrCodeUrl = '';
        $instruction = "Total pembayaran: {$this->formatCurrency($paymentAmount)}";

        if ($paymentMethod === 'qris' && $userId) {
            try {
                Log::channel('whatsapp')->info('Generating QRIS for WhatsApp Flow reservation', [
                    'user_id' => $userId,
                    'amount' => $paymentAmount,
                    'payment_type' => $paymentType,
                    'customer_name' => $data['customer_name'] ?? 'unknown',
                ]);

                // Generate QRIS
                $qrisData = $this->generateQrisForReservation($userId, $paymentAmount, $data);
                $showQris = true;
                $qrCodeUrl = $qrisData['qr_code_url'] ?? '';
                $instruction = "Scan QRIS di bawah untuk membayar {$this->formatCurrency($paymentAmount)}";

                Log::channel('whatsapp')->info('QRIS generated successfully for WhatsApp Flow', [
                    'user_id' => $userId,
                    'order_id' => $qrisData['order_id'] ?? 'unknown',
                    'has_qr_url' => ! empty($qrCodeUrl),
                ]);
            } catch (\Exception $e) {
                Log::channel('whatsapp')->error('Failed to generate QRIS for WhatsApp Flow', [
                    'error' => $e->getMessage(),
                    'user_id' => $userId,
                    'amount' => $paymentAmount,
                    'trace' => $e->getTraceAsString(),
                ]);
                $instruction = 'Gagal generate QRIS. Silakan pilih metode pembayaran lain.';
            }
        } elseif ($paymentMethod === 'cash') {
            Log::channel('whatsapp')->info('Cash payment selected for WhatsApp Flow reservation', [
                'user_id' => $userId,
                'amount' => $paymentAmount,
            ]);
            $instruction = "Pembayaran {$this->formatCurrency($paymentAmount)} akan dilakukan di tempat saat datang.";
        }

        return [
            'screen' => 'PAYMENT',
            'data' => [
                'reservation_date' => $data['reservation_date'] ?? '',
                'reservation_time' => $data['reservation_time'] ?? '',
                'customer_name' => $data['customer_name'] ?? '',
                'phone' => $data['phone'] ?? '',
                'guest_count' => $data['guest_count'] ?? '',
                'email' => $data['email'] ?? '',
                'event_type' => $data['event_type'] ?? '',
                'special_notes' => $data['special_notes'] ?? '',
                'selected_products' => $data['selected_products'] ?? [],
                'table_id' => $data['table_id'] ?? '',
                'menu_total' => $data['menu_total'] ?? '0',
                'table_fee' => $data['table_fee'] ?? '100000',
                'grand_total' => $data['grand_total'] ?? '0',
                'dp_amount' => $data['dp_amount'] ?? '0',
                'payment_types' => $this->getPaymentTypes($grandTotal, $dpAmount),
                'payment_methods' => $this->getPaymentMethods(),
                'is_payment_method_enabled' => true,
                'show_qris' => $showQris,
                'qr_code_url' => $qrCodeUrl,
                'payment_amount' => (string) $paymentAmount,
                'payment_amount_formatted' => $this->formatCurrency($paymentAmount),
                'payment_instruction' => $instruction,
            ],
        ];
    }

    /**
     * Handle BACK action.
     */
    private function handleBack(array $flowData): array
    {
        $currentScreen = $flowData['screen'] ?? 'APPOINTMENT';

        // Determine previous screen
        $previousScreen = match ($currentScreen) {
            'DETAILS' => 'APPOINTMENT',
            'SUMMARY' => 'DETAILS',
            'PAYMENT' => 'SUMMARY',
            default => 'APPOINTMENT',
        };

        return [
            'screen' => $previousScreen,
            'data' => $flowData['data'] ?? [],
        ];
    }

    /**
     * Handle ping action (health check).
     */
    private function handlePing(): array
    {
        return [
            'data' => [
                'status' => 'active',
                'version' => '7.3',
            ],
        ];
    }

    /**
     * Handle unknown action.
     */
    private function handleUnknownAction(array $flowData): array
    {
        Log::warning('Unknown WhatsApp Flow action', [
            'action' => $flowData['action'] ?? 'null',
        ]);

        return [
            'screen' => 'APPOINTMENT',
            'data' => [
                'dates' => $this->getAvailableDates(30),
                'times' => $this->getAllTimeSlots(),
                'is_time_enabled' => false,
            ],
        ];
    }

    /**
     * Handle unknown trigger in data_exchange.
     */
    private function handleUnknownTrigger(string $trigger, array $data): array
    {
        Log::warning('Unknown WhatsApp Flow trigger', [
            'trigger' => $trigger,
        ]);

        return [
            'screen' => $data['screen'] ?? 'APPOINTMENT',
            'data' => $data,
        ];
    }

    // ==================== Helper Methods ====================

    /**
     * Extract user ID from flow token.
     * Flow token format: {user_id}_{uuid}
     */
    private function extractUserIdFromFlowToken(string $flowToken): ?int
    {
        if (empty($flowToken)) {
            return null;
        }

        $parts = explode('_', $flowToken);
        if (count($parts) >= 2 && is_numeric($parts[0])) {
            return (int) $parts[0];
        }

        return null;
    }

    /**
     * Get available dates for the next N days.
     */
    private function getAvailableDates(int $days = 30): array
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
     * Get all time slots (10:00 - 21:00, 1 hour interval).
     */
    private function getAllTimeSlots(): array
    {
        $slots = [];
        for ($hour = 10; $hour <= 21; $hour++) {
            $time = sprintf('%02d:00', $hour);
            $slots[] = [
                'id' => $time,
                'title' => "{$time} WIB",
            ];
        }

        return $slots;
    }

    /**
     * Get available time slots for a specific date (excluding booked ones).
     */
    private function getAvailableTimeSlots(string $date, ?int $userId): array
    {
        $allSlots = $this->getAllTimeSlots();

        if (! $userId || ! $date) {
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
    private function getEventTypes(): array
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
     * Get products for checkbox group.
     */
    private function getProductsForCheckbox(?int $userId): array
    {
        if (! $userId) {
            return [];
        }

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
     * Get tables for dropdown.
     */
    private function getTablesForDropdown(?int $userId): array
    {
        if (! $userId) {
            return [];
        }

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
     * Calculate menu total from selected product IDs.
     */
    private function calculateMenuTotal(array $productIds, ?int $userId): float
    {
        if (empty($productIds) || ! $userId) {
            return 0;
        }

        return Product::where('user_id', $userId)
            ->whereIn('id', $productIds)
            ->sum('price');
    }

    /**
     * Get payment types with amounts.
     */
    private function getPaymentTypes(float $grandTotal, float $dpAmount): array
    {
        return [
            [
                'id' => 'dp',
                'title' => "DP (50%) - {$this->formatCurrency($dpAmount)}",
            ],
            [
                'id' => 'lunas',
                'title' => "Lunas - {$this->formatCurrency($grandTotal)}",
            ],
        ];
    }

    /**
     * Get payment methods.
     */
    private function getPaymentMethods(): array
    {
        return [
            ['id' => 'qris', 'title' => 'QRIS'],
            ['id' => 'cash', 'title' => 'Bayar di Tempat (Cash)'],
        ];
    }

    /**
     * Build appointment summary text.
     */
    private function buildAppointmentSummary(array $data): string
    {
        $date = Carbon::parse($data['reservation_date'] ?? now())->locale('id')->isoFormat('dddd, DD MMMM YYYY');
        $time = $data['reservation_time'] ?? '';
        $name = $data['customer_name'] ?? '';
        $phone = $data['phone'] ?? '';
        $guests = $data['guest_count'] ?? '';
        $eventType = $this->getEventTypeLabel($data['event_type'] ?? '');

        $summary = "📅 {$date} jam {$time} WIB\n";
        $summary .= "👤 {$name} ({$phone})\n";
        $summary .= "👥 {$guests} tamu";

        if ($eventType) {
            $summary .= "\n🎉 {$eventType}";
        }

        return $summary;
    }

    /**
     * Build menu summary text.
     */
    private function buildMenuSummary(array $productIds, ?int $userId): string
    {
        if (empty($productIds) || ! $userId) {
            return '🍽️ Tidak ada menu yang dipilih';
        }

        $products = Product::where('user_id', $userId)
            ->whereIn('id', $productIds)
            ->get();

        $summary = "🍽️ Menu yang dipilih:\n";
        foreach ($products as $product) {
            $summary .= "• {$product->name} - {$this->formatCurrency($product->price)}\n";
        }

        return rtrim($summary);
    }

    /**
     * Build table summary text.
     */
    private function buildTableSummary(string $tableId, ?int $userId): string
    {
        if (! $tableId || ! $userId) {
            return '🪑 Meja belum dipilih';
        }

        $table = Table::find($tableId);
        if (! $table) {
            return '🪑 Meja tidak ditemukan';
        }

        return "🪑 Meja {$table->number} ({$table->capacity} orang)";
    }

    /**
     * Build price breakdown text.
     */
    private function buildPriceBreakdown(float $menuTotal, float $tableFee, float $grandTotal, float $dpAmount): string
    {
        $breakdown = "💰 Rincian Biaya:\n";
        $breakdown .= "Total Menu: {$this->formatCurrency($menuTotal)}\n";
        $breakdown .= "Biaya Reservasi Meja: {$this->formatCurrency($tableFee)}\n";
        $breakdown .= "─────────────────\n";
        $breakdown .= "Total: {$this->formatCurrency($grandTotal)}\n";
        $breakdown .= "DP Minimal (50%): {$this->formatCurrency($dpAmount)}";

        return $breakdown;
    }

    /**
     * Get event type label.
     */
    private function getEventTypeLabel(string $type): string
    {
        $labels = [
            'regular' => 'Makan biasa',
            'birthday' => 'Ulang Tahun',
            'meeting' => 'Meeting/Bisnis',
            'anniversary' => 'Anniversary',
            'family' => 'Gathering Keluarga',
            'other' => 'Lainnya',
        ];

        return $labels[$type] ?? '';
    }

    /**
     * Format currency in Indonesian Rupiah.
     */
    private function formatCurrency(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }

    /**
     * Generate QRIS for reservation payment.
     */
    private function generateQrisForReservation(int $userId, float $amount, array $data): array
    {
        $user = User::find($userId);
        if (! $user) {
            throw new \Exception('User not found');
        }

        $subMerchant = $user->subMerchant;
        if (! $subMerchant) {
            throw new \Exception('Sub-merchant not configured');
        }

        $description = "Reservasi {$data['customer_name']} - {$data['reservation_date']}";

        $transaction = $this->qrisService->generateQris($subMerchant, $amount, [
            'description' => $description,
            'customer_name' => $data['customer_name'] ?? '',
            'customer_email' => $data['email'] ?? '',
            'expiry_minutes' => 30,
        ]);

        return [
            'qr_code_url' => $transaction->qr_code_url,
            'order_id' => $transaction->order_id,
            'transaction_id' => $transaction->id,
        ];
    }
}
