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

        return view('delivery.proof', [
            'order' => $order,
            'token' => $token,
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

        $validated = $request->validate([
            'proof' => 'required|image|max:5120', // 5 MB
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $proofUrl = null;
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

        // Store proof + delivery location, then trigger the "received?" question.
        $this->fulfillment->submitProof($order, $proofUrl, [
            'lat' => $validated['latitude'],
            'lng' => $validated['longitude'],
        ]);

        return redirect()->route('delivery.proof', $token)
            ->with('success', 'Bukti terkirim. Menunggu konfirmasi dari pembeli.');
    }
}
