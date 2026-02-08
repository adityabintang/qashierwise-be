<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\WhatsAppFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function __construct(
        private WhatsAppFlowService $flowService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Reservation::where('user_id', auth()->user()->getEffectiveUserId())
            ->with('whatsappContact')
            ->orderBy('reservation_date', 'desc')
            ->orderBy('reservation_time', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('reservation_date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('reservation_date', '<=', $request->to_date);
        }

        // Filter upcoming only
        if ($request->boolean('upcoming')) {
            $query->upcoming();
        }

        // Filter today only
        if ($request->boolean('today')) {
            $query->today();
        }

        // Search by name or phone
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $reservations = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $reservations,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'reservation_date' => 'required|date|after_or_equal:today',
            'reservation_time' => 'required|date_format:H:i',
            'guest_count' => 'required|integer|min:1|max:100',
            'email' => 'nullable|email|max:255',
            'event_type' => 'nullable|string|in:regular,birthday,meeting,anniversary,family,other',
            'special_notes' => 'nullable|string|max:1000',
            'preferences' => 'nullable|array',
            'preferences.*' => 'string|in:window,quiet,outdoor,smoking,baby_chair,wheelchair',
            'deposit' => 'nullable|numeric|min:0',
            'pre_order_items' => 'nullable|array',
        ]);

        $validated['user_id'] = auth()->user()->getEffectiveUserId();
        $validated['status'] = 'pending';

        $reservation = Reservation::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Reservasi berhasil dibuat',
            'data' => $reservation->load('whatsappContact'),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Reservation $reservation): JsonResponse
    {
        $this->authorizeReservation($reservation);

        return response()->json([
            'success' => true,
            'data' => $reservation->load('whatsappContact'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Reservation $reservation): JsonResponse
    {
        $this->authorizeReservation($reservation);

        $validated = $request->validate([
            'customer_name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'reservation_date' => 'sometimes|date',
            'reservation_time' => 'sometimes|date_format:H:i',
            'guest_count' => 'sometimes|integer|min:1|max:100',
            'email' => 'nullable|email|max:255',
            'event_type' => 'nullable|string|in:regular,birthday,meeting,anniversary,family,other',
            'special_notes' => 'nullable|string|max:1000',
            'preferences' => 'nullable|array',
            'deposit' => 'nullable|numeric|min:0',
            'deposit_paid' => 'sometimes|boolean',
            'pre_order_items' => 'nullable|array',
        ]);

        $reservation->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Reservasi berhasil diperbarui',
            'data' => $reservation->fresh()->load('whatsappContact'),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Reservation $reservation): JsonResponse
    {
        $this->authorizeReservation($reservation);

        $reservation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Reservasi berhasil dihapus',
        ]);
    }

    /**
     * Confirm a reservation
     */
    public function confirm(Reservation $reservation): JsonResponse
    {
        $this->authorizeReservation($reservation);

        if (! $reservation->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya reservasi dengan status pending yang bisa dikonfirmasi',
            ], 422);
        }

        $reservation->confirm();

        return response()->json([
            'success' => true,
            'message' => 'Reservasi berhasil dikonfirmasi',
            'data' => $reservation->fresh(),
        ]);
    }

    /**
     * Cancel a reservation
     */
    public function cancel(Request $request, Reservation $reservation): JsonResponse
    {
        $this->authorizeReservation($reservation);

        if ($reservation->isCancelled() || $reservation->isCompleted()) {
            return response()->json([
                'success' => false,
                'message' => 'Reservasi tidak dapat dibatalkan',
            ], 422);
        }

        $reason = $request->input('reason');
        $reservation->cancel($reason);

        return response()->json([
            'success' => true,
            'message' => 'Reservasi berhasil dibatalkan',
            'data' => $reservation->fresh(),
        ]);
    }

    /**
     * Mark reservation as completed
     */
    public function complete(Reservation $reservation): JsonResponse
    {
        $this->authorizeReservation($reservation);

        if (! $reservation->isConfirmed()) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya reservasi yang sudah dikonfirmasi yang bisa diselesaikan',
            ], 422);
        }

        $reservation->complete();

        return response()->json([
            'success' => true,
            'message' => 'Reservasi berhasil diselesaikan',
            'data' => $reservation->fresh(),
        ]);
    }

    /**
     * Mark reservation as no-show
     */
    public function noShow(Reservation $reservation): JsonResponse
    {
        $this->authorizeReservation($reservation);

        if (! $reservation->isConfirmed()) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya reservasi yang sudah dikonfirmasi yang bisa ditandai no-show',
            ], 422);
        }

        $reservation->markNoShow();

        return response()->json([
            'success' => true,
            'message' => 'Reservasi ditandai sebagai no-show',
            'data' => $reservation->fresh(),
        ]);
    }

    /**
     * Get reservation statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $userId = auth()->user()->getEffectiveUserId();

        $stats = [
            'today' => Reservation::where('user_id', $userId)->today()->count(),
            'upcoming' => Reservation::where('user_id', $userId)->upcoming()->count(),
            'pending' => Reservation::where('user_id', $userId)->pending()->count(),
            'confirmed' => Reservation::where('user_id', $userId)->confirmed()->count(),
            'total_this_month' => Reservation::where('user_id', $userId)
                ->whereMonth('reservation_date', now()->month)
                ->whereYear('reservation_date', now()->year)
                ->count(),
            'completed_this_month' => Reservation::where('user_id', $userId)
                ->whereMonth('reservation_date', now()->month)
                ->whereYear('reservation_date', now()->year)
                ->where('status', 'completed')
                ->count(),
        ];

        // Today's reservations by time
        $todayByTime = Reservation::where('user_id', $userId)
            ->today()
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('reservation_time')
            ->get(['id', 'customer_name', 'reservation_time', 'guest_count', 'status']);

        return response()->json([
            'success' => true,
            'data' => [
                'statistics' => $stats,
                'today_reservations' => $todayByTime,
            ],
        ]);
    }

    // ==================== WhatsApp Flow Methods ====================

    /**
     * List all WhatsApp Flows
     */
    public function listFlows(): JsonResponse
    {
        $flows = $this->flowService->listFlows(auth()->user()->getEffectiveUserId());

        return response()->json([
            'success' => true,
            'data' => $flows,
        ]);
    }

    /**
     * Create a new reservation flow
     */
    public function createFlow(): JsonResponse
    {
        try {
            $result = $this->flowService->createReservationFlow(auth()->user()->getEffectiveUserId());

            return response()->json([
                'success' => true,
                'message' => 'Flow berhasil dibuat',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send reservation flow to a customer
     */
    public function sendFlow(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string',
            'flow_id' => 'required|string',
        ]);

        try {
            $result = $this->flowService->sendReservationFlow(
                auth()->user()->getEffectiveUserId(),
                $validated['phone'],
                $validated['flow_id']
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

    /**
     * Publish a flow
     */
    public function publishFlow(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'flow_id' => 'required|string',
        ]);

        $success = $this->flowService->publishFlow(auth()->user()->getEffectiveUserId(), $validated['flow_id']);

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => 'Flow berhasil dipublish',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Gagal mempublish flow',
        ], 500);
    }

    /**
     * Delete a flow
     */
    public function deleteFlow(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'flow_id' => 'required|string',
        ]);

        $success = $this->flowService->deleteFlow(auth()->user()->getEffectiveUserId(), $validated['flow_id']);

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => 'Flow berhasil dihapus',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Gagal menghapus flow',
        ], 500);
    }

    /**
     * Authorize that the user owns the reservation
     */
    private function authorizeReservation(Reservation $reservation): void
    {
        if ($reservation->user_id !== auth()->user()->getEffectiveUserId()) {
            abort(403, 'Unauthorized access to reservation');
        }
    }
}
