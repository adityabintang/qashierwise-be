<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Get available payment methods.
     */
    public function methods(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                ['value' => Payment::METHOD_CASH, 'label' => 'Cash'],
                ['value' => Payment::METHOD_CARD, 'label' => 'Card'],
                ['value' => Payment::METHOD_TRANSFER, 'label' => 'Bank Transfer'],
                ['value' => Payment::METHOD_QRIS, 'label' => 'QRIS'],
                ['value' => Payment::METHOD_OTHER, 'label' => 'Other'],
            ],
        ]);
    }

    /**
     * Process a payment for an order.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'method' => 'required|in:cash,card,transfer,qris,other',
            'amount' => 'required|numeric|min:0.01',
            'reference' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
        ]);

        try {
            $order = Order::findOrFail($validated['order_id']);
            $payment = $this->paymentService->processPayment($order, $validated, true);

            $change = 0;
            if ($validated['method'] === Payment::METHOD_CASH) {
                $remainingBefore = $this->paymentService->getRemainingAmount($order) + $validated['amount'];
                if ($validated['amount'] >= $remainingBefore) {
                    $change = $this->paymentService->calculateChange($validated['amount'], $remainingBefore);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment processed successfully',
                'data' => [
                    'payment' => $payment,
                    'order' => $order->fresh()->load(['payments']),
                    'change' => $change,
                    'remaining' => $this->paymentService->getRemainingAmount($order->fresh()),
                    'is_fully_paid' => $this->paymentService->isFullyPaid($order->fresh()),
                ],
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Process split payment for an order.
     */
    public function splitPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'payments' => 'required|array|min:1',
            'payments.*.method' => 'required|in:cash,card,transfer,qris,other',
            'payments.*.amount' => 'required|numeric|min:0.01',
            'payments.*.reference' => 'nullable|string|max:255',
            'payments.*.metadata' => 'nullable|array',
        ]);

        try {
            $order = Order::findOrFail($validated['order_id']);
            $payments = $this->paymentService->splitPayment($order, $validated['payments'], true);

            return response()->json([
                'success' => true,
                'message' => 'Split payment processed successfully',
                'data' => [
                    'payments' => $payments,
                    'order' => $order->fresh()->load(['payments']),
                    'remaining' => $this->paymentService->getRemainingAmount($order->fresh()),
                    'is_fully_paid' => $this->paymentService->isFullyPaid($order->fresh()),
                ],
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Calculate change for cash payment.
     */
    public function calculateChange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount_paid' => 'required|numeric|min:0',
            'order_total' => 'required|numeric|min:0',
        ]);

        try {
            $change = $this->paymentService->calculateChange(
                $validated['amount_paid'],
                $validated['order_total']
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'amount_paid' => $validated['amount_paid'],
                    'order_total' => $validated['order_total'],
                    'change' => $change,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
