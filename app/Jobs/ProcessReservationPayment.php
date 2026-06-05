<?php

namespace App\Jobs;

use App\Models\CatalogProduct;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\QrisTransaction;
use App\Models\Reservation;
use App\Services\OrderService;
use App\Services\ReservationFulfillmentService;
use App\Services\ReservationService;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Job for processing reservation payments after QRIS webhook.
 *
 * This job handles:
 * - Updating reservation payment status
 * - Confirming reservation (creating calendar event, updating table)
 * - Dispatching notification jobs
 */
class ProcessReservationPayment implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [10, 30, 60];

    /**
     * The reservation to process.
     */
    protected Reservation $reservation;

    /**
     * The payment status (success or failed).
     */
    protected string $paymentStatus;

    /**
     * The QRIS transaction.
     */
    protected ?QrisTransaction $qrisTransaction;

    /**
     * Create a new job instance.
     *
     * @param  string  $paymentStatus  'success' or 'failed'
     */
    public function __construct(Reservation $reservation, string $paymentStatus, ?QrisTransaction $qrisTransaction = null)
    {
        $this->reservation = $reservation;
        $this->paymentStatus = $paymentStatus;
        $this->qrisTransaction = $qrisTransaction;
    }

    /**
     * Execute the job.
     */
    public function handle(ReservationService $reservationService, OrderService $orderService, ReservationFulfillmentService $fulfillment): void
    {
        Log::info('ProcessReservationPayment job started', [
            'reservation_id' => $this->reservation->id,
            'order_id' => $this->reservation->order_id,
            'status' => $this->paymentStatus,
        ]);

        try {
            // Refresh reservation to get latest state
            $this->reservation->refresh();

            // Process based on payment status
            if ($this->paymentStatus === 'success') {
                $this->handleSuccessfulPayment($reservationService, $orderService, $fulfillment);
            } else {
                $this->handleFailedPayment($reservationService);
            }

            Log::info('ProcessReservationPayment job completed', [
                'reservation_id' => $this->reservation->id,
                'order_id' => $this->reservation->order_id,
            ]);
        } catch (Exception $e) {
            Log::error('ProcessReservationPayment job failed', [
                'reservation_id' => $this->reservation->id,
                'order_id' => $this->reservation->order_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle successful payment.
     */
    protected function handleSuccessfulPayment(ReservationService $reservationService, OrderService $orderService, ReservationFulfillmentService $fulfillment): void
    {
        // Skip if already confirmed
        if ($this->reservation->status === Reservation::STATUS_CONFIRMED) {
            Log::info('Reservation already confirmed, skipping', [
                'reservation_id' => $this->reservation->id,
            ]);

            return;
        }

        // Calculate paid amount
        $paidAmount = $this->reservation->payment_type === Reservation::PAYMENT_TYPE_DP
            ? $this->reservation->calculateDpAmount()
            : $this->reservation->total_amount;

        // Update payment amounts
        $this->reservation->update([
            'paid_amount' => $paidAmount,
            'remaining_amount' => $this->reservation->total_amount - $paidAmount,
        ]);

        // Confirm reservation (creates Google Calendar event on merchant's calendar,
        // schedules WA reminders, updates table status).
        $reservationService->confirmReservation($this->reservation);

        // Create POS Order for dashboard/reporting visibility and invoice generation.
        $posOrder = null;
        try {
            $posOrder = $this->createOrderFromReservation($orderService);
            if ($posOrder) {
                $this->reservation->update(['pos_order_id' => $posOrder->id]);
            }
        } catch (Exception $e) {
            Log::error('Failed to create order from reservation', [
                'reservation_id' => $this->reservation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        // Notify merchant via their own channel (Fonnte / WABA depending on setup).
        try {
            $this->reservation->refresh();
            if ($posOrder) {
                if ($posOrder->delivery_type !== 'reservasi') {
                    $posOrder->setAttribute('delivery_type', 'reservasi');
                }
                app(\App\Services\MerchantNotificationService::class)->notifyNewOrder($posOrder);
            }
        } catch (\Throwable $e) {
            Log::warning('Merchant reservation notif failed (non-fatal)', [
                'reservation_id' => $this->reservation->id,
                'error'          => $e->getMessage(),
            ]);
        }

        // Send customer: confirmation message + invoice PDF via ReservationFulfillmentService.
        // This replaces the old SendReservationNotification job.
        try {
            $this->reservation->refresh();
            $fulfillment->onPaymentConfirmed($this->reservation);
        } catch (\Throwable $e) {
            Log::warning('Reservation customer notification failed (non-fatal)', [
                'reservation_id' => $this->reservation->id,
                'error'          => $e->getMessage(),
            ]);
        }

        Log::info('Reservation payment processed successfully', [
            'reservation_id' => $this->reservation->id,
            'order_id' => $this->reservation->order_id,
            'paid_amount' => $paidAmount,
        ]);
    }

    /**
     * Handle failed payment.
     */
    protected function handleFailedPayment(ReservationService $reservationService): void
    {
        // Cancel the reservation
        $reservationService->cancelReservation(
            $this->reservation,
            'Payment failed or expired'
        );

        // Dispatch failure notification
        SendReservationNotification::dispatch($this->reservation, 'failure');

        Log::warning('Reservation payment failed', [
            'reservation_id' => $this->reservation->id,
            'order_id' => $this->reservation->order_id,
        ]);
    }

    /**
     * Create a POS Order from the reservation for visibility, reporting, and
     * invoice generation. Returns the created Order (or the pre-existing one),
     * or null if creation fails.
     */
    protected function createOrderFromReservation(OrderService $orderService): ?Order
    {
        // Idempotency: if the reservation already has a linked POS Order, return it.
        if ($this->reservation->pos_order_id) {
            $existing = Order::find($this->reservation->pos_order_id);
            if ($existing) {
                return $existing;
            }
        }

        // Also check by QRIS transaction to guard against duplicate job runs.
        if ($this->qrisTransaction) {
            $existingOrder = Order::where('source', 'reservation')
                ->whereHas('payments', fn ($q) => $q->where('qris_transaction_id', $this->qrisTransaction->id))
                ->first();

            if ($existingOrder) {
                Log::info('Order already exists for this reservation payment', [
                    'reservation_id' => $this->reservation->id,
                    'order_id' => $existingOrder->id,
                ]);

                return $existingOrder;
            }
        }

        // Create POS order. delivery_type='reservasi' so the invoice and merchant
        // notification service show the correct label.
        $order = Order::create([
            'store_id'       => $this->reservation->store_id,
            'order_number'   => $this->generateOrderNumber($this->reservation->store_id),
            'status'         => Order::STATUS_PAID,
            'source'         => 'reservation',
            'delivery_type'  => 'reservasi',
            'customer_name'  => $this->reservation->customer_name,
            'customer_phone' => $this->reservation->phone,
            'subtotal'       => 0,
            'tax_amount'     => 0,
            'discount_amount'=> 0,
            'total'          => 0,
        ]);

        // Add items from selected products
        $selectedProducts = $this->reservation->selected_products ?? [];
        $subtotal = 0;

        foreach ($selectedProducts as $productData) {
            $product = $this->resolveProduct($productData);
            if (! $product) {
                Log::warning('Could not resolve catalog product from reservation', [
                    'reservation_id' => $this->reservation->id,
                    'product_data' => $productData,
                ]);

                continue;
            }

            $quantity = $this->getProductQuantity($productData);
            $unitPrice = $this->getProductPrice($productData, $product);
            $itemSubtotal = $quantity * $unitPrice;

            // Reservation products come from the Meta Catalog mirror, which has
            // no master Product row. Snapshot name + retailer_id instead and
            // leave product_id null (column is nullable).
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => null,
                'product_name' => $product->name,
                'product_retailer_id' => $product->retailer_id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $itemSubtotal,
            ]);

            $subtotal += $itemSubtotal;
        }

        // Calculate totals
        $taxAmount = round(($subtotal) * OrderService::DEFAULT_TAX_RATE, 2);
        $total = $subtotal + $taxAmount;

        // Update order totals
        $order->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ]);

        // Create payment record linked to QRIS transaction
        if ($this->qrisTransaction) {
            Payment::create([
                'order_id' => $order->id,
                'qris_transaction_id' => $this->qrisTransaction->id,
                'method' => Payment::METHOD_QRIS,
                'amount' => $total,
                'status' => Payment::STATUS_PAID,
                'paid_at' => now(),
                'reference' => $this->qrisTransaction->provider_transaction_id,
            ]);
        }

        Log::info('Order created from reservation', [
            'reservation_id' => $this->reservation->id,
            'order_id'       => $order->id,
            'order_number'   => $order->order_number,
            'subtotal'       => $subtotal,
            'total'          => $total,
        ]);

        return $order;
    }

    /**
     * Resolve a catalog product from various formats in selected_products.
     *
     * Handles:
     * - Array with 'id' key: ['id' => 1, 'name' => '...']
     * - Array with 'product_id' key: ['product_id' => 1, ...]
     * - Integer: 1
     *
     * The id refers to a `catalog_products` row scoped to the reservation owner.
     */
    protected function resolveProduct(mixed $productData): ?CatalogProduct
    {
        $productId = null;

        if (is_array($productData)) {
            $productId = $productData['id'] ?? $productData['product_id'] ?? null;
        } elseif (is_int($productData)) {
            $productId = $productData;
        }

        if (! $productId) {
            return null;
        }

        return CatalogProduct::where('user_id', $this->reservation->user_id)
            ->find($productId);
    }

    /**
     * Get product quantity from product data.
     *
     * Defaults to 1 if not specified.
     */
    protected function getProductQuantity(mixed $productData): int
    {
        if (is_array($productData)) {
            return $productData['quantity'] ?? $productData['qty'] ?? 1;
        }

        return 1;
    }

    /**
     * Get product price from product data or use the catalog product's price.
     */
    protected function getProductPrice(mixed $productData, CatalogProduct $product): float
    {
        if (is_array($productData)) {
            if (isset($productData['price'])) {
                return (float) $productData['price'];
            }
            if (isset($productData['unit_price'])) {
                return (float) $productData['unit_price'];
            }
        }

        return (float) $product->price;
    }

    /**
     * Generate a unique order number for the store.
     *
     * Uses format: STORE_DATE_SEQUENCE (e.g., STORE1_20250201_001)
     *
     * Uses database locking to prevent race conditions when multiple
     * queue jobs run simultaneously.
     */
    protected function generateOrderNumber(int $storeId): string
    {
        Log::debug('Generating order number for store', [
            'store_id' => $storeId,
            'timestamp' => now()->toIso8601String(),
        ]);

        return DB::transaction(function () use ($storeId) {
            $today = now()->format('Ymd');
            $prefix = "ORD{$storeId}_{$today}_";

            Log::debug('Starting order number transaction', [
                'store_id' => $storeId,
                'prefix' => $prefix,
            ]);

            // Lock existing orders for this store/date to prevent race conditions
            // lockForUpdate() ensures exclusive access until transaction completes
            $latestOrder = Order::where('store_id', $storeId)
                ->where('order_number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderBy('order_number', 'desc')
                ->first();

            // Extract sequence number from existing order or start at 0
            $sequence = 0;
            if ($latestOrder) {
                $lastSequence = str_replace($prefix, '', $latestOrder->order_number);
                $sequence = (int) $lastSequence;
                Log::debug('Found existing order, extracting sequence', [
                    'latest_order_number' => $latestOrder->order_number,
                    'extracted_sequence' => $sequence,
                ]);
            }

            $newOrderNumber = $prefix.str_pad($sequence + 1, 3, '0', STR_PAD_LEFT);

            Log::debug('Generated order number', [
                'store_id' => $storeId,
                'order_number' => $newOrderNumber,
                'new_sequence' => $sequence + 1,
            ]);

            return $newOrderNumber;
        });
    }
}
