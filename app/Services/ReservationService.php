<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Reservation;
use App\Models\ReservationConfig;
use App\Models\Store;
use App\Models\Table;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReservationService
{
    public function __construct(
        protected QrisService $qrisService,
        protected GoogleCalendarService $calendarService,
    ) {}

    /**
     * Create a new reservation with QRIS payment.
     *
     * @return array ['reservation' => Reservation, 'qris' => QrisTransaction]
     */
    public function createReservation(array $data, User $merchant): array
    {
        return DB::transaction(function () use ($data, $merchant) {
            $selectedProducts = $this->normalizeSelectedProducts($data['selected_products'] ?? null);
            // Get configuration
            $config = ReservationConfig::where('user_id', $merchant->id)
                ->where('store_id', $data['store_id'] ?? null)
                ->where('is_active', true)
                ->first();

            if (! $config) {
                throw new Exception('Reservation system is not configured for this merchant');
            }

            // Validate table availability
            $table = null;
            if (! empty($data['table_id'])) {
                $table = Table::findOrFail($data['table_id']);
                if (! $this->checkTableQuota($table, Carbon::parse($data['reservation_date']))) {
                    throw new Exception('Table is not available for the selected date');
                }
            }

            // Calculate amounts
            $amounts = $this->calculateAmounts($data, $config);

            // Generate unique order ID
            $orderId = 'RSV-'.time().'-'.strtoupper(substr(md5(rand()), 0, 6));

            // Determine payment amount based on type
            $paymentAmount = $data['payment_type'] === Reservation::PAYMENT_TYPE_DP
                ? $amounts['dp_amount']
                : $amounts['total_amount'];

            // Parse reservation datetime
            $reservationDateTime = Carbon::parse($data['reservation_date']);
            $reservationDate = $reservationDateTime->toDateString();

            // Extract time - if no time component, default to 12:00
            $hasTimeComponent = str_contains($data['reservation_date'], 'T') || str_contains($data['reservation_date'], ':');
            $reservationTime = $hasTimeComponent
                ? $reservationDateTime->format('H:i')
                : '12:00';

            // Create reservation record
            $reservation = Reservation::create([
                'user_id' => $merchant->id,
                'store_id' => $data['store_id'] ?? null,
                'customer_name' => $data['customer_name'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'reservation_date' => $reservationDate,
                'reservation_time' => $reservationTime,
                'guest_count' => (int) ($data['guest_count'] ?? 1),
                'table_id' => $data['table_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'selected_products' => $selectedProducts,
                'payment_type' => $data['payment_type'],
                'payment_method' => Reservation::PAYMENT_METHOD_QRIS,
                'total_amount' => $amounts['total_amount'],
                'paid_amount' => 0,
                'remaining_amount' => $amounts['total_amount'],
                'status' => Reservation::STATUS_PENDING_PAYMENT,
                'order_id' => $orderId,
            ]);

            // Get merchant's sub-merchant account for QRIS
            $subMerchant = $merchant->subMerchant;
            if (! $subMerchant) {
                throw new Exception('Merchant does not have payment account configured');
            }

            // Generate QRIS payment
            $qrisTransaction = $this->qrisService->generateQris(
                $subMerchant,
                $paymentAmount,
                [
                    'description' => "Reservasi - {$reservation->customer_name}",
                    'customer_name' => $reservation->customer_name,
                    'customer_phone' => $reservation->phone,
                    'customer_email' => $reservation->email,
                    'reservation_id' => $reservation->id,
                    'order_id' => $orderId,
                ]
            );

            // Link QRIS transaction to reservation
            $reservation->update([
                'qris_transaction_id' => $qrisTransaction->id,
            ]);

            Log::info('Reservation created', [
                'reservation_id' => $reservation->id,
                'order_id' => $orderId,
                'customer' => $reservation->customer_name,
                'amount' => $paymentAmount,
            ]);

            return [
                'reservation' => $reservation->fresh(['qrisTransaction', 'table']),
                'qris' => $qrisTransaction,
            ];
        });
    }

    /**
     * Confirm reservation after successful payment.
     */
    public function confirmReservation(Reservation $reservation): void
    {
        DB::transaction(function () use ($reservation) {
            // Update reservation status
            $reservation->update([
                'status' => Reservation::STATUS_CONFIRMED,
                'notified_at' => now(),
            ]);

            // Update table status to reserved
            if ($reservation->table_id) {
                $reservation->table->update([
                    'status' => Table::STATUS_RESERVED,
                ]);
            }

            // Create Google Calendar event
            try {
                if ($reservation->user->google_calendar_refresh_token) {
                    $this->calendarService->setAccessToken($reservation->user->google_calendar_refresh_token);
                }

                $eventId = $this->calendarService->createEvent($reservation);
                $reservation->update(['calendar_event_id' => $eventId]);
            } catch (Exception $e) {
                Log::error('Failed to create calendar event for reservation', [
                    'reservation_id' => $reservation->id,
                    'error' => $e->getMessage(),
                ]);
                // Don't fail the confirmation if calendar creation fails
            }

            Log::info('Reservation confirmed', [
                'reservation_id' => $reservation->id,
                'order_id' => $reservation->order_id,
            ]);
        });
    }

    /**
     * Complete reservation (when customer arrives and settles).
     */
    public function completeReservation(Reservation $reservation): void
    {
        DB::transaction(function () use ($reservation) {
            $reservation->update([
                'status' => Reservation::STATUS_COMPLETED,
            ]);

            // Free the table
            if ($reservation->table_id) {
                $reservation->table->update([
                    'status' => Table::STATUS_AVAILABLE,
                ]);
            }

            Log::info('Reservation completed', [
                'reservation_id' => $reservation->id,
                'order_id' => $reservation->order_id,
            ]);
        });
    }

    /**
     * Cancel a reservation.
     */
    public function cancelReservation(Reservation $reservation, string $reason): void
    {
        DB::transaction(function () use ($reservation, $reason) {
            $reservation->update([
                'status' => Reservation::STATUS_CANCELLED,
                'cancelled_reason' => $reason,
            ]);

            // Free the table
            if ($reservation->table_id) {
                $reservation->table->update([
                    'status' => Table::STATUS_AVAILABLE,
                ]);
            }

            // Delete calendar event
            if ($reservation->calendar_event_id) {
                try {
                    if ($reservation->user->google_calendar_refresh_token) {
                        $this->calendarService->setAccessToken($reservation->user->google_calendar_refresh_token);
                    }
                    $this->calendarService->deleteEvent($reservation->calendar_event_id);
                } catch (Exception $e) {
                    Log::error('Failed to delete calendar event', [
                        'reservation_id' => $reservation->id,
                        'event_id' => $reservation->calendar_event_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Reservation cancelled', [
                'reservation_id' => $reservation->id,
                'order_id' => $reservation->order_id,
                'reason' => $reason,
            ]);
        });
    }

    /**
     * Get available tables for a specific date.
     */
    public function getAvailableTables(Store $store, Carbon $date): Collection
    {
        // Get all tables for the store
        $allTables = Table::where('store_id', $store->id)
            ->where('status', '!=', Table::STATUS_UNAVAILABLE)
            ->get();

        // Get reserved tables for the date
        $reservedTableIds = Reservation::where('store_id', $store->id)
            ->whereDate('reservation_date', $date)
            ->whereIn('status', [Reservation::STATUS_CONFIRMED, Reservation::STATUS_PENDING_PAYMENT])
            ->pluck('table_id')
            ->toArray();

        // Filter out reserved tables
        return $allTables->filter(function ($table) use ($reservedTableIds) {
            return ! in_array($table->id, $reservedTableIds);
        });
    }

    /**
     * Check if a table is available for a specific date.
     */
    public function checkTableQuota(Table $table, Carbon $date): bool
    {
        $existingReservation = Reservation::where('table_id', $table->id)
            ->whereDate('reservation_date', $date)
            ->whereIn('status', [
                Reservation::STATUS_CONFIRMED,
                Reservation::STATUS_PENDING_PAYMENT,
            ])
            ->exists();

        return ! $existingReservation;
    }

    /**
     * Calculate total amount, DP amount, and remaining amount.
     */
    public function calculateAmounts(array $data, ReservationConfig $config): array
    {
        $baseFee = (float) ($config->reservation_fee ?? 0);
        $productTotal = 0;
        $selectedProducts = $this->normalizeSelectedProducts($data['selected_products'] ?? null);

        if ($selectedProducts !== []) {
            $productMap = Product::whereIn('id', collect($selectedProducts)->pluck('id'))
                ->get()
                ->keyBy('id');

            $productTotal = collect($selectedProducts)->reduce(function (float $total, array $item) use ($productMap) {
                $product = $productMap->get($item['id']);
                if (! $product) {
                    return $total;
                }

                return $total + ((float) $product->price * $item['quantity']);
            }, 0.0);
        }

        $totalAmount = $baseFee + $productTotal;
        $dpAmount = $totalAmount * ((float) $config->dp_percentage / 100);
        $remainingAmount = $totalAmount - $dpAmount;

        return [
            'total_amount' => $totalAmount,
            'dp_amount' => $dpAmount,
            'remaining_amount' => $remainingAmount,
        ];
    }

    /**
     * @param  array<int, int|array{id:int, quantity?:int}>|null  $selectedProducts
     * @return array<int, array{id:int, quantity:int}>
     */
    private function normalizeSelectedProducts(?array $selectedProducts): array
    {
        return collect($selectedProducts ?? [])
            ->map(function ($item) {
                if (is_array($item)) {
                    $id = isset($item['id']) ? (int) $item['id'] : 0;
                    $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 1;

                    if ($id <= 0) {
                        return null;
                    }

                    return [
                        'id' => $id,
                        'quantity' => max(1, $quantity),
                    ];
                }

                if (is_numeric($item)) {
                    $id = (int) $item;

                    if ($id <= 0) {
                        return null;
                    }

                    return [
                        'id' => $id,
                        'quantity' => 1,
                    ];
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Get reservation by order ID.
     */
    public function getReservationByOrderId(string $orderId): ?Reservation
    {
        return Reservation::where('order_id', $orderId)
            ->with(['table', 'qrisTransaction', 'user', 'store'])
            ->first();
    }
}
