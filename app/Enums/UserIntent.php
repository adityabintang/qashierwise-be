<?php

namespace App\Enums;

enum UserIntent: string
{
    case GREETING = 'greeting';
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

        // Greeting
        if (preg_match('/^(hai|halo|hi|hello|hei|assalamualaikum)/', $message)) {
            return self::GREETING;
        }

        // View Menu
        if (preg_match('/(menu|daftar|list|produk|jual apa|ada apa)/i', $message)) {
            return self::VIEW_MENU;
        }

        // Order
        if (preg_match('/(pesan|beli|order|mau|ambil)/i', $message)) {
            return self::ORDER;
        }

        // View Cart
        if (preg_match('/(keranjang|cart|pesanan saya|lihat pesanan)/i', $message)) {
            return self::VIEW_CART;
        }

        // Checkout
        if (preg_match('/(checkout|bayar|konfirmasi|lanjut|proses)/i', $message)) {
            return self::CHECKOUT;
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
