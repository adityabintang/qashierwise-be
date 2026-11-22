<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\DeliveryFulfillmentService;
use App\Services\MediaStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Public, token-protected page where the courier uploads proof of delivery and
 * taps "pesanan telah diantarkan". No auth — security is the unguessable
 * delivery_token shared only with the driver via Fonnte.
 */
class DeliveryProofController extends Controller
{
    public function __construct(
        protected DeliveryFulfillmentService $fulfillment,
        protected MediaStorageService $media,
    ) {}

    public function show(string $token): View
    {
        $order = Order::with(['store', 'deliveryDriver', 'items'])
            ->where('delivery_token', $token)
            ->firstOrFail();

        $config = \App\Models\DeliveryConfig::forUser($order->store->user_id);

        return view('delivery.proof', [
            'order' => $order,
            'token' => $token,
            'proofRequired' => (bool) $config->proof_required,
            'addressRequired' => (bool) $config->address_required,
            'done' => in_array($order->fulfillment_status, [
                Order::FULFILLMENT_DELIVERED,
                Order::FULFILLMENT_COMPLAINT,
            ], true),
            // After proof upload the order stays out_for_delivery until the
            // customer confirms; show a "waiting for customer" state if proof
            // already exists.
            'awaitingCustomer' => $order->fulfillment_status === Order::FULFILLMENT_OUT_FOR_DELIVERY
                && ! empty($order->proof_image_url),
        ]);
    }

    public function submit(string $token, Request $request): RedirectResponse
    {
        $order = Order::where('delivery_token', $token)->firstOrFail();

        if ($order->fulfillment_status !== Order::FULFILLMENT_OUT_FOR_DELIVERY) {
            return redirect()->route('delivery.proof', $token)
                ->with('error', 'Pesanan ini sudah tidak dapat diproses.');
        }

        // Required fields are driven by the merchant's delivery config.
        $config = \App\Models\DeliveryConfig::forUser($order->store->user_id);
        $proofRule = $config->proof_required ? 'required' : 'nullable';
        $coordRule = $config->address_required ? 'required' : 'nullable';

        $validated = $request->validate([
            'proof' => $proofRule.'|image|max:5120', // 5 MB
            'latitude' => $coordRule.'|numeric|between:-90,90',
            'longitude' => $coordRule.'|numeric|between:-180,180',
        ]);

        $proofUrl = null;
        if ($request->hasFile('proof')) {
            try {
                $result = $this->media->store($request->file('proof'), 'image');
                $proofUrl = $result['url'] ?? null;
            } catch (\Throwable $e) {
                Log::error('Delivery proof upload failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);

                return redirect()->route('delivery.proof', $token)
                    ->with('error', 'Gagal mengunggah foto. Silakan coba lagi.');
            }
        }

        $location = (isset($validated['latitude'], $validated['longitude']) && $validated['latitude'] !== null)
            ? ['lat' => $validated['latitude'], 'lng' => $validated['longitude']]
            : null;

        // Store proof + delivery location, then trigger the "received?" question.
        $this->fulfillment->submitProof($order, $proofUrl, $location);

        return redirect()->route('delivery.proof', $token)
            ->with('success', 'Bukti terkirim. Menunggu konfirmasi dari pembeli.');
    }
}
