<?php

namespace App\Services\AiAgent\Payment;

use App\Models\AiAgentConversation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\QrisTransaction;
use App\Services\QrisService;
use Illuminate\Support\Facades\Log;

/**
 * Standalone QRIS tools exposed to the LLM.
 *
 *   generateQris       — used when the LLM wants to create a payment link
 *                        without going through full confirmOrder (rare, but
 *                        the tool exists for prompt-driven scenarios).
 *   checkPaymentStatus — refreshes the current QRIS transaction and
 *                        translates its status into a user-facing message,
 *                        clearing conversation context on terminal states.
 *
 * CheckoutTools owns the "create order + attach QRIS" happy path; this
 * class owns the operations that touch QRIS without creating a new order.
 */
class PaymentTools
{
    public function __construct(
        protected QrisService $qrisService,
    ) {}

    /**
     * Create a standalone QRIS transaction tied to the current conversation's
     * order (if any). Returns the customer-facing instructions.
     */
    public function generateQris(
        AiAgentConversation $conversation,
        int $userId,
        float $amount,
        ?string $description = null,
    ): string {
        try {
            $aiAgent = $conversation->aiAgent;

            if (! $aiAgent->isQrisEnabled()) {
                $errors = $aiAgent->validateQrisConfiguration();
                if (! empty($errors)) {
                    Log::warning('QRIS not properly configured', [
                        'ai_agent_id' => $aiAgent->id,
                        'errors' => $errors,
                    ]);
                }

                return 'Maaf, pembayaran QRIS belum tersedia saat ini. Silakan hubungi penjual untuk metode pembayaran lain.';
            }

            $subMerchant = $aiAgent->getSubMerchant();
            if (! $subMerchant) {
                return 'Maaf, pembayaran QRIS belum tersedia. Silakan hubungi penjual.';
            }
            if ($amount <= 0) {
                return 'Maaf, jumlah pembayaran tidak valid.';
            }

            $order = $conversation->getCurrentOrder();

            $qrisTransaction = $this->qrisService->generateQris($subMerchant, $amount, [
                'description' => $description ?? 'Pembayaran via WhatsApp',
            ]);

            if ($order) {
                $qrisTransaction->linked_order_id = $order->id;
                $qrisTransaction->save();

                Payment::create([
                    'order_id' => $order->id,
                    'qris_transaction_id' => $qrisTransaction->id,
                    'method' => Payment::METHOD_QRIS,
                    'amount' => $amount,
                    'status' => Payment::STATUS_PENDING,
                ]);
            }

            $conversation->setCurrentQrisTransaction($qrisTransaction->id);

            $expiryTime = $qrisTransaction->expires_at->format('H:i');
            $formattedAmount = 'Rp '.number_format($amount, 0, ',', '.');

            return "💳 Pembayaran QRIS\n\n"
                ."Total: {$formattedAmount}\n\n"
                ."📱 Silakan bayar melalui link berikut:\n"
                ."{$qrisTransaction->getShareableLink()}\n\n"
                ."⏰ Berlaku hingga: {$expiryTime}\n\n"
                ."Cara pembayaran:\n"
                ."1. Klik link di atas\n"
                ."2. Scan QR Code yang muncul\n"
                ."3. Buka aplikasi e-wallet/mobile banking\n"
                ."4. Konfirmasi pembayaran\n\n"
                ."Setelah pembayaran berhasil, ketik 'cek status' untuk konfirmasi. 🙏";
        } catch (\Exception $e) {
            Log::error('QRIS generation failed in AI Agent', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversation->id,
                'amount' => $amount,
            ]);

            return 'Maaf, terjadi kesalahan saat membuat kode pembayaran. Silakan coba lagi atau hubungi penjual.';
        }
    }

    /**
     * Resolve the current QRIS state and return the appropriate customer
     * message. Auto-expires pending links past their TTL and clears the
     * conversation's payment context on terminal states so the LLM doesn't
     * keep referring to a dead transaction.
     */
    public function checkPaymentStatus(AiAgentConversation $conversation): string
    {
        try {
            $qrisTransaction = $conversation->getCurrentQrisTransaction();

            // currentQrisTransaction is null when clearPaymentContext() was
            // already called by the webhook. Fall back to the snapshot id.
            if (! $qrisTransaction) {
                $lastId = $conversation->order_context['last_qris_transaction_id'] ?? null;
                if ($lastId) {
                    $qrisTransaction = QrisTransaction::find($lastId);
                }
            }
            if (! $qrisTransaction) {
                return 'Tidak ada pembayaran yang sedang diproses. Silakan buat pesanan terlebih dahulu.';
            }

            $qrisTransaction->refresh();
            $formattedAmount = 'Rp '.number_format($qrisTransaction->amount, 0, ',', '.');

            return match ($qrisTransaction->status) {
                QrisTransaction::STATUS_SETTLEMENT => $this->settlementMessage($conversation, $qrisTransaction, $formattedAmount),
                QrisTransaction::STATUS_PENDING => $this->pendingMessage($conversation, $qrisTransaction, $formattedAmount),
                QrisTransaction::STATUS_EXPIRE => $this->expireMessage($conversation),
                QrisTransaction::STATUS_CANCEL => "❌ Pembayaran dibatalkan.\n\nSilakan buat pesanan baru jika ingin melanjutkan.",
                default => "Status pembayaran: {$qrisTransaction->status}\n".'Silakan hubungi penjual untuk informasi lebih lanjut.',
            };
        } catch (\Exception $e) {
            Log::error('Payment status check failed', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversation->id,
            ]);

            return 'Maaf, terjadi kesalahan saat mengecek status pembayaran. Silakan coba lagi.';
        }
    }

    protected function settlementMessage(AiAgentConversation $conversation, QrisTransaction $qris, string $formattedAmount): string
    {
        $response = "✅ Pembayaran Berhasil!\n\n";
        $response .= "Jumlah: {$formattedAmount}\n";
        $response .= "No. Transaksi: {$qris->order_id}\n\n";

        $deliveryType = $conversation->order_context['delivery_type'] ?? null;
        if ($deliveryType === Order::DELIVERY_TYPE_DELIVERY) {
            $address = $conversation->order_context['delivery_address'] ?? null;
            $response .= '🚚 Pesanan Anda sedang disiapkan dan akan segera diantar';
            if ($address) {
                $response .= " ke:\n📍 {$address}";
            }
            $response .= "\n\nMohon siapkan diri untuk menerima pesanan. Terima kasih! 🙏";
        } else {
            $response .= 'Pesanan Anda sedang diproses. Terima kasih atas pembayaran Anda! 🙏';
        }

        $conversation->clearPaymentContext();
        $conversation->clearCart();
        $conversation->clearPendingOrder();

        return $response;
    }

    protected function pendingMessage(AiAgentConversation $conversation, QrisTransaction $qris, string $formattedAmount): string
    {
        if ($qris->isExpired()) {
            $qris->markAsExpired();
            $qris->save();

            return $this->expireMessage($conversation);
        }

        $remainingMinutes = ceil($qris->getRemainingTimeInSeconds() / 60);

        return "⏳ Pembayaran Menunggu\n\n"
            ."Jumlah: {$formattedAmount}\n"
            ."Sisa waktu: {$remainingMinutes} menit\n\n"
            ."Silakan selesaikan pembayaran melalui QR Code yang sudah diberikan.\n"
            .'Jika sudah membayar, tunggu beberapa saat lalu cek status kembali.';
    }

    protected function expireMessage(AiAgentConversation $conversation): string
    {
        $conversation->clearFlowState();
        $conversation->clearPaymentContext();
        $conversation->clearCart();
        $conversation->clearPendingOrder();

        $orderContext = $conversation->order_context ?? [];
        unset($orderContext['last_qris_transaction_id']);
        $conversation->order_context = $orderContext;
        $conversation->save();

        return "⏰ Kode pembayaran sudah kadaluarsa.\n\n".'Pesanan otomatis dibatalkan.';
    }
}
