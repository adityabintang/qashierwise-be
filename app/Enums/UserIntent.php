<?php

namespace App\Enums;

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

    public static function detect(string $message): self
    {
        $message = strtolower($message);

        // Greeting - only if no menu/order/cart keywords follow the greeting
        if (preg_match('/^(hai|halo|hi|hello|hei|assalamualaikum)/', $message)) {
            $hasOrderMenuKeyword = preg_match('/(menu|pesan|beli|order|keranjang|checkout|bayar|produk|daftar|jual apa|ada apa)/i', $message);
            if (! $hasOrderMenuKeyword) {
                return self::GREETING;
            }
            // Fall through to check more specific intents below
        }

        // Next Menu Page (check before View Menu - more specific)
        if (preg_match('/(menu (lainnya|selanjutnya|berikutnya|lagi)|lihat (lagi|selanjutnya)|masih ada (lagi|yang lain)|ada (lagi|yang lain)|selanjutnya|next|lebih banyak|lainnya)/i', $message)) {
            return self::NEXT_MENU_PAGE;
        }

        // View Menu
        if (preg_match('/(menu|daftar|list|produk|jual apa|ada apa)/i', $message)) {
            return self::VIEW_MENU;
        }

        // View Cart (check before Order - more specific)
        if (preg_match('/(keranjang|cart|pesanan saya|lihat pesanan)/i', $message)) {
            return self::VIEW_CART;
        }

        // Checkout (check before Order - more specific)
        if (preg_match('/(checkout|bayar|konfirmasi|lanjut|proses)/i', $message)) {
            return self::CHECKOUT;
        }

        // Order (check after more specific patterns)
        if (preg_match('/(pesan|beli|order|mau|ambil)/i', $message)) {
            return self::ORDER;
        }

        // Business Info
        if (preg_match('/(jam|buka|tutup|alamat|lokasi|dimana|kontak|telepon)/i', $message)) {
            return self::BUSINESS_INFO;
        }

        // Off Topic Detection
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
}
