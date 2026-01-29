<?php

namespace App\Http\Controllers;

use App\Models\QrisTransaction;

class QrisPaymentPageController extends Controller
{
    /**
     * Display the public QRIS payment page.
     */
    public function show(string $orderId)
    {
        $transaction = QrisTransaction::with('subMerchant')
            ->where('order_id', $orderId)
            ->first();

        if (! $transaction) {
            abort(404, 'Transaksi tidak ditemukan');
        }

        // Check if transaction is still valid for payment
        $isExpired = $transaction->status === QrisTransaction::STATUS_EXPIRE || $transaction->isExpired();
        $isPaid = $transaction->isSettled();
        $isCancelled = $transaction->status === QrisTransaction::STATUS_CANCEL;

        return view('payment.qris', [
            'transaction' => $transaction,
            'isExpired' => $isExpired,
            'isPaid' => $isPaid,
            'isCancelled' => $isCancelled,
        ]);
    }
}
