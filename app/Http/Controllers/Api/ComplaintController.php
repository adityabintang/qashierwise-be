<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Services\DeliveryFulfillmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Merchant-facing complaint queue backing /dashboard/complain.
 *
 * Complaints are created automatically when a customer taps "Complain" on the
 * WhatsApp delivery-received prompt (see DeliveryFulfillmentService::raiseComplaint).
 */
class ComplaintController extends Controller
{
    public function __construct(
        protected DeliveryFulfillmentService $fulfillment,
    ) {}

    private function uid(Request $request): int
    {
        return $request->user()->getEffectiveUserId();
    }

    public function index(Request $request): JsonResponse
    {
        $complaints = Complaint::with(['order', 'whatsappContact'])
            ->where('user_id', $this->uid($request))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));

        return response()->json(['success' => true, 'data' => $complaints]);
    }

    /**
     * Resolve a complaint: notify the customer, re-activate their AI bot, and
     * mark the complaint resolved with the merchant's resolution note.
     */
    public function resolve(Request $request, Complaint $complaint): JsonResponse
    {
        if ($complaint->user_id !== $this->uid($request)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        if ($complaint->status === Complaint::STATUS_RESOLVED) {
            return response()->json(['success' => false, 'message' => 'Komplain ini sudah diselesaikan.'], 422);
        }

        $validated = $request->validate([
            'resolution_note' => 'required|string|max:2000',
        ]);

        $this->fulfillment->resolveComplaint($complaint, $validated['resolution_note']);

        return response()->json([
            'success' => true,
            'message' => 'Komplain diselesaikan. Pesan terkirim ke pelanggan & bot diaktifkan kembali.',
            'data' => $complaint->fresh(['order', 'whatsappContact']),
        ]);
    }
}
