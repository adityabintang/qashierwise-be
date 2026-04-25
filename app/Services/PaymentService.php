<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PlatformFee;
use App\Models\QrisTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PaymentService
{
    public function __construct(
        protected OrderService $orderService,
        protected SubMerchantService $subMerchantService,
        protected BalanceService $balanceService,
        protected QrisService $qrisService
    ) {}

    /**
     * Process a payment for an order
     *
     * @param  Order  $order  Order to pay for
     * @param  array  $paymentData  Payment data (method, amount, reference, metadata)
     * @param  bool  $autoComplete  Whether to auto-complete order after full payment
     *
     * @throws InvalidArgumentException If payment is invalid
     */
    public function processPayment(Order $order, array $paymentData, bool $autoComplete = false): Payment
    {
        // Validate order status
        if ($order->status === Order::STATUS_CANCELLED) {
            throw new InvalidArgumentException('Cannot process payment for cancelled order');
        }

        if ($order->status === Order::STATUS_PAID) {
            throw new InvalidArgumentException('Order is already paid');
        }

        // Validate payment amount
        $amount = (float) ($paymentData['amount'] ?? 0);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be positive');
        }

        // Validate payment method
        $validMethods = [
            Payment::METHOD_CASH,
            Payment::METHOD_CARD,
            Payment::METHOD_TRANSFER,
            Payment::METHOD_QRIS,
            Payment::METHOD_OTHER,
        ];

        $method = $paymentData['method'] ?? Payment::METHOD_CASH;
        if (! in_array($method, $validMethods)) {
            throw new InvalidArgumentException('Invalid payment method');
        }

        return DB::transaction(function () use ($order, $paymentData, $amount, $method, $autoComplete) {
            $qrisTransaction = null;

            // If payment method is QRIS, generate QRIS code first
            if ($method === Payment::METHOD_QRIS) {
                $qrisTransaction = $this->generateQrisForPayment($order, $amount, $paymentData);
            }

            // Create payment record
            $payment = Payment::create([
                'order_id' => $order->id,
                'method' => $method,
                'amount' => $amount,
                'reference' => $paymentData['reference'] ?? null,
                'metadata' => $paymentData['metadata'] ?? null,
                'qris_transaction_id' => $qrisTransaction?->id,
            ]);

            // Check if order is fully paid
            // Use bccomp for precise decimal comparison to avoid floating-point issues
            $totalPaid = $order->payments()->sum('amount') + $amount;
            $orderTotal = (float) $order->total;

            // bccomp returns 0 if equal, 1 if first > second, -1 if first < second
            if (bccomp((string) $totalPaid, (string) $orderTotal, 2) >= 0) {
                if ($autoComplete) {
                    // Auto-complete order when fully paid (from payment menu)
                    $order->refresh();
                    $this->orderService->complete($order);
                } else {
                    // Just mark as paid (e.g., from external QRIS payment)
                    $order->status = Order::STATUS_PAID;
                    $order->save();
                }

                // If payment method is QRIS and payment was created via POS, create QrisTransaction
                if ($method === Payment::METHOD_QRIS && $autoComplete && ! $qrisTransaction) {
                    $this->createQrisTransactionForPayment($order, $payment);
                }
            }

            return $payment;
        });
    }

    /**
     * Generate QRIS code for a payment.
     *
     * @param  Order  $order  The order
     * @param  float  $amount  Payment amount
     * @param  array  $paymentData  Additional payment data
     * @return QrisTransaction The generated QRIS transaction
     *
     * @throws InvalidArgumentException If QRIS generation fails
     */
    protected function generateQrisForPayment(Order $order, float $amount, array $paymentData): QrisTransaction
    {
        try {
            $store = $order->store;
            $user = $store->user;

            $subMerchant = $this->subMerchantService->findByUserId($user->id);

            if (! $subMerchant) {
                throw new InvalidArgumentException('Sub-merchant not found for this user');
            }

            if (! $subMerchant->is_active) {
                throw new InvalidArgumentException('Sub-merchant is not active');
            }

            // Generate QRIS via service
            $qrisTransaction = $this->qrisService->generateQris(
                $subMerchant,
                $amount,
                [
                    'description' => "Order {$order->order_number}",
                    'customer_name' => $order->customer_name,
                    'customer_email' => $paymentData['customer_email'] ?? null,
                ]
            );

            // Link QRIS transaction to order
            $qrisTransaction->update(['linked_order_id' => $order->id]);

            Log::info('QRIS generated for POS payment', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'qris_transaction_id' => $qrisTransaction->id,
                'amount' => $amount,
            ]);

            return $qrisTransaction;
        } catch (\Exception $e) {
            Log::error('Failed to generate QRIS for payment', [
                'order_id' => $order->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            throw new InvalidArgumentException('Failed to generate QRIS: ' . $e->getMessage());
        }
    }

    /**
     * Calculate change for a cash payment
     *
     * @param  float  $amountPaid  Amount paid by customer
     * @param  float  $orderTotal  Order total amount
     * @return float Change to return
     *
     * @throws InvalidArgumentException If amount paid is less than order total
     */
    public function calculateChange(float $amountPaid, float $orderTotal): float
    {
        if ($amountPaid < 0) {
            throw new InvalidArgumentException('Amount paid cannot be negative');
        }

        if ($orderTotal < 0) {
            throw new InvalidArgumentException('Order total cannot be negative');
        }

        if ($amountPaid < $orderTotal) {
            throw new InvalidArgumentException('Amount paid is less than order total');
        }

        return round($amountPaid - $orderTotal, 2);
    }

    /**
     * Process split payment across multiple methods
     *
     * @param  Order  $order  Order to pay for
     * @param  array  $payments  Array of payment data [{method, amount, reference?, metadata?}, ...]
     * @param  bool  $autoComplete  Whether to auto-complete order after full payment
     * @return Collection Collection of Payment records
     *
     * @throws InvalidArgumentException If payments are invalid
     */
    public function splitPayment(Order $order, array $payments, bool $autoComplete = false): Collection
    {
        // Validate order status
        if ($order->status === Order::STATUS_CANCELLED) {
            throw new InvalidArgumentException('Cannot process payment for cancelled order');
        }

        if ($order->status === Order::STATUS_PAID) {
            throw new InvalidArgumentException('Order is already paid');
        }

        if (empty($payments)) {
            throw new InvalidArgumentException('At least one payment is required');
        }

        // Calculate total of all payments
        $totalPaymentAmount = 0;
        foreach ($payments as $paymentData) {
            $amount = (float) ($paymentData['amount'] ?? 0);
            if ($amount <= 0) {
                throw new InvalidArgumentException('Each payment amount must be positive');
            }
            $totalPaymentAmount += $amount;
        }

        // Get existing payments total
        $existingPaymentsTotal = (float) $order->payments()->sum('amount');
        $remainingAmount = (float) $order->total - $existingPaymentsTotal;

        // Validate total payment covers remaining amount (using tolerance for floating-point comparison)
        $tolerance = 0.001; // 0.1 cent tolerance for floating-point precision
        if ($totalPaymentAmount < $remainingAmount - $tolerance) {
            throw new InvalidArgumentException(
                "Total payment amount ({$totalPaymentAmount}) is less than remaining order amount ({$remainingAmount})"
            );
        }

        return DB::transaction(function () use ($order, $payments, $totalPaymentAmount, $existingPaymentsTotal, $autoComplete) {
            $createdPayments = [];

            foreach ($payments as $paymentData) {
                $qrisTransaction = null;

                // If payment method is QRIS, generate QRIS code first
                if ($paymentData['method'] === Payment::METHOD_QRIS) {
                    $qrisTransaction = $this->generateQrisForPayment($order, (float) $paymentData['amount'], $paymentData);
                }

                $payment = Payment::create([
                    'order_id' => $order->id,
                    'method' => $paymentData['method'] ?? Payment::METHOD_CASH,
                    'amount' => (float) $paymentData['amount'],
                    'reference' => $paymentData['reference'] ?? null,
                    'metadata' => $paymentData['metadata'] ?? null,
                    'qris_transaction_id' => $qrisTransaction?->id,
                ]);
                $createdPayments[] = $payment;
            }

            // Check if order is fully paid
            // Use bccomp for precise decimal comparison to avoid floating-point issues
            $newTotalPaid = $existingPaymentsTotal + $totalPaymentAmount;
            $orderTotal = (float) $order->total;

            // bccomp returns 0 if equal, 1 if first > second, -1 if first < second
            if (bccomp((string) $newTotalPaid, (string) $orderTotal, 2) >= 0) {
                if ($autoComplete) {
                    // Auto-complete order when fully paid (from payment menu)
                    $order->refresh();
                    $this->orderService->complete($order);
                } else {
                    // Just mark as paid (e.g., from external QRIS payment)
                    $order->status = Order::STATUS_PAID;
                    $order->save();
                }

                // Check if any payment method is QRIS and create QRIS transaction (for backward compatibility)
                $qrisPayment = null;
                foreach ($createdPayments as $createdPayment) {
                    if ($createdPayment->method === Payment::METHOD_QRIS && ! $createdPayment->qris_transaction_id) {
                        $qrisPayment = $createdPayment;
                        break;
                    }
                }

                if ($qrisPayment) {
                    $this->createQrisTransactionForPayment($order, $qrisPayment);
                }
            }

            return new Collection($createdPayments);
        });
    }

    /**
     * Create QRIS transaction for payment made via POS.
     *
     * @param  Order  $order  The order
     * @param  Payment  $payment  The payment record
     */
    protected function createQrisTransactionForPayment(Order $order, Payment $payment): void
    {
        try {
            // Get order's store owner
            $store = $order->store;
            $user = $store->user;

            // Find sub-merchant for this user
            $subMerchant = $this->subMerchantService->findByUserId($user->id);

            if (! $subMerchant) {
                Log::info('No sub-merchant found for user, skipping QRIS transaction creation', [
                    'order_id' => $order->id,
                    'user_id' => $user->id,
                ]);

                return;
            }

            // Calculate platform fee
            $platformFee = QrisTransaction::calculatePlatformFee($payment->amount);
            $netAmount = QrisTransaction::calculateNetAmount($payment->amount);

            // Create QRIS transaction record linked to order
            $qrisTransaction = QrisTransaction::create([
                'sub_merchant_id' => $subMerchant->id,
                'order_id' => $order->order_number,
                'amount' => $payment->amount,
                'platform_fee' => $platformFee,
                'net_amount' => $netAmount,
                'status' => QrisTransaction::STATUS_SETTLEMENT,
                'provider' => 'pos',
                'provider_transaction_id' => $payment->id,
                'paid_at' => now(),
                'settled_at' => now(),
                'created_at' => $order->created_at,
            ]);

            // Link QRIS transaction to payment
            $payment->update([
                'qris_transaction_id' => $qrisTransaction->id,
            ]);

            // Update order with linked order_id to QRIS transaction
            $qrisTransaction->update(['linked_order_id' => $order->id]);

            // Process payment success to update sub-merchant balance
            $this->balanceService->processPaymentSuccess($qrisTransaction);

            // Create platform fee record
            PlatformFee::createForTransaction($qrisTransaction);

            Log::info('QRIS transaction created for POS payment', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_id' => $payment->id,
                'sub_merchant_id' => $subMerchant->id,
                'amount' => $payment->amount,
                'platform_fee' => $platformFee,
                'net_amount' => $netAmount,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create QRIS transaction for POS payment', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Get total amount paid for an order
     *
     * @param  Order  $order  Order to check
     * @return float Total amount paid
     */
    public function getTotalPaid(Order $order): float
    {
        return (float) $order->payments()->sum('amount');
    }

    /**
     * Get remaining amount to be paid for an order
     *
     * @param  Order  $order  Order to check
     * @return float Remaining amount
     */
    public function getRemainingAmount(Order $order): float
    {
        $totalPaid = $this->getTotalPaid($order);

        return max(0, (float) $order->total - $totalPaid);
    }

    /**
     * Check if an order is fully paid
     *
     * @param  Order  $order  Order to check
     * @return bool True if fully paid
     */
    public function isFullyPaid(Order $order): bool
    {
        $totalPaid = $this->getTotalPaid($order);
        $orderTotal = (float) $order->total;

        // Use bccomp for precise decimal comparison to avoid floating-point issues
        // bccomp returns 0 if equal, 1 if first > second, -1 if first < second
        return bccomp((string) $totalPaid, (string) $orderTotal, 2) >= 0;
    }

    /**
     * Get payments for an order
     *
     * @param  Order  $order  Order to get payments for
     * @return Collection Collection of Payment records
     */
    public function getPayments(Order $order): Collection
    {
        return $order->payments()->get();
    }
}
