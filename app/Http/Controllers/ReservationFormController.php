<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReservationFormRequest;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\ReservationConfig;
use App\Models\Store;
use App\Models\User;
use App\Services\ReservationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ReservationFormController extends Controller
{
    public function __construct(
        protected ReservationService $reservationService
    ) {}

    /**
     * Display the reservation form.
     */
    public function show(Request $request): View
    {
        $merchantName = $request->query('merchantName');

        if (! $merchantName) {
            abort(400, 'merchantName parameter is required');
        }

        // Find merchant by slug or name
        $merchant = User::where('slug', $merchantName)
            ->orWhere('name', $merchantName)
            ->orWhere('id', is_numeric($merchantName) ? $merchantName : null)
            ->firstOrFail();

        // Get reservation config
        $config = ReservationConfig::where('user_id', $merchant->id)
            ->where('is_active', true)
            ->first();

        $defaultStoreId = $config->store_id ?? Store::where('user_id', $merchant->id)->value('id');

        if (! $config) {
            abort(404, 'Reservation system not configured for this merchant');
        }

        // Get available dates with availability status
        $availableDates = $this->getAvailableDatesWithStatus($config, $defaultStoreId);

        return view('reservation.form', [
            'merchant' => $merchant,
            'config' => $config,
            'availableDates' => $availableDates,
            'defaultStoreId' => $defaultStoreId,
        ]);
    }

    /**
     * Get available dates with availability status.
     */
    private function getAvailableDatesWithStatus(ReservationConfig $config, int $storeId): array
    {
        $dates = $config->getAvailableDatesFormatted();
        $store = Store::findOrFail($storeId);

        return collect($dates)->map(function ($dateInfo) use ($store) {
            $date = \Carbon\Carbon::parse($dateInfo['id']);
            $availableTables = $this->reservationService->getAvailableTables($store, $date);

            return [
                ...$dateInfo,
                'available' => $availableTables->count() > 0,
                'availableCount' => $availableTables->count(),
            ];
        })->toArray();
    }

    /**
     * Submit reservation and generate QRIS payment.
     */
    public function submit(ReservationFormRequest $request): JsonResponse
    {
        try {
            $merchantName = $request->query('merchantName');

            if (! $merchantName) {
                return response()->json([
                    'success' => false,
                    'message' => 'merchantName parameter is required',
                ], 400);
            }

            // Find merchant
            $merchant = User::where('slug', $merchantName)
                ->orWhere('name', $merchantName)
                ->orWhere('id', is_numeric($merchantName) ? $merchantName : null)
                ->firstOrFail();

            // Create reservation
            $result = $this->reservationService->createReservation(
                $request->validated(),
                $merchant
            );

            $reservation = $result['reservation'];
            $qrisTransaction = $result['qris'];

            Log::info('Reservation form submitted successfully', [
                'reservation_id' => $reservation->id,
                'order_id' => $reservation->order_id,
                'customer' => $reservation->customer_name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Reservasi berhasil dibuat. Silakan lakukan pembayaran.',
                'data' => [
                    'order_id' => $reservation->order_id,
                    'reservation_id' => $reservation->id,
                    'qr_code_url' => $qrisTransaction->qr_code_url,
                    'amount' => $reservation->payment_type === Reservation::PAYMENT_TYPE_DP
                        ? $reservation->calculateDpAmount()
                        : $reservation->total_amount,
                    'payment_type' => $reservation->payment_type,
                    'expires_at' => $qrisTransaction->expires_at->toIso8601String(),
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Reservation form submission failed', [
                'merchant_name' => $request->query('merchantName'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check reservation payment status.
     */
    public function status(Request $request, string $orderId): JsonResponse
    {
        try {
            $reservation = $this->reservationService->getReservationByOrderId($orderId);

            if (! $reservation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reservasi tidak ditemukan.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $reservation->order_id,
                    'status' => $reservation->status,
                    'payment_status' => $reservation->qrisTransaction?->status,
                    'is_confirmed' => $reservation->status === Reservation::STATUS_CONFIRMED,
                    'is_pending' => $reservation->status === Reservation::STATUS_PENDING_PAYMENT,
                    'is_cancelled' => $reservation->status === Reservation::STATUS_CANCELLED,
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Failed to check reservation status', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memeriksa status.',
            ], 500);
        }
    }

    /**
     * Get available tables for a specific store and date.
     */
    public function getAvailableTables(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'store_id' => 'required|exists:stores,id',
                'reservation_date' => 'required|date',
            ]);

            $merchantName = $request->query('merchantName');

            if (! $merchantName) {
                return response()->json([
                    'success' => false,
                    'message' => 'merchantName parameter is required',
                ], 400);
            }

            $merchant = User::where('slug', $merchantName)
                ->orWhere('name', $merchantName)
                ->orWhere('id', is_numeric($merchantName) ? $merchantName : null)
                ->firstOrFail();

            $store = Store::where('user_id', $merchant->id)
                ->findOrFail($request->store_id);
            $date = \Carbon\Carbon::parse($request->reservation_date);

            $availableTables = $this->reservationService->getAvailableTables($store, $date);

            return response()->json([
                'success' => true,
                'data' => $availableTables->map(fn ($table) => [
                    'id' => $table->id,
                    'number' => $table->number,
                    'capacity' => $table->capacity,
                    'label' => "Meja {$table->number} (Kapasitas: {$table->capacity} orang)",
                ])->values(),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get available tables', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data meja.',
            ], 500);
        }
    }

    /**
     * Get available products for a specific store.
     */
    public function getAvailableProducts(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'store_id' => 'nullable|exists:stores,id',
            ]);

            $merchantName = $request->query('merchantName');

            if (! $merchantName) {
                return response()->json([
                    'success' => false,
                    'message' => 'merchantName parameter is required',
                ], 400);
            }

            $merchant = User::where('slug', $merchantName)
                ->orWhere('name', $merchantName)
                ->orWhere('id', is_numeric($merchantName) ? $merchantName : null)
                ->firstOrFail();

            $configQuery = ReservationConfig::where('user_id', $merchant->id)
                ->where('is_active', true);

            if ($request->store_id) {
                $configQuery->where('store_id', $request->store_id);
            }

            $config = $configQuery->first();

            if (! $config || ! $config->enable_menu_selection) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            $query = Product::where('user_id', $merchant->id)
                ->where('is_active', true);

            $configuredProductIds = collect($config->available_products ?? [])
                ->filter()
                ->map(fn ($productId) => (int) $productId)
                ->values();

            if ($configuredProductIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            $query->whereIn('id', $configuredProductIds->all());

            $products = $query->get();

            return response()->json([
                'success' => true,
                'data' => $products->map(fn ($product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'label' => "{$product->name} - Rp ".number_format($product->price, 0, ',', '.'),
                ])->values(),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get available products', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data produk.',
            ], 500);
        }
    }
}
