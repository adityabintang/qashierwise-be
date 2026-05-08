<?php

namespace App\Services;

use App\Enums\UserIntent;
use App\Models\AiAgent;
use App\Models\AiAgentConversation;
use App\Models\QrisTransaction;

class ConversationGuard
{
    /**
     * Resolve deterministic replies without LLM calls.
     */
    public function resolve(
        AiAgentConversation $conversation,
        AiAgent $aiAgent,
        string $messageText,
        UserIntent $intent
    ): ?string {
        if (! $this->shouldGuard($conversation, $aiAgent, $messageText, $intent)) {
            return null;
        }

        $orderContextReply = $this->resolveFromOrderContext($conversation, $aiAgent, $messageText);
        if ($orderContextReply !== null) {
            return $orderContextReply;
        }

        $paymentReply = $this->resolveFromPendingPayment($conversation, $aiAgent, $messageText, $intent);
        if ($paymentReply !== null) {
            return $paymentReply;
        }

        return $this->resolveFromSummary($conversation, $aiAgent);
    }

    private function shouldGuard(
        AiAgentConversation $conversation,
        AiAgent $aiAgent,
        string $messageText,
        UserIntent $intent
    ): bool {
        if (! $aiAgent->isOrderEnabled()) {
            return false;
        }

        if ($this->getActiveQrisTransaction($conversation)) {
            return true;
        }

        if ($intent === UserIntent::UNKNOWN) {
            return true;
        }

        if ($intent !== UserIntent::BUSINESS_INFO) {
            return false;
        }

        $hasCart = ! empty($conversation->getCart());
        $hasDeliveryContext = $conversation->getDeliveryType()
            || $conversation->getDeliveryAddress()
            || $conversation->getDeliveryNotes();

        if (! $hasCart && ! $hasDeliveryContext) {
            return false;
        }

        return $this->looksLikeAddress($messageText)
            || $this->isDeliveryKeyword($messageText)
            || $this->isPickupKeyword($messageText)
            || $this->isShortConfirmation($messageText);
    }

    private function resolveFromOrderContext(
        AiAgentConversation $conversation,
        AiAgent $aiAgent,
        string $messageText
    ): ?string {
        $cart = $conversation->getCart();
        if (empty($cart)) {
            return null;
        }

        $normalized = $this->normalizeMessage($messageText);
        $rawMessage = trim($messageText);

        if ($aiAgent->isDeliveryEnabled()) {
            $deliveryType = $conversation->getDeliveryType();
            if (! $deliveryType) {
                if ($this->isDeliveryKeyword($normalized) || $this->isPickupKeyword($normalized)) {
                    $selectedType = $this->isDeliveryKeyword($normalized) ? 'delivery' : 'pickup';

                    return $this->applyDeliverySelection($conversation, $aiAgent, $selectedType, $rawMessage);
                }

                if ($this->isShortConfirmation($normalized)) {
                    return "Sebelum checkout, pilih metode pengiriman:\n".
                        "• Ketik 'pickup' untuk ambil di tempat\n".
                        "• Ketik 'delivery' untuk diantar";
                }

                return null;
            }

            if ($deliveryType === 'delivery' && ! $conversation->getDeliveryAddress()) {
                $address = $this->extractAddress($rawMessage);
                if ($address !== null) {
                    $conversation->setDeliveryAddress($address);

                    return $this->buildNotesPrompt($address, $aiAgent->default_ongkir);
                }

                if ($this->isShortConfirmation($normalized)) {
                    return 'Silakan kirim alamat pengiriman Anda.';
                }

                return null;
            }
        }

        if ($conversation->getDeliveryNotes() === null) {
            $notesResponse = $this->applyDeliveryNotes($conversation, $normalized, $rawMessage);
            if ($notesResponse !== null) {
                return $notesResponse;
            }

            if ($this->isShortConfirmation($normalized)) {
                return "Ada catatan khusus untuk pesanan? (contoh: tidak pedas, tanpa bawang)\n".
                    "Ketik 'tidak ada' jika tidak ada catatan.";
            }
        }

        return null;
    }

