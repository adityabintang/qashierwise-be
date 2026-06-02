<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryConfig;
use App\Models\DeliveryDriver;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Merchant-facing delivery management API backing the /dashboard/delivery page:
 * delivery config, driver directory CRUD, and proof-of-delivery listing.
 *
 * All queries are scoped to the authenticated user's effective (master) id so
 * sub-accounts share the master merchant's drivers/config.
 */
class DeliveryController extends Controller
{
    private function uid(Request $request): int
    {
        return $request->user()->getEffectiveUserId();
    }

    // ---- Config -------------------------------------------------------------

    public function getConfig(Request $request): JsonResponse
    {
        $uid = $this->uid($request);
        $config = DeliveryConfig::forUser($uid);

        // First time: carry over the ongkir previously set on the AI agent so
        // existing merchants don't lose their value (ongkir now lives here).
        if ($config->wasRecentlyCreated) {
            $agent = $this->agentForUser($uid);
            if ($agent && (float) $agent->default_ongkir > 0) {
                $config->default_ongkir = $agent->default_ongkir;
                $config->save();
            }
        }

        return response()->json(['success' => true, 'data' => $config]);
    }

    public function updateConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => 'sometimes|boolean',
            'default_ongkir' => 'sometimes|numeric|min:0',
            'proof_required' => 'sometimes|boolean',
            'address_required' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:2000',
        ]);

        $uid = $this->uid($request);
        $config = DeliveryConfig::forUser($uid);
        $config->fill($validated)->save();

        // Keep the AI agent in sync: ongkir is the source of truth here, and
        // turning the master switch off must also disable the agent's delivery.
        $agent = $this->agentForUser($uid);
        if ($agent) {
            $dirty = false;
            if (array_key_exists('default_ongkir', $validated)) {
                $agent->default_ongkir = $config->default_ongkir;
                $dirty = true;
            }
            if (array_key_exists('is_active', $validated) && ! $config->is_active && $agent->delivery_enabled) {
                $agent->delivery_enabled = false;
                $dirty = true;
            }
            if ($dirty) {
                $agent->save();
            }
        }

        return response()->json(['success' => true, 'message' => 'Konfigurasi delivery disimpan.', 'data' => $config]);
    }

    /**
     * Resolve the AI agent for a merchant (via their WhatsApp account).
     */
    private function agentForUser(int $userId): ?\App\Models\AiAgent
    {
        $account = \App\Models\WhatsAppAccount::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->orderByDesc('is_active')
            ->first();

        return $account
            ? \App\Models\AiAgent::where('whatsapp_account_id', $account->id)->first()
            : null;
    }

    // ---- Drivers ------------------------------------------------------------

    public function listDrivers(Request $request): JsonResponse
    {
        $drivers = DeliveryDriver::where('user_id', $this->uid($request))
            ->when($request->boolean('active_only'), fn ($q) => $q->where('is_active', true))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $drivers]);
    }

    public function storeDriver(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'required|string|max:25',
            'vehicle' => 'nullable|string|max:100',
            'plate_number' => 'nullable|string|max:30',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'sometimes|boolean',
        ]);
        $validated['user_id'] = $this->uid($request);

        $driver = DeliveryDriver::create($validated);

        return response()->json(['success' => true, 'message' => 'Driver ditambahkan.', 'data' => $driver], 201);
    }

    public function updateDriver(Request $request, DeliveryDriver $driver): JsonResponse
    {
        if ($driver->user_id !== $this->uid($request)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'phone' => 'sometimes|string|max:25',
            'vehicle' => 'nullable|string|max:100',
            'plate_number' => 'nullable|string|max:30',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'sometimes|boolean',
        ]);

        $driver->fill($validated)->save();

        return response()->json(['success' => true, 'message' => 'Driver diperbarui.', 'data' => $driver]);
    }

    public function destroyDriver(Request $request, DeliveryDriver $driver): JsonResponse
    {
        if ($driver->user_id !== $this->uid($request)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $driver->delete();

        return response()->json(['success' => true, 'message' => 'Driver dihapus.']);
    }

    // ---- Proofs / delivered orders -----------------------------------------

    /**
     * List delivery orders (with proof + driver) for the proof gallery. Can be
     * filtered by driver and fulfillment_status.
     */
    public function listProofs(Request $request): JsonResponse
    {
        $uid = $this->uid($request);

        $orders = Order::with(['deliveryDriver', 'store'])
            ->whereHas('store', fn ($q) => $q->where('user_id', $uid))
            ->where('delivery_type', Order::DELIVERY_TYPE_DELIVERY)
            ->whereNotNull('fulfillment_status')
            ->when($request->input('delivery_driver_id'), fn ($q, $id) => $q->where('delivery_driver_id', $id))
            ->when($request->input('fulfillment_status'), fn ($q, $fs) => $q->where('fulfillment_status', $fs))
            ->when($request->boolean('with_proof_only'), fn ($q) => $q->whereNotNull('proof_image_url'))
            ->orderByDesc('out_for_delivery_at')
            ->paginate($request->input('per_page', 20));

        return response()->json(['success' => true, 'data' => $orders]);
    }
}
