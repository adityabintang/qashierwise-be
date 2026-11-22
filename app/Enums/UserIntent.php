<?php

namespace App\Enums;

/**
 * Single source of truth for classifying customer messages into an action
 * the AI agent should take. Replaces the previous AiAgentService methods
 * isOrderMenuIntent() and isNextMenuPageIntent() — those duplicated this
 * logic with overlapping regex and produced inconsistent classifications.
 *
 * Add a new intent here when (and only when) the dispatcher needs to branch
 * on something this enum cannot already express.
 */
enum UserIntent: string
{
    case GREETING = 'greeting';
    case NEXT_MENU_PAGE = 'next_menu_page';
    case VIEW_MENU = 'view_menu';
    case SEARCH_PRODUCT = 'search_product';
    case ORDER = 'order';
    case VIEW_CART = 'view_cart';
    case CHECKOUT = 'checkout';
    case BUSINESS_INFO = 'business_info';
    case OFF_TOPIC = 'off_topic';
    case UNKNOWN = 'unknown';

    /**
     * Classify a message. The optional $context lets the caller disambiguate
     * intents whose text is identical but whose meaning depends on where the
     * conversation is (e.g. "alamat" → BUSINESS_INFO normally, but ORDER when
     * the customer is filling in a delivery address).
     *
     * Supported context keys (all optional):
     *   - flow_state: string|null    current AiAgentConversation flow_state
     *   - has_cart:   bool           whether the conversation already has items
     */
    public static function detect(string $message, array $context = []): self
    {
        $message = strtolower($message);

        // Greeting wins only when there's no order/menu/cart keyword tagging
        // along — "halo" is a greeting, "halo mau pesan" is an order request.
        if (preg_match('/^(hai|halo|hi|hello|hei|assalamualaikum)/', $message)) {
            $hasOrderMenuKeyword = preg_match('/(menu|pesan|beli|order|keranjang|checkout|bayar|produk|daftar|jual apa|ada apa)/i', $message);
            if (! $hasOrderMenuKeyword) {
                return self::GREETING;
            }
        }

        // Pagination — must be checked before VIEW_MENU since it's a specialization.
        if (preg_match('/(menu (lainnya|selanjutnya|berikutnya|lagi)|lihat (lagi|selanjutnya)|masih ada (lagi|yang lain)|ada (lagi|yang lain)|selanjutnya|next( menu)?|lebih banyak|lainnya|(page|halaman) berikutnya)/i', $message)) {
            return self::NEXT_MENU_PAGE;
        }

        if (preg_match('/(menu|daftar|list|produk|jual apa|ada apa( aja)?)/i', $message)) {
            return self::VIEW_MENU;
        }

        if (preg_match('/(keranjang|cart|pesanan saya|lihat pesanan)/i', $message)) {
            return self::VIEW_CART;
        }

        // "konfirmasi" alone and "konfirmasi pesanan" both belong here.
        if (preg_match('/(checkout|bayar|konfirmasi( pesanan)?|^selesai$|^sudah$|^udah$|^cukup$|^itu (saja|aja)$|^lanjut(kan)?$|proses)/i', $message)) {
            return self::CHECKOUT;
        }

        if (preg_match('/(pesan|beli|order|mau|ambil)/i', $message)) {
            return self::ORDER;
        }

        // Context-aware "alamat" disambiguation. A delivery flow stays in
        // 'awaiting_delivery_info' state until the customer answers — that's
        // the strongest signal "alamat" means the shipping address, not a
        // question about the cafe.
        //
        // has_cart by itself is too weak: a customer with items in the cart
        // can still ask "alamat kafe dimana?" and expect business info.
        // Question words ("dimana", "berapa", "kapan") short-circuit back to
        // BUSINESS_INFO even when the cart is non-empty.
        $isQuestionAboutPlace = (bool) preg_match('/(dimana|kapan|berapa|jam berapa|alamatnya apa)/i', $message);
        $deliveryFlowActive = ($context['flow_state'] ?? null) === 'awaiting_delivery_info';
        $hasCart = (bool) ($context['has_cart'] ?? false);

        if (! $isQuestionAboutPlace
            && ($deliveryFlowActive || $hasCart)
            && preg_match('/(alamat|address)/i', $message)) {
            return self::ORDER;
        }

        // Explicit delivery vocabulary plus "alamat" is always an order.
        if (preg_match('/(delivery|pickup|antar|ambil sendiri)/i', $message)
            && preg_match('/(alamat|address)/i', $message)) {
            return self::ORDER;
        }

        if (preg_match('/(jam|buka|tutup|alamat|lokasi|dimana|kontak|telepon)/i', $message)) {
            return self::BUSINESS_INFO;
        }

        $offTopicKeywords = [
            'siapa presiden', 'ibu kota', 'chatgpt', 'claude', 'openai',
            'berita', 'politik', 'sejarah', 'matematika', 'hitungan',
            'cerita', 'puisi', 'coding', 'program',
        ];

        foreach ($offTopicKeywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return self::OFF_TOPIC;
            }
        }

        return self::UNKNOWN;
    }

    public function needsBusinessInfo(): bool
    {
        return in_array($this, [
            self::GREETING,
            self::BUSINESS_INFO,
        ]);
    }

    public function needsProductList(): bool
    {
        return in_array($this, [
            self::VIEW_MENU,
            self::SEARCH_PRODUCT,
            self::NEXT_MENU_PAGE,
        ]);
    }

    public function needsOrderWorkflow(): bool
    {
        return in_array($this, [
            self::ORDER,
            self::VIEW_CART,
            self::CHECKOUT,
        ]);
    }

    /**
     * Whether the intent describes anything related to the menu/order/cart
     * flow. Replaces AiAgentService::isOrderMenuIntent() and is the canonical
     * predicate the "order disabled" hard-guard branches off of.
     */
    public function isOrderOrMenuRelated(): bool
    {
        return in_array($this, [
            self::VIEW_MENU,
            self::SEARCH_PRODUCT,
            self::NEXT_MENU_PAGE,
            self::ORDER,
            self::VIEW_CART,
            self::CHECKOUT,
        ]);
    }
}
