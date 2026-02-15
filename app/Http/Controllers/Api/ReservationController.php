<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReservationController extends Controller
{
    public function __construct(
        protected ReservationService $reservationService
    ) {}

    /**
     * Display a listing of reservations.
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

        // Use effective user ID (master admin ID for sub-accounts)
        $effectiveUserId = $user->getEffectiveUserId();

        $perPage = $request->input('per_page', 20);
        $statusFilter = $request->input('status');

        // Build query
        $reservations = Reservation::with(['table', 'store', 'qrisTransaction'])
            ->where('user_id', $effectiveUserId)
            ->when($request->input('store_id'), fn ($q, $storeId) => $q->where('store_id', $storeId))
            ->when($statusFilter, function ($query) use ($statusFilter) {
                if ($statusFilter === 'dp_confirmed') {
                    return $query
                        ->where('status', Reservation::STATUS_CONFIRMED)
                        ->where('payment_type', Reservation::PAYMENT_TYPE_DP);
                }

                return $query->where('status', $statusFilter);
            })
            ->when($request->input('date'), fn ($q, $date) => $q->whereDate('reservation_date', $date))
            ->when($request->input('search'), fn ($q, $search) => $q->where(function ($query) use ($search) {
                $query->where('order_id', 'like', '%'.$search.'%')
                    ->orWhere('customer_name', 'like', '%'.$search.'%')
                    ->orWhere('customer_phone', 'like', '%'.$search.'%')
                    ->orWhere('customer_email', 'like', '%'.$search.'%');
            })
            )
            ->orderBy($request->input('sort_by', 'reservation_date'), $request->input('sort_order', 'desc'))
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ReservationResource::collection($reservations),
            'meta' => [
                'current_page' => $reservations->currentPage(),
                'last_page' => $reservations->lastPage(),
                'per_page' => $reservations->perPage(),
                'total' => $reservations->total(),
            ],
        ]);
    }

    /**
     * Display the specified reservation.
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

        $reservation = Reservation::with(['table', 'store', 'qrisTransaction'])
            ->where('user_id', $effectiveUserId)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new ReservationResource($reservation),
        ]);
    }

    /**
     * Mark reservation as completed (for day-of completion).
     */
    public function complete(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $effectiveUserId = $user->getEffectiveUserId();

        $reservation = Reservation::where('user_id', $effectiveUserId)->findOrFail($id);

        if ($reservation->status !== Reservation::STATUS_CONFIRMED) {
            return response()->json([
                'success' => false,
                'message' => __('dashboard.reservation.only_confirmed_can_be_completed'),
            ], 422);
        }

        try {
            $this->reservationService->completeReservation($reservation);

            return response()->json([
                'success' => true,
                'message' => __('dashboard.reservation.completed_successfully'),
                'data' => new ReservationResource($reservation->fresh()),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to complete reservation', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('dashboard.reservation.complete_failed'),
            ], 500);
        }
    }

    /**
     * Cancel the specified reservation.
     */
    public function cancel(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $effectiveUserId = $user->getEffectiveUserId();

        $reservation = Reservation::where('user_id', $effectiveUserId)->findOrFail($id);

        if (! $reservation->canBeCancelled()) {
            return response()->json([
                'success' => false,
                'message' => __('dashboard.reservation.cannot_be_cancelled'),
            ], 422);
        }

        try {
            $this->reservationService->cancelReservation($reservation, $validated['reason'] ?? null);

            return response()->json([
                'success' => true,
                'message' => __('dashboard.reservation.cancelled_successfully'),
                'data' => new ReservationResource($reservation->fresh()),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to cancel reservation', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('dashboard.reservation.cancel_failed'),
            ], 500);
        }
    }

    /**
     * Get reservations for calendar view.
     */
    public function calendar(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $effectiveUserId = $user->getEffectiveUserId();

        // Get date range from request (default to current month)
        $startDate = $request->input('start', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end', now()->endOfMonth()->toDateString());

        $reservations = Reservation::with(['table', 'store'])
            ->where('user_id', $effectiveUserId)
            ->whereDate('reservation_date', '>=', $startDate)
            ->whereDate('reservation_date', '<=', $endDate)
            ->whereIn('status', [Reservation::STATUS_CONFIRMED, Reservation::STATUS_COMPLETED])
            ->get();

        // Format for calendar (FullCalendar format)
        $events = $reservations->map(function ($reservation) {
            $guestCount = $reservation->table?->capacity ?? $reservation->guest_count;
            $reservationTime = $reservation->reservation_time ?? null;
            $startDateTime = null;
            $timeDisplay = 'Waktu belum ditentukan';

            if ($reservationTime) {
                // Parse time in Asia/Jakarta timezone (WIB)
                if (is_string($reservationTime) && preg_match('/^\d{2}:\d{2}/', $reservationTime)) {
                    // Ensure we have a date string (not a Carbon object)
                    $dateString = $reservation->reservation_date instanceof \Carbon\Carbon
                        ? $reservation->reservation_date->toDateString()
                        : $reservation->reservation_date;
                    $startDateTime = Carbon::parse($dateString.' '.$reservationTime, 'Asia/Jakarta');
                } else {
                    $startDateTime = Carbon::parse($reservationTime, 'Asia/Jakarta');
                }

                $timeDisplay = $startDateTime->format('H:i').' WIB';
            }

            $tableNumber = $reservation->table?->number;
            $tableLabel = $tableNumber ? 'Meja '.$tableNumber : null;

            // Determine status label and color based on payment type
            $isDP = $reservation->status === Reservation::STATUS_CONFIRMED && $reservation->payment_type === Reservation::PAYMENT_TYPE_DP;
            $statusLabel = match ($reservation->status) {
                Reservation::STATUS_PENDING_PAYMENT => 'Menunggu Pembayaran',
                Reservation::STATUS_CONFIRMED => $isDP ? 'DP Confirmed' : 'Confirmed',
                Reservation::STATUS_COMPLETED => 'Selesai',
                Reservation::STATUS_CANCELLED => 'Dibatalkan',
                default => ucfirst($reservation->status),
            };

            return [
                'id' => $reservation->id,
                'title' => $reservation->customer_name.' ('.$guestCount.' tamu)',
                'start' => $startDateTime ? $startDateTime->toIso8601String() : $reservation->reservation_date,
                'backgroundColor' => match (true) {
                    $isDP => '#06b6d4',  // cyan for DP
                    $reservation->status === Reservation::STATUS_CONFIRMED => '#3b82f6',  // blue for full payment
                    $reservation->status === Reservation::STATUS_COMPLETED => '#10b981',  // green
                    $reservation->status === Reservation::STATUS_CANCELLED => '#ef4444',  // red
                    default => '#f59e0b',  // amber for pending
                },
                'extendedProps' => [
                    'order_id' => $reservation->order_id,
                    'customer_name' => $reservation->customer_name,
                    'customer_phone' => $reservation->phone,
                    'customer_email' => $reservation->email,
                    'table_name' => $tableLabel,
                    'table_capacity' => $reservation->table?->capacity,
                    'status' => $reservation->status,
                    'payment_type' => $reservation->payment_type,
                    'status_label' => $statusLabel,
                    'guest_count' => $guestCount,
                    'reservation_time' => $startDateTime ? $startDateTime->format('H:i') : null,
                    'time_display' => $timeDisplay,
                    'notes' => $reservation->notes,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    /**
     * Get reservation statistics.
     */
    public function stats(Request $request): JsonResponse
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

        $query = Reservation::where('user_id', $effectiveUserId);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $stats = [
            'pending_payment' => (clone $query)->where('status', Reservation::STATUS_PENDING_PAYMENT)->count(),
            'confirmed' => (clone $query)->where('status', Reservation::STATUS_CONFIRMED)->count(),
            'completed' => (clone $query)->where('status', Reservation::STATUS_COMPLETED)->count(),
            'cancelled' => (clone $query)->where('status', Reservation::STATUS_CANCELLED)->count(),
            'today' => (clone $query)->whereDate('reservation_date', today())->whereIn('status', [Reservation::STATUS_CONFIRMED, Reservation::STATUS_COMPLETED])->count(),
            'upcoming' => (clone $query)->where('reservation_date', '>', today())->where('status', Reservation::STATUS_CONFIRMED)->count(),
            'total_revenue' => (clone $query)->where('status', Reservation::STATUS_COMPLETED)->sum('paid_amount'),
            'booked_capacity' => (clone $query)
                ->whereDate('reservation_date', '>=', today())
                ->whereIn('status', [
                    Reservation::STATUS_PENDING_PAYMENT,
                    Reservation::STATUS_CONFIRMED,
                    Reservation::STATUS_COMPLETED,
                ])
                ->sum('guest_count'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
