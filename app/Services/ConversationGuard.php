<?php

namespace App\Services;

use App\Enums\UserIntent;
use App\Models\AiAgent;
use App\Models\AiAgentConversation;

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
            'ok', 'oke', 'iya', 'ya', 'y', 'sip', 'betul', 'benar', 'lanjut', 'sudah',
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
}
