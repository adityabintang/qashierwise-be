<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkTimeSlotGenerateRequest;
use App\Http\Resources\ReservationConfigResource;
use App\Models\CatalogProduct;
use App\Models\ReservationConfig;
use App\Models\Store;
use App\Services\CatalogService;
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
            'guest_options' => 'nullable|array|min:1',
            'guest_options.*' => 'nullable|integer|min:1|max:100',
            'reservation_fee' => 'required|numeric|min:0',
            'dp_percentage' => 'required|numeric|min:0|max:100',
            'allow_full_payment' => 'boolean',
            'allow_dp_payment' => 'boolean',
            'available_tables' => 'nullable|array',
            'available_tables.*' => 'nullable|integer',
            'available_products' => 'nullable|array',
            'available_products.*' => 'nullable|integer|exists:catalog_products,id',
            'enable_menu_selection' => 'boolean',
            'require_menu_selection' => 'boolean',
            // Reminder fields
            'reminder_enabled' => 'boolean',
            'reminder_template' => 'nullable|string|max:255',
            'reminder_template_language' => 'nullable|string|max:10',
            'reminder_param_mapping' => 'nullable|array',
            'reminder_timing' => 'nullable|array',
            'reminder_timing.*' => 'nullable|integer|min:1|max:10080',
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
                'guest_options' => $validated['guest_options'] ?? [],
                'reservation_fee' => $validated['reservation_fee'] ?? 0,
                'dp_percentage' => $validated['dp_percentage'],
                'allow_full_payment' => $validated['allow_full_payment'] ?? true,
                'allow_dp_payment' => $validated['allow_dp_payment'] ?? true,
                'available_tables' => $validated['available_tables'] ?? [],
                'available_products' => $validated['available_products'] ?? [],
                'enable_menu_selection' => $validated['enable_menu_selection'] ?? false,
                'require_menu_selection' => $validated['require_menu_selection'] ?? false,
                // Reminder fields
                'reminder_enabled' => $validated['reminder_enabled'] ?? false,
                'reminder_template' => $validated['reminder_template'] ?? null,
                'reminder_template_language' => $validated['reminder_template_language'] ?? null,
                'reminder_param_mapping' => $validated['reminder_param_mapping'] ?? [],
                'reminder_timing' => $validated['reminder_timing'] ?? [],
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
            'available_products.*' => 'nullable|integer|exists:catalog_products,id',
            'enable_menu_selection' => 'boolean',
            'require_menu_selection' => 'boolean',
            // Reminder fields
            'reminder_enabled' => 'boolean',
            'reminder_template' => 'nullable|string|max:255',
            'reminder_template_language' => 'nullable|string|max:10',
            'reminder_param_mapping' => 'nullable|array',
            'reminder_timing' => 'nullable|array',
            'reminder_timing.*' => 'nullable|integer|min:1|max:10080',
            // Auto cleanup fields
            'auto_cleanup_enabled' => 'boolean',
            'auto_cleanup_reference_date' => 'nullable|date_format:Y-m-d',
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
     * List products from the merchant's BOUND Meta Catalog (the catalog linked
     * to their AiAgent) so they can be attached to a reservation menu. Sourced
     * from the locally-synced `catalog_products` mirror, scoped to that one
     * catalog — master Product and other (unbound) catalogs are never used.
     */
    public function catalogProducts(Request $request, CatalogService $catalogService): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $effectiveUserId = $user->getEffectiveUserId();
        $boundCatalogId = $catalogService->getBoundCatalogId($effectiveUserId);

        if (! $boundCatalogId) {
            // No catalog is bound to this merchant yet — nothing to offer.
            return response()->json([
                'success' => true,
                'data' => [],
                'catalog_id' => null,
            ]);
        }

        $products = CatalogProduct::where('user_id', $effectiveUserId)
            ->where('catalog_id', $boundCatalogId)
            ->where('is_available', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'catalog_id' => $boundCatalogId,
            'data' => $products->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'currency' => $product->currency,
                'retailer_id' => $product->retailer_id,
                'category' => $product->category,
                'catalog_id' => $product->catalog_id,
                'label' => "{$product->name} - Rp ".number_format((float) $product->price, 0, ',', '.'),
            ])->values(),
        ]);
    }

    /**
     * Report whether the merchant has connected Google Calendar (used by the
     * reservation config UI to show connect/disconnect state).
     */
    public function googleCalendarStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        // Calendar is connected per master account (effective user).
        $owner = $user->getEffectiveUserId() === $user->id
            ? $user
            : \App\Models\User::find($user->getEffectiveUserId());

        return response()->json([
            'success' => true,
            'data' => [
                'connected' => ! empty($owner?->google_calendar_refresh_token),
                'email' => $owner?->google_calendar_email,
            ],
        ]);
    }

    /**
     * Generate bulk time slots for a store configuration.
     */
    public function generateSlots(BulkTimeSlotGenerateRequest $request, ReservationSlotGenerator $generator): JsonResponse
    {
        $validated = $request->validated();

        // Get the config to check auto_cleanup_enabled setting
        $config = ReservationConfig::where('user_id', $request->user()->getEffectiveUserId())
            ->where('store_id', $validated['store_id'])
            ->first();

        // Pass store_id and auto_cleanup_enabled to generator for capacity calculation
        $payload = array_merge($validated, [
            'store_id' => $validated['store_id'],
            'auto_cleanup_enabled' => $config?->auto_cleanup_enabled ?? false,
        ]);

        $result = $generator->generate($payload);

        return response()->json([
            'success' => true,
            'data' => [
                'slots' => $result['slots'],
                'metadata' => $result['metadata'],
                'count' => count($result['slots']),
            ],
        ]);
    }
}
