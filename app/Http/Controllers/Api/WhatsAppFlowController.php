<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\ReservationConfig;
use App\Models\Table;
use App\Models\WhatsAppContact;
use App\Services\WhatsAppFlowEncryptionService;
use App\Services\WhatsAppFlowService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WhatsAppFlowController extends Controller
{
    public function __construct(
        protected WhatsAppFlowService $flowService,
        protected WhatsAppFlowEncryptionService $encryptionService,
    ) {}

    /**
     * List all WhatsApp Flows for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $flows = $this->flowService->listFlows($request->user()->id);

            return response()->json(['data' => $flows]);
        } catch (\Exception $e) {
            Log::error('Failed to list flows', ['error' => $e->getMessage(), 'user_id' => $request->user()->id]);

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create a new reservation WhatsApp Flow.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $result = $this->flowService->createReservationFlow($request->user()->id);

            return response()->json($result, 201);
        } catch (\Exception $e) {
            Log::error('Failed to create flow', ['error' => $e->getMessage(), 'user_id' => $request->user()->id]);

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get details of a specific flow.
     */
    public function show(Request $request, string $flowId): JsonResponse
    {
        try {
            $flow = $this->flowService->getFlow($request->user()->id, $flowId);

            if (! $flow) {
                return response()->json(['message' => 'Flow not found'], 404);
            }

            return response()->json($flow);
        } catch (\Exception $e) {
            Log::error('Failed to get flow', ['error' => $e->getMessage(), 'flow_id' => $flowId]);

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Publish a flow (set endpoint + upload key + publish).
     */
    public function publish(Request $request, string $flowId): JsonResponse
    {
        try {
            $success = $this->flowService->publishFlow($request->user()->id, $flowId);

            return response()->json(['success' => $success, 'flow_id' => $flowId]);
        } catch (\Exception $e) {
            Log::error('Failed to publish flow', ['error' => $e->getMessage(), 'flow_id' => $flowId]);

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a flow.
     */
    public function destroy(Request $request, string $flowId): JsonResponse
    {
        try {
            $success = $this->flowService->deleteFlow($request->user()->id, $flowId);

            return response()->json(['success' => $success]);
        } catch (\Exception $e) {
            Log::error('Failed to delete flow', ['error' => $e->getMessage(), 'flow_id' => $flowId]);

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Send a reservation flow message to a customer's WhatsApp.
     */
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
            'flow_id' => 'nullable|string',
            'flow_mode' => 'nullable|string|in:published,draft',
            'flow_cta' => 'nullable|string|max:20',
        ]);

        try {
            $userId = $request->user()->id;
            $config = $this->flowService->getFlowConfig($userId);

            if ($config && $config->flow_id) {
                $result = $this->flowService->sendReservationFlowWithConfig(
                    userId: $userId,
                    phoneNumber: $request->input('phone'),
                    config: $config,
                );
            } else {
                $flowId = $request->input('flow_id');

                if (empty($flowId)) {
                    return response()->json(['message' => 'flow_id wajib diisi'], 422);
                }

                $result = $this->flowService->sendReservationFlow(
                    userId: $userId,
                    phoneNumber: $request->input('phone'),
                    flowId: $flowId,
                    flowMode: $request->input('flow_mode', 'published'),
                    flowCta: $request->input('flow_cta', 'Buat Reservasi'),
                    flowAction: 'data_exchange',
                );
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to send flow', ['error' => $e->getMessage(), 'user_id' => $request->user()->id]);

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * WhatsApp Flow data exchange endpoint (public — called by WhatsApp servers).
     *
     * Handles: ping (INIT), data_exchange (per screen trigger), complete (final submission).
     */
    public function endpoint(Request $request): Response
    {
        // Health check from WhatsApp
        if ($request->isMethod('get')) {
            return response('OK', 200);
        }

        $encryptedAesKey = $request->input('encrypted_aes_key');
        $encryptedFlowData = $request->input('encrypted_flow_data');
        $initialVector = $request->input('initial_vector');

        if (! $encryptedAesKey || ! $encryptedFlowData || ! $initialVector) {
            Log::warning('Flow endpoint: missing encryption parameters');

            return response('Bad Request', 400);
        }

        try {
            $decrypted = $this->encryptionService->decryptRequest(
                $encryptedAesKey,
                $encryptedFlowData,
                $initialVector
            );
        } catch (\Exception $e) {
            Log::error('Flow endpoint: decryption failed', ['error' => $e->getMessage()]);

            return response('Decryption Error', 421);
        }

        $flowData = $decrypted['data'];
        $aesKey = $decrypted['aes_key'];
        $iv = $decrypted['iv'];

        $action = $flowData['action'] ?? 'ping';
        $flowToken = $flowData['flow_token'] ?? '';
        $screenData = $flowData['data'] ?? [];
        $screen = $flowData['screen'] ?? null;

        Log::info('Flow endpoint received', [
            'action' => $action,
            'screen' => $screen,
            'flow_token' => $flowToken,
        ]);

        try {
            $responseData = $this->handleFlowAction($action, $screen, $screenData, $flowToken);
        } catch (\Exception $e) {
            Log::error('Flow endpoint: action handling failed', [
                'action' => $action,
                'screen' => $screen,
                'error' => $e->getMessage(),
            ]);
            $responseData = $this->buildErrorScreen('Terjadi kesalahan. Silakan coba lagi.');
        }

        try {
            $encryptedResponse = $this->encryptionService->encryptResponse($responseData, $aesKey, $iv);

            return response($encryptedResponse, 200, ['Content-Type' => 'text/plain']);
        } catch (\Exception $e) {
            Log::error('Flow endpoint: response encryption failed', ['error' => $e->getMessage()]);

            return response('Encryption Error', 500);
        }
    }

    // ==================== Private Screen Handlers ====================

    /**
     * Route the flow action to the correct screen handler.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function handleFlowAction(string $action, ?string $screen, array $data, string $flowToken): array
    {
        $userId = $this->extractUserIdFromToken($flowToken);

        if ($action === 'ping') {
            return $this->buildHealthCheckResponse();
        }

        if ($action === 'complete') {
            return $this->handleCompleteAction($userId, $flowToken, $data);
        }

        if ($action === 'data_exchange') {
            $trigger = $data['trigger'] ?? '';

            return match ($trigger) {
                'date_selected' => $this->handleDateSelected($userId, $data),
                'welcome_submitted' => $this->buildDetailsScreenData($userId, $data, $flowToken),
                'details_submitted' => $this->buildSummaryScreenData($userId, $data, $flowToken),
                'summary_confirmed' => $this->buildPaymentScreenData($userId, $data, $flowToken),
                'payment_type_selected' => $this->handlePaymentTypeSelected($userId, $data, $flowToken),
                'payment_confirmed' => $this->handlePaymentConfirmed($userId, $data, $flowToken),
                default => $this->buildWelcomeScreenData($userId),
            };
        }

        if ($action === 'navigate') {
            return match ($screen) {
                'WELCOME_SCREEN' => $this->buildWelcomeScreenData($userId),
                'DETAILS' => $this->buildDetailsScreenData($userId, $data),
                'SUMMARY' => $this->buildSummaryScreenData($userId, $data),
                'PAYMENT' => $this->buildPaymentScreenData($userId, $data),
                default => $this->buildWelcomeScreenData($userId),
            };
        }

        return $this->buildWelcomeScreenData($userId);
    }

    /**
     * Build the Meta-compliant health check response for ping requests.
     *
     * @return array<string, mixed>
     */
    private function buildHealthCheckResponse(): array
    {
        return [
            'data' => [
                'status' => 'active',
            ],
        ];
    }

    /**
     * Build WELCOME_SCREEN data: dates, times, guest_options.
     *
     * @return array<string, mixed>
     */
    private function buildWelcomeScreenData(int $userId): array
    {
        $config = $this->getReservationConfig($userId);
        $dates = $this->buildDatesData($config);
        $times = $this->buildTimeSlotsData($config, null);
        $guestOptions = $this->buildGuestOptionsData($config);

        return [
            'screen' => 'WELCOME_SCREEN',
            'data' => [
                'dates' => $dates,
                'times' => $times,
                'is_time_enabled' => $config ? ! empty($config->available_slots) : false,
                'guest_options' => $guestOptions,
            ],
        ];
    }

    /**
     * When a date is selected, return updated time slots for the date.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function handleDateSelected(int $userId, array $data): array
    {
        $config = $this->getReservationConfig($userId);
        $selectedDate = $data['reservation_date'] ?? null;
        $times = $this->buildTimeSlotsData($config, $selectedDate);

        return [
            'screen' => 'WELCOME_SCREEN',
            'data' => [
                'times' => $times,
                'is_time_enabled' => true,
            ],
        ];
    }

    /**
     * Build DETAILS screen data: tables, products, event_types.
     *
     * Caches the WELCOME form data (customer info, date, time, guest count) so it
     * can be merged back at the summary and payment steps.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildDetailsScreenData(int $userId, array $data, string $flowToken = ''): array
    {
        if (! empty($flowToken)) {
            Cache::put(
                'flow_data:'.$flowToken,
                [
                    'reservation_date' => $data['reservation_date'] ?? null,
                    'reservation_time' => $data['reservation_time'] ?? null,
                    'customer_name' => $data['customer_name'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'guest_count' => $data['guest_count'] ?? null,
                ],
                now()->addHours(2)
            );
        }
        $config = $this->getReservationConfig($userId);

        $tables = Table::where('user_id', $userId)
            ->where('status', Table::STATUS_AVAILABLE)
            ->orderBy('number')
            ->get()
            ->map(fn (Table $table): array => [
                'id' => (string) $table->id,
                'title' => "Meja {$table->number} ({$table->capacity} orang)",
            ])
            ->values()
            ->toArray();

        $products = [];
        if ($config?->enable_menu_selection) {
            $availableProductIds = $config->available_products ?? [];
            $productsQuery = Product::where('user_id', $userId)->where('is_active', true);
            if (! empty($availableProductIds)) {
                $productsQuery->whereIn('id', $availableProductIds);
            }
            $products = $productsQuery->orderBy('name')->get()->map(fn (Product $product): array => [
                'id' => (string) $product->id,
                'title' => "{$product->name} - Rp".number_format($product->price, 0, ',', '.'),
            ])->values()->toArray();
        }

        return [
            'screen' => 'DETAILS',
            'data' => [
                'tables' => $tables,
                'products' => $products,
                'event_types' => $this->getEventTypes(),
            ],
        ];
    }

    /**
     * Build SUMMARY screen data: appointment_summary, table_summary, menu_summary, price_breakdown.
     *
     * Merges DETAILS form data with cached WELCOME data so all reservation fields are available.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildSummaryScreenData(int $userId, array $data, string $flowToken = ''): array
    {
        if (! empty($flowToken)) {
            $cacheKey = 'flow_data:'.$flowToken;
            $cachedData = Cache::get($cacheKey, []);
            $data = array_merge($cachedData, $data);
            Cache::put($cacheKey, $data, now()->addHours(2));
        }

        $config = $this->getReservationConfig($userId);

        $reservationDate = $data['reservation_date'] ?? null;
        $reservationTime = $data['reservation_time'] ?? null;
        $customerName = $data['customer_name'] ?? '-';
        $phone = $data['phone'] ?? '-';
        $guestCount = $data['guest_count'] ?? '-';
        $tableId = $data['table_id'] ?? null;
        $selectedProductIds = $data['selected_products'] ?? [];

        $dateFormatted = $reservationDate
            ? Carbon::parse($reservationDate)->locale('id')->isoFormat('ddd, D MMM YYYY')
            : '-';
        $appointmentSummary = "📅 {$dateFormatted} jam {$reservationTime} WIB\n👤 {$customerName} ({$phone})\n👥 {$guestCount} tamu";

        $tableSummary = '🪑 Belum memilih meja';
        if ($tableId) {
            $table = Table::find((int) $tableId);
            if ($table) {
                $tableSummary = "🪑 Meja {$table->number} ({$table->capacity} orang)";
            }
        }

        $menuTotal = 0.0;
        $menuSummary = '🍽️ Tidak ada pre-order menu';
        if (! empty($selectedProductIds)) {
            $products = Product::whereIn('id', $selectedProductIds)->get();
            if ($products->isNotEmpty()) {
                $lines = $products->map(fn (Product $p): string => "• {$p->name} - Rp".number_format($p->price, 0, ',', '.'))->implode("\n");
                $menuTotal = (float) $products->sum('price');
                $menuSummary = "🍽️ Menu yang dipilih:\n{$lines}";
            }
        }

        $reservationFee = (float) ($config?->reservation_fee ?? 0);
        $grandTotal = $menuTotal + $reservationFee;
        $dpPercentage = $config?->dp_percentage ?? 50;
        $dpAmount = (int) ceil($grandTotal * ((float) $dpPercentage / 100));

        $priceBreakdown = '💰 Total: Rp'.number_format($grandTotal, 0, ',', '.');
        if ($config?->allow_dp_payment) {
            $priceBreakdown .= "\nDP ({$dpPercentage}%): Rp".number_format($dpAmount, 0, ',', '.');
        }

        return [
            'screen' => 'SUMMARY',
            'data' => [
                'appointment_summary' => $appointmentSummary,
                'table_summary' => $tableSummary,
                'menu_summary' => $menuSummary,
                'price_breakdown' => $priceBreakdown,
            ],
        ];
    }

    /**
     * Build PAYMENT screen: payment_types, payment_methods, instruction.
     *
     * Retrieves cached flow data and stores computed pricing for use in payment_confirmed.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildPaymentScreenData(int $userId, array $data, string $flowToken = ''): array
    {
        $config = $this->getReservationConfig($userId);

        if (! empty($flowToken)) {
            $cacheKey = 'flow_data:'.$flowToken;
            $cachedData = Cache::get($cacheKey, []);
            $data = array_merge($cachedData, $data);
        }

        $selectedProductIds = $data['selected_products'] ?? [];
        $menuTotal = empty($selectedProductIds)
            ? 0.0
            : (float) Product::whereIn('id', $selectedProductIds)->sum('price');

        $reservationFee = (float) ($config?->reservation_fee ?? 0);
        $grandTotal = $menuTotal + $reservationFee;
        $dpPercentage = $config?->dp_percentage ?? 50;
        $dpAmount = (int) ceil($grandTotal * ((float) $dpPercentage / 100));

        if (! empty($flowToken)) {
            Cache::put(
                'flow_data:'.$flowToken,
                array_merge($data, [
                    'menu_total' => $menuTotal,
                    'table_fee' => $reservationFee,
                    'grand_total' => $grandTotal,
                    'dp_amount' => $dpAmount,
                ]),
                now()->addHours(2)
            );
        }

        $paymentTypes = [];
        if ($config?->allow_dp_payment ?? false) {
            $paymentTypes[] = [
                'id' => 'dp',
                'title' => "DP ({$dpPercentage}%) - Rp".number_format($dpAmount, 0, ',', '.'),
            ];
        }
        if (($config?->allow_full_payment ?? true) || empty($paymentTypes)) {
            $paymentTypes[] = [
                'id' => 'full',
                'title' => 'Lunas - Rp'.number_format($grandTotal, 0, ',', '.'),
            ];
        }

        return [
            'screen' => 'PAYMENT',
            'data' => [
                'payment_types' => $paymentTypes,
                'payment_methods' => [
                    ['id' => 'qris', 'title' => 'QRIS'],
                    ['id' => 'cash', 'title' => 'Bayar di Tempat'],
                ],
                'is_payment_method_enabled' => true,
                'payment_instruction' => 'Silakan pilih jenis dan metode pembayaran.',
            ],
        ];
    }

    /**
     * Handle payment_type_selected trigger — refresh PAYMENT screen.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function handlePaymentTypeSelected(int $userId, array $data, string $flowToken = ''): array
    {
        return $this->buildPaymentScreenData($userId, $data, $flowToken);
    }

    /**
     * Handle payment_confirmed trigger — create reservation and return SUCCESS screen.
     *
     * Merges cached flow data (accumulated from WELCOME → DETAILS → SUMMARY → PAYMENT)
     * with the current payment fields then delegates to processFlowResponse.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function handlePaymentConfirmed(int $userId, array $data, string $flowToken): array
    {
        $cacheKey = 'flow_data:'.$flowToken;
        $cachedData = Cache::get($cacheKey, []);
        $allData = array_merge($cachedData, [
            'payment_type' => $data['payment_type'] ?? 'full',
            'payment_method' => $data['payment_method'] ?? 'cash',
        ]);

        $phone = $allData['phone'] ?? null;
        $contact = null;
        if ($phone) {
            $normalizedPhone = $this->normalizePhone($phone);
            $contact = WhatsAppContact::where('user_id', $userId)
                ->where('wa_id', $normalizedPhone)
                ->first();
        }

        $reservation = $this->flowService->processFlowResponse(
            userId: $userId,
            flowToken: $flowToken,
            responseData: $allData,
            contact: $contact,
        );

        Cache::forget($cacheKey);

        $confirmationMessage = sprintf(
            "Terima kasih %s!\n\nReservasi untuk %d tamu pada %s telah kami terima.\nStatus: Menunggu konfirmasi\n\nTim kami akan menghubungi Anda segera.",
            $reservation->customer_name,
            $reservation->guest_count,
            Carbon::parse($reservation->reservation_date)->locale('id')->isoFormat('D MMM YYYY'),
        );

        return [
            'screen' => 'SUCCESS',
            'data' => [
                'reservation_code' => 'RSV-'.str_pad((string) $reservation->id, 6, '0', STR_PAD_LEFT),
                'confirmation_message' => $confirmationMessage,
            ],
        ];
    }

    /**
     * Handle COMPLETE action: create reservation and return SUCCESS screen data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function handleCompleteAction(int $userId, string $flowToken, array $data): array
    {
        $phone = $data['phone'] ?? null;
        $contact = null;
        if ($phone) {
            $normalizedPhone = $this->normalizePhone($phone);
            $contact = WhatsAppContact::where('user_id', $userId)
                ->where('wa_id', $normalizedPhone)
                ->first();
        }

        $reservation = $this->flowService->processFlowResponse(
            userId: $userId,
            flowToken: $flowToken,
            responseData: $data,
            contact: $contact,
        );

        $confirmationMessage = sprintf(
            "Terima kasih %s!\n\nReservasi untuk %d tamu pada %s telah kami terima.\nStatus: Menunggu konfirmasi\n\nTim kami akan menghubungi Anda segera.",
            $reservation->customer_name,
            $reservation->guest_count,
            Carbon::parse($reservation->reservation_date)->locale('id')->isoFormat('D MMM YYYY'),
        );

        return [
            'screen' => 'SUCCESS',
            'data' => [
                'reservation_code' => 'RSV-'.str_pad((string) $reservation->id, 6, '0', STR_PAD_LEFT),
                'confirmation_message' => $confirmationMessage,
            ],
        ];
    }

    // ==================== Helpers ====================

    private function extractUserIdFromToken(string $flowToken): int
    {
        if (empty($flowToken)) {
            return 0;
        }
        $parts = explode('_', $flowToken, 2);

        return (int) ($parts[0] ?? 0);
    }

    private function getReservationConfig(int $userId): ?ReservationConfig
    {
        return ReservationConfig::where('user_id', $userId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * @return array<int, array{id: string, title: string}>
     */
    private function buildDatesData(?ReservationConfig $config): array
    {
        if ($config && ! empty($config->available_slots)) {
            return array_map(fn (array $d): array => [
                'id' => $d['date'],
                'title' => $d['title'],
            ], $config->getUniqueDatesWithCapacity());
        }

        $dates = [];
        $start = Carbon::tomorrow();
        for ($i = 0; $i < 30; $i++) {
            $date = $start->copy()->addDays($i);
            $dates[] = [
                'id' => $date->format('Y-m-d'),
                'title' => $date->locale('id')->isoFormat('ddd, D MMM YYYY'),
            ];
        }

        return $dates;
    }

    /**
     * @return array<int, array{id: string, title: string}>
     */
    private function buildTimeSlotsData(?ReservationConfig $config, ?string $date): array
    {
        if ($config && ! empty($config->available_slots) && $date) {
            $slots = collect($config->available_slots)->filter(function ($slot) use ($date): bool {
                $datetime = is_array($slot) ? ($slot['datetime'] ?? '') : $slot;

                return Carbon::parse($datetime)->toDateString() === $date;
            })->map(function ($slot): array {
                $datetime = is_array($slot) ? ($slot['datetime'] ?? '') : $slot;
                $time = Carbon::parse($datetime)->format('H:i');

                return ['id' => $time, 'title' => "{$time} WIB"];
            })->values()->toArray();

            if (! empty($slots)) {
                return $slots;
            }
        }

        $times = [];
        for ($hour = 10; $hour <= 21; $hour++) {
            $time = sprintf('%02d:00', $hour);
            $times[] = ['id' => $time, 'title' => "{$time} WIB"];
        }

        return $times;
    }

    /**
     * @return array<int, array{id: string, title: string}>
     */
    private function buildGuestOptionsData(?ReservationConfig $config): array
    {
        if ($config && ! empty($config->guest_options)) {
            return array_map(fn ($opt): array => is_array($opt)
                ? ['id' => (string) $opt['id'], 'title' => $opt['title']]
                : ['id' => (string) $opt, 'title' => "{$opt} orang"],
                $config->guest_options
            );
        }

        return [
            ['id' => '1', 'title' => '1 orang'],
            ['id' => '2', 'title' => '2 orang'],
            ['id' => '3', 'title' => '3 orang'],
            ['id' => '4', 'title' => '4 orang'],
            ['id' => '5', 'title' => '5 orang'],
            ['id' => '6', 'title' => '6-8 orang'],
            ['id' => '9', 'title' => '9-12 orang'],
            ['id' => '13', 'title' => 'Lebih dari 12'],
        ];
    }

    /**
     * @return array<int, array{id: string, title: string}>
     */
    private function getEventTypes(): array
    {
        return [
            ['id' => 'regular', 'title' => 'Makan biasa'],
            ['id' => 'birthday', 'title' => 'Ulang Tahun'],
            ['id' => 'meeting', 'title' => 'Meeting / Bisnis'],
            ['id' => 'anniversary', 'title' => 'Anniversary'],
            ['id' => 'family', 'title' => 'Gathering Keluarga'],
            ['id' => 'other', 'title' => 'Lainnya'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildErrorScreen(string $message): array
    {
        return [
            'screen' => 'SUCCESS',
            'data' => [
                'reservation_code' => '-',
                'confirmation_message' => $message,
            ],
        ];
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^\d]/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        }

        return $phone;
    }
}