    private function resolveFromSummary(AiAgentConversation $conversation, AiAgent $aiAgent): ?string
    {
        $summary = $conversation->getSummary();
        if (! is_array($summary)) {
            return null;
        }

        $intent = $summary['intent'] ?? null;
        $missing = $summary['missing_information'] ?? [];

        if (! empty($missing)) {
            $missingText = implode(', ', array_filter($missing));
            if ($missingText !== '') {
                return "Untuk melanjutkan, saya butuh: {$missingText}.";
            }
        }

        if ($intent === 'order_food') {
            return "Saya bisa bantu lanjut pesanan. Sebutkan produk dan jumlahnya, contoh: 'pesan nasi goreng 2'.";
        }

        if ($intent === 'browse_menu') {
            return "Ketik 'menu' untuk melihat daftar produk kami.";
        }

        if ($intent === 'payment' && $conversation->getCurrentOrder()) {
            return "Pesanan Anda sudah dibuat. Ketik 'bayar' untuk lanjut pembayaran.";
        }

        if ($aiAgent->isDeliveryEnabled() && $conversation->getCart()) {
            return "Saya bisa bantu lanjut checkout. Pilih metode pengiriman: ketik 'pickup' atau 'delivery'.";
        }

        return null;
    }

    private function normalizeMessage(string $messageText): string
    {
        return strtolower(trim($messageText));
    }

    private function isShortConfirmation(string $messageText): bool
    {
        $confirmations = [
            'ok', 'oke', 'iya', 'ya', 'y', 'sip', 'sipsip', 'sip sip', 'betul', 'benar', 'lanjut', 'sudah',
        ];

        return in_array($messageText, $confirmations, true);
    }

    private function isDeliveryKeyword(string $messageText): bool
    {
        return (bool) preg_match('/\b(delivery|antar|diantar)\b/i', $messageText);
    }

    private function isPickupKeyword(string $messageText): bool
    {
        return (bool) preg_match('/\b(pickup|ambil|ambil sendiri|takeaway)\b/i', $messageText);
    }

    private function looksLikeAddress(string $messageText): bool
    {
        if (mb_strlen($messageText) < 12) {
            return false;
        }

        if (! preg_match('/\d/', $messageText)) {
            return false;
        }

        return (bool) preg_match('/\b(jl|jalan|jln|rt|rw|no\.?|blok|kec|kel|desa|komplek|gang|gng)\b/i', $messageText);
    }

    private function extractAddress(string $messageText): ?string
    {
        if ($this->looksLikeAddress($messageText)) {
            $address = preg_replace('/^alamat(nya)?\s*[:\-]?\s*/i', '', $messageText);
            $address = trim((string) $address);

            return $address !== '' ? $address : null;
        }

        return null;
    }

    private function applyDeliverySelection(
        AiAgentConversation $conversation,
        AiAgent $aiAgent,
        string $deliveryType,
        string $messageText
    ): string {
        $conversation->setDeliveryType($deliveryType);

        $ongkir = 0.0;
        if ($deliveryType === 'delivery') {
            $ongkir = (float) $aiAgent->default_ongkir;
            $conversation->setOngkir($ongkir);

            $address = $this->extractAddress($messageText);
            if ($address !== null) {
                $conversation->setDeliveryAddress($address);

                return $this->buildNotesPrompt($address, $ongkir);
            }

            $formattedOngkir = 'Rp '.number_format($ongkir, 0, ',', '.');

            return "✅ Delivery dipilih.\n".
                "🚚 Ongkir: {$formattedOngkir}\n\n".
                'Silakan kirim alamat pengiriman Anda.';
        }

        $conversation->setOngkir(0.0);

        return "✅ Pickup dipilih. Ongkir: Rp 0.\n\n".
            "Ada catatan khusus untuk pesanan? (contoh: tidak pedas, tanpa bawang)\n".
            "Ketik 'tidak ada' jika tidak ada catatan.";
    }

    private function applyDeliveryNotes(
        AiAgentConversation $conversation,
        string $normalizedMessage,
        string $rawMessage
    ): ?string {
        if ($normalizedMessage === '' || $normalizedMessage === 'tidak ada' || $normalizedMessage === 'ga ada') {
            $conversation->setDeliveryNotes(null);

            return "✅ Tidak ada catatan khusus.\n\nKetik 'konfirmasi' untuk melanjutkan checkout.";
        }

        if ($this->isShortConfirmation($normalizedMessage)) {
            return null;
        }

        if (mb_strlen($rawMessage) < 4) {
            return null;
        }

        $conversation->setDeliveryNotes($rawMessage);

        return "📝 Catatan tersimpan: {$rawMessage}\n\nKetik 'konfirmasi' untuk melanjutkan checkout.";
    }

    private function buildNotesPrompt(string $address, float $ongkir): string
    {
        $formattedOngkir = 'Rp '.number_format($ongkir, 0, ',', '.');

        return "✅ Delivery dipilih.\n".
            "📍 Alamat: {$address}\n".
            "🚚 Ongkir: {$formattedOngkir}\n\n".
            "Ada catatan khusus untuk pesanan? (contoh: tidak pedas, tanpa bawang)\n".
            "Ketik 'tidak ada' jika tidak ada catatan.";
    }

