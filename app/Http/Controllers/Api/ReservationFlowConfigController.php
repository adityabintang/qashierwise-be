<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateReservationFlowConfigRequest;
use App\Models\Product;
use App\Models\ReservationFlowConfig;
use App\Models\Table;
use App\Services\WhatsAppFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ReservationFlowConfigController extends Controller
{
    public function __construct(
        private WhatsAppFlowService $flowService
    ) {}

    /**
     * Get current flow configuration.
     */
    public function show(): JsonResponse
    {
        $config = ReservationFlowConfig::getOrCreateForUser(Auth::id());

        // Get available tables and products for selection
        $tables = Table::where('user_id', Auth::id())
            ->orderBy('number')
            ->get(['id', 'number', 'capacity', 'status']);

        $products = Product::where('user_id', Auth::id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'category_id']);

        return response()->json([
            'success' => true,
            'data' => [
                'config' => $config,
                'available_tables' => $tables,
                'available_products' => $products,
                'time_slots_preview' => $config->getTimeSlots(),
                'dates_preview' => array_slice($config->getAvailableDates(), 0, 7),
                'guest_options' => $config->getGuestCountOptions(),
                'event_types' => $config->getEventTypes(),
                'default_event_types' => ReservationFlowConfig::DEFAULT_EVENT_TYPES,
            ],
        ]);
    }

    /**
     * Update flow configuration.
     */
    public function update(UpdateReservationFlowConfigRequest $request): JsonResponse
    {
        $config = ReservationFlowConfig::getOrCreateForUser(Auth::id());

        // Validate table IDs belong to user
        if ($request->has('available_table_ids')) {
            $validTableIds = Table::where('user_id', Auth::id())
                ->whereIn('id', $request->available_table_ids)
                ->pluck('id')
                ->toArray();
            $request->merge(['available_table_ids' => $validTableIds]);
        }

        // Validate product IDs belong to user
        if ($request->has('available_product_ids')) {
            $validProductIds = Product::where('user_id', Auth::id())
                ->whereIn('id', $request->available_product_ids)
                ->pluck('id')
                ->toArray();
            $request->merge(['available_product_ids' => $validProductIds]);
        }

        // Ensure at least one payment type is enabled
        if ($request->has('allow_full_payment') && $request->has('allow_dp_payment')) {
            if (!$request->allow_full_payment && !$request->allow_dp_payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Minimal satu tipe pembayaran harus diaktifkan',
                ], 422);
            }
        }

        // Ensure at least one payment method is enabled if payment is enabled
        if ($request->has('enable_qris') && $request->has('enable_cash')) {
            if ($config->enable_payment && !$request->enable_qris && !$request->enable_cash) {
                return response()->json([
                    'success' => false,
                    'message' => 'Minimal satu metode pembayaran harus diaktifkan',
                ], 422);
            }
        }

        $config->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Konfigurasi berhasil diperbarui',
            'data' => [
                'config' => $config->fresh(),
                'time_slots_preview' => $config->getTimeSlots(),
                'dates_preview' => array_slice($config->getAvailableDates(), 0, 7),
            ],
        ]);
    }

    /**
     * Preview flow with current configuration.
     */
    public function preview(): JsonResponse
    {
        $config = ReservationFlowConfig::getOrCreateForUser(Auth::id());

        // Build preview data
        $preview = [
            'time_slots' => $config->getTimeSlots(),
            'available_dates' => $config->getAvailableDates(),
            'guest_options' => $config->getGuestCountOptions(),
            'event_types' => $config->getEventTypes(),
            'tables' => $config->enable_table_selection
                ? $this->getTablesForPreview($config)
                : [],
            'products' => $config->enable_menu_selection
                ? $this->getProductsForPreview($config)
                : [],
            'payment_types' => $config->enable_payment
                ? $config->getPaymentTypes(500000) // Example total
                : [],
            'payment_methods' => $config->enable_payment
                ? $config->getPaymentMethods()
                : [],
            'messages' => [
                'header' => $config->header_text,
                'body' => $config->body_text ?? 'Silakan isi form di bawah ini untuk membuat reservasi.',
                'footer' => $config->footer_text,
                'cta' => $config->cta_text,
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $preview,
        ]);
    }

    /**
     * Create and publish flow to WhatsApp.
     */
    public function publish(): JsonResponse
    {
        $config = ReservationFlowConfig::getOrCreateForUser(Auth::id());

        try {
            // Create flow if not exists
            if (!$config->hasFlow()) {
                $result = $this->flowService->createReservationFlowWithConfig(Auth::id(), $config);
                $config->update([
                    'flow_id' => $result['id'],
                    'flow_status' => ReservationFlowConfig::STATUS_DRAFT,
                ]);
            }

            // Publish the flow
            $published = $this->flowService->publishFlow(Auth::id(), $config->flow_id);

            if ($published) {
                $config->update(['flow_status' => ReservationFlowConfig::STATUS_PUBLISHED]);

                return response()->json([
                    'success' => true,
                    'message' => 'Flow berhasil dipublish',
                    'data' => $config->fresh(),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal mempublish flow',
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync flow status from Meta.
     */
    public function sync(): JsonResponse
    {
        $config = ReservationFlowConfig::getOrCreateForUser(Auth::id());

        if (!$config->hasFlow()) {
            return response()->json([
                'success' => false,
                'message' => 'Belum ada flow yang dibuat',
            ], 404);
        }

        try {
            $flowData = $this->flowService->getFlow(Auth::id(), $config->flow_id);

            if ($flowData) {
                $status = match ($flowData['status'] ?? '') {
                    'PUBLISHED' => ReservationFlowConfig::STATUS_PUBLISHED,
                    'DEPRECATED' => ReservationFlowConfig::STATUS_DEPRECATED,
                    default => ReservationFlowConfig::STATUS_DRAFT,
                };

                $config->update(['flow_status' => $status]);

                return response()->json([
                    'success' => true,
                    'message' => 'Status berhasil disinkronkan',
                    'data' => [
                        'config' => $config->fresh(),
                        'flow_data' => $flowData,
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Flow tidak ditemukan di Meta',
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete flow and reset config.
     */
    public function destroy(): JsonResponse
    {
        $config = ReservationFlowConfig::getOrCreateForUser(Auth::id());

        if ($config->hasFlow()) {
            try {
                $this->flowService->deleteFlow(Auth::id(), $config->flow_id);
            } catch (\Exception $e) {
                // Log but continue - flow might already be deleted
            }
        }

        $config->update([
            'flow_id' => null,
            'flow_status' => ReservationFlowConfig::STATUS_DRAFT,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Flow berhasil dihapus',
            'data' => $config->fresh(),
        ]);
    }

    /**
     * Send flow to a phone number.
     */
    public function send(\Illuminate\Http\Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        $config = ReservationFlowConfig::getOrCreateForUser(Auth::id());

        if (!$config->hasFlow() || !$config->isPublished()) {
            return response()->json([
                'success' => false,
                'message' => 'Flow belum dipublish. Silakan publish flow terlebih dahulu.',
            ], 422);
        }

        try {
            $result = $this->flowService->sendReservationFlowWithConfig(
                Auth::id(),
                $request->phone,
                $config
            );

            return response()->json([
                'success' => true,
                'message' => 'Flow reservasi berhasil dikirim',
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ==================== Helper Methods ====================

    private function getTablesForPreview(ReservationFlowConfig $config): array
    {
        $query = Table::where('user_id', Auth::id())
            ->where('status', Table::STATUS_AVAILABLE)
            ->orderBy('number');

        if (!empty($config->available_table_ids)) {
            $query->whereIn('id', $config->available_table_ids);
        }

        return $query->get()->map(fn ($table) => [
            'id' => (string) $table->id,
            'title' => "Meja {$table->number} ({$table->capacity} orang)",
        ])->toArray();
    }

    private function getProductsForPreview(ReservationFlowConfig $config): array
    {
        $query = Product::where('user_id', Auth::id())
            ->where('is_active', true)
            ->orderBy('name');

        if (!empty($config->available_product_ids)) {
            $query->whereIn('id', $config->available_product_ids);
        }

        return $query->get()->map(fn ($product) => [
            'id' => (string) $product->id,
            'title' => "{$product->name} - Rp" . number_format($product->price, 0, ',', '.'),
        ])->toArray();
    }
}
