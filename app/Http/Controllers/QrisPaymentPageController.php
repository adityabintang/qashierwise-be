<?php

namespace App\Http\Controllers;

use App\Models\QrisTransaction;
use Illuminate\Http\Request;

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

        if (!$transaction) {
            abort(404, 'Transaksi tidak ditemukan');
        }

        // Check if transaction is still valid for payment
        $isExpired = $transaction->isExpired() || $transaction->expires_at < now();
        $isPaid = $transaction->isSettled();
        $isCancelled = $transaction->isCancelled();

        return view('payment.qris', [
            'transaction' => $transaction,
            'isExpired' => $isExpired,
            'isPaid' => $isPaid,
            'isCancelled' => $isCancelled,
        ]);
    }
}
