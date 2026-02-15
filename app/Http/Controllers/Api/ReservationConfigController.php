<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkTimeSlotGenerateRequest;
use App\Http\Resources\ReservationConfigResource;
use App\Models\ReservationConfig;
use App\Models\Store;
use App\Services\ReservationSlotGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ReservationConfigController extends Controller
{
    /**
     * Display the reservation config for the user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $effectiveUserId = $user->getEffectiveUserId();
        $storeId = $request->input('store_id');

        $query = ReservationConfig::with('store')
            ->where('user_id', $effectiveUserId);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $configs = $query->get();

        return response()->json([
            'success' => true,
            'data' => ReservationConfigResource::collection($configs),
        ]);
    }

    /**
     * Show the specified config.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $effectiveUserId = $user->getEffectiveUserId();

        $config = ReservationConfig::with('store')
            ->where('user_id', $effectiveUserId)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new ReservationConfigResource($config),
        ]);
    }

    /**
     * Store a newly created config.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $effectiveUserId = $user->getEffectiveUserId();

        $validated = $request->validate([
            'store_id' => [
                'required',
                Rule::exists('stores', 'id')->where('user_id', $effectiveUserId),
            ],
            'is_active' => 'boolean',
            'available_slots' => 'nullable|array',
            'available_slots.*' => 'nullable|date_format:Y-m-d\TH:i',
            'capacity_per_slot' => 'nullable|integer|min:1|max:100',
            'guest_options' => 'required|array|min:1',
            'guest_options.*' => 'required|integer|min:1|max:100',
            'reservation_fee' => 'required|numeric|min:0',
            'dp_percentage' => 'required|numeric|min:0|max:100',
            'allow_full_payment' => 'boolean',
            'allow_dp_payment' => 'boolean',
            'available_tables' => 'nullable|array',
            'available_tables.*' => 'nullable|integer',
            'available_products' => 'nullable|array',
            'available_products.*' => 'nullable|integer',
            'enable_menu_selection' => 'boolean',
            'require_menu_selection' => 'boolean',
        ]);

        // Check if config already exists for this store
        $existingConfig = ReservationConfig::where('user_id', $effectiveUserId)
            ->where('store_id', $validated['store_id'])
            ->first();

        if ($existingConfig) {
            return response()->json([
                'success' => false,
                'message' => __('dashboard.reservation.config_already_exists'),
            ], 422);
        }

        $allowFullPayment = $validated['allow_full_payment'] ?? true;
        $allowDpPayment = $validated['allow_dp_payment'] ?? true;
        if (! $allowFullPayment && ! $allowDpPayment) {
            return response()->json([
                'success' => false,
                'message' => 'Minimal satu jenis pembayaran harus dipilih.',
                'errors' => [
                    'allow_full_payment' => ['Minimal satu jenis pembayaran harus dipilih.'],
                    'allow_dp_payment' => ['Minimal satu jenis pembayaran harus dipilih.'],
                ],
            ], 422);
        }

        try {
            $config = ReservationConfig::create([
                'user_id' => $effectiveUserId,
                'store_id' => $validated['store_id'],
                'is_active' => $validated['is_active'] ?? true,
                'available_slots' => $validated['available_slots'] ?? [],
                'capacity_per_slot' => $validated['capacity_per_slot'] ?? 12,
                'guest_options' => $validated['guest_options'],
                'reservation_fee' => $validated['reservation_fee'] ?? 0,
                'dp_percentage' => $validated['dp_percentage'],
                'allow_full_payment' => $validated['allow_full_payment'] ?? true,
                'allow_dp_payment' => $validated['allow_dp_payment'] ?? true,
                'available_tables' => $validated['available_tables'] ?? [],
                'available_products' => $validated['available_products'] ?? [],
                'enable_menu_selection' => $validated['enable_menu_selection'] ?? false,
                'require_menu_selection' => $validated['require_menu_selection'] ?? false,
            ]);

            return response()->json([
                'success' => true,
                'message' => __('dashboard.reservation.config_created'),
                'data' => new ReservationConfigResource($config->load('store')),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create reservation config', [
                'user_id' => $effectiveUserId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('dashboard.reservation.config_create_failed'),
            ], 500);
        }
    }

    /**
     * Update the specified config.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $effectiveUserId = $user->getEffectiveUserId();

        $config = ReservationConfig::where('user_id', $effectiveUserId)
            ->findOrFail($id);

        $validated = $request->validate([
            'is_active' => 'boolean',
            'available_slots' => 'nullable|array',
            'available_slots.*' => 'nullable|date_format:Y-m-d\TH:i',
            'capacity_per_slot' => 'nullable|integer|min:1|max:100',
            'guest_options' => 'nullable|array|min:1',
            'guest_options.*' => 'required_with:guest_options|integer|min:1|max:100',
            'reservation_fee' => 'required|numeric|min:0',
            'dp_percentage' => 'nullable|numeric|min:0|max:100',
            'allow_full_payment' => 'boolean',
            'allow_dp_payment' => 'boolean',
            'available_tables' => 'nullable|array',
            'available_tables.*' => 'nullable|integer',
            'available_products' => 'nullable|array',
            'available_products.*' => 'nullable|integer',
            'enable_menu_selection' => 'boolean',
            'require_menu_selection' => 'boolean',
        ]);

        $allowFullPayment = $validated['allow_full_payment'] ?? $config->allow_full_payment;
        $allowDpPayment = $validated['allow_dp_payment'] ?? $config->allow_dp_payment;
        if (! $allowFullPayment && ! $allowDpPayment) {
            return response()->json([
                'success' => false,
                'message' => 'Minimal satu jenis pembayaran harus dipilih.',
                'errors' => [
                    'allow_full_payment' => ['Minimal satu jenis pembayaran harus dipilih.'],
                    'allow_dp_payment' => ['Minimal satu jenis pembayaran harus dipilih.'],
                ],
            ], 422);
        }

        try {
            $config->update($validated);

            return response()->json([
                'success' => true,
                'message' => __('dashboard.reservation.config_updated'),
                'data' => new ReservationConfigResource($config->fresh()->load('store')),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update reservation config', [
                'config_id' => $config->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('dashboard.reservation.config_update_failed'),
            ], 500);
        }
    }

    /**
     * Remove the specified config.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $effectiveUserId = $user->getEffectiveUserId();

        $config = ReservationConfig::where('user_id', $effectiveUserId)
            ->findOrFail($id);

        try {
            $config->delete();

            return response()->json([
                'success' => true,
                'message' => __('dashboard.reservation.config_deleted'),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete reservation config', [
                'config_id' => $config->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('dashboard.reservation.config_delete_failed'),
            ], 500);
        }
    }

    /**
     * Generate bulk time slots for a store configuration.
     */
    public function generateSlots(BulkTimeSlotGenerateRequest $request, ReservationSlotGenerator $generator): JsonResponse
    {
        $validated = $request->validated();
        $slots = $generator->generate($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'slots' => $slots,
                'count' => count($slots),
            ],
        ]);
    }
}