    private function resolveFromPendingPayment(
        AiAgentConversation $conversation,
        AiAgent $aiAgent,
        string $messageText,
        UserIntent $intent
    ): ?string {
        $normalized = $this->normalizeMessage($messageText);

        if ($this->looksLikePaymentAction($normalized)) {
            return null;
        }

        $transaction = $this->getActiveQrisTransaction($conversation);
        if (! $transaction) {
            return null;
        }

        if (! $transaction->canBeUsed()) {
            return "⏰ Kode pembayaran sudah kadaluarsa.\n\n".
                "Ketik 'bayar' untuk mendapatkan link pembayaran baru.";
        }

        $remainingMinutes = max(1, (int) ceil($transaction->getRemainingTimeInSeconds() / 60));
        $shareableLink = $transaction->getShareableLink();
        $formattedAmount = 'Rp '.number_format($transaction->amount, 0, ',', '.');

        $prefix = $this->buildPaymentPrefix($aiAgent, $messageText, $intent);

        return "{$prefix}Tapi Anda masih ada pembayaran menanti nih.\n\n".
            "Jumlah: {$formattedAmount}\n".
            "Sisa waktu: {$remainingMinutes} menit\n\n".
            "Silakan selesaikan pembayaran pada link berikut:\n".
            "{$shareableLink}\n\n".
            "Jika sudah bayar, ketik 'cek status' ya.";
    }

    private function getActiveQrisTransaction(AiAgentConversation $conversation): ?QrisTransaction
    {
        $transaction = $conversation->getCurrentQrisTransaction();

        if (! $transaction) {
            $lastId = $conversation->order_context['last_qris_transaction_id'] ?? null;
            if ($lastId) {
                $transaction = QrisTransaction::find($lastId);
            }
        }

        if (! $transaction) {
            return null;
        }

        $transaction->refresh();

        if ($transaction->status !== QrisTransaction::STATUS_PENDING) {
            return null;
        }

        return $transaction;
    }

    private function looksLikePaymentAction(string $messageText): bool
    {
        return (bool) preg_match('/\b(cek status|status|bayar|payment|qris|bukti)\b/i', $messageText);
    }

    private function buildPaymentPrefix(
        AiAgent $aiAgent,
        string $messageText,
        UserIntent $intent
    ): string {
        $normalized = $this->normalizeMessage($messageText);

        if ($intent === UserIntent::OFF_TOPIC || $this->looksOffTopic($normalized)) {
            $botName = $aiAgent->bot_name;

            return "Maaf, saya {$botName} hanya membantu pemesanan makanan, melihat menu, atau reservasi. ";
        }

        if (! $this->isOrderContextMessage($normalized) && ! $this->isShortConfirmation($normalized)) {
            $botName = $aiAgent->bot_name;

            return "Maaf, saya {$botName} hanya membantu pemesanan makanan, melihat menu, atau reservasi. ";
        }

        $cleanMessage = trim(preg_replace('/\s+/', ' ', $messageText));
        if ($cleanMessage === '') {
            return 'Oke. ';
        }

        if (! $this->shouldEchoMessage($cleanMessage)) {
            return 'Oke. ';
        }

        if (mb_strlen($cleanMessage) > 60) {
            return 'Oke. ';
        }

        return "Oke, {$cleanMessage}. ";
    }

    private function shouldEchoMessage(string $messageText): bool
    {
        if (str_contains($messageText, '?')) {
            return false;
        }

        if (preg_match('/\b(apakah|kenapa|gimana|bagaimana|kapan|dimana|berapa)\b/i', $messageText)) {
            return false;
        }

        return true;
    }

    private function isOrderContextMessage(string $messageText): bool
    {
        return (bool) preg_match('/\b(menu|pesan|order|keranjang|checkout|konfirmasi|bayar|pembayaran|payment|qris|status)\b/i', $messageText);
    }

    private function looksOffTopic(string $messageText): bool
    {
        $offTopicKeywords = [
            'siapa presiden', 'ibu kota', 'chatgpt', 'claude', 'openai',
            'berita', 'politik', 'sejarah', 'matematika', 'hitungan',
            'cerita', 'puisi', 'coding', 'program', 'napoleon', 'biksu',
        ];

        foreach ($offTopicKeywords as $keyword) {
            if (str_contains($messageText, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
