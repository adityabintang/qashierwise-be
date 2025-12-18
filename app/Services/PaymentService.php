<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentService
{
    /**
     * Process a payment for an order
     *
     * @param  Order  $order  Order to pay for
     * @param  array  $paymentData  Payment data (method, amount, reference, metadata)
     *
     * @throws InvalidArgumentException If payment is invalid
     */
    public function processPayment(Order $order, array $paymentData): Payment
    {
        // Validate order status
        if ($order->status === Order::STATUS_CANCELLED) {
            throw new InvalidArgumentException('Cannot process payment for cancelled order');
        }

        if ($order->status === Order::STATUS_COMPLETED) {
            throw new InvalidArgumentException('Order is already completed');
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

        return DB::transaction(function () use ($order, $paymentData, $amount, $method) {
            // Create payment record
            $payment = Payment::create([
                'order_id' => $order->id,
                'method' => $method,
                'amount' => $amount,
                'reference' => $paymentData['reference'] ?? null,
                'metadata' => $paymentData['metadata'] ?? null,
            ]);

            // Check if order is fully paid
            // Use bccomp for precise decimal comparison to avoid floating-point issues
            $totalPaid = $order->payments()->sum('amount') + $amount;
            $orderTotal = (float) $order->total;

            // bccomp returns 0 if equal, 1 if first > second, -1 if first < second
            if (bccomp((string) $totalPaid, (string) $orderTotal, 2) >= 0) {
                $order->status = Order::STATUS_PAID;
                $order->save();
            }

            return $payment;
        });
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
     * @return Collection Collection of Payment records
     *
     * @throws InvalidArgumentException If payments are invalid
     */
    public function splitPayment(Order $order, array $payments): Collection
    {
        // Validate order status
        if ($order->status === Order::STATUS_CANCELLED) {
            throw new InvalidArgumentException('Cannot process payment for cancelled order');
        }

        if ($order->status === Order::STATUS_COMPLETED) {
            throw new InvalidArgumentException('Order is already completed');
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

        return DB::transaction(function () use ($order, $payments, $totalPaymentAmount, $existingPaymentsTotal) {
            $createdPayments = [];

            foreach ($payments as $paymentData) {
                $payment = Payment::create([
                    'order_id' => $order->id,
                    'method' => $paymentData['method'] ?? Payment::METHOD_CASH,
                    'amount' => (float) $paymentData['amount'],
                    'reference' => $paymentData['reference'] ?? null,
                    'metadata' => $paymentData['metadata'] ?? null,
                ]);
                $createdPayments[] = $payment;
            }

            // Check if order is fully paid
            // Use bccomp for precise decimal comparison to avoid floating-point issues
            $newTotalPaid = $existingPaymentsTotal + $totalPaymentAmount;
            $orderTotal = (float) $order->total;

            // bccomp returns 0 if equal, 1 if first > second, -1 if first < second
            if (bccomp((string) $newTotalPaid, (string) $orderTotal, 2) >= 0) {
                $order->status = Order::STATUS_PAID;
                $order->save();
            }

            return new Collection($createdPayments);
        });
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
