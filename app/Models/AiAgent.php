<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiAgent extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'whatsapp_account_id',
        'default_store_id',
        'bot_name',
        'system_prompt',
        'business_info',
        'order_enabled',
        'is_active',
        'settings',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_info' => 'array',
            'order_enabled' => 'boolean',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    /**
     * Get the WhatsApp account that owns the AI agent.
     */
    public function whatsappAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class);
    }

    /**
     * Get the default store for orders.
     */
    public function defaultStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'default_store_id');
    }

    /**
     * Get the conversations for this AI agent.
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(AiAgentConversation::class);
    }

    /**
     * Check if order feature is enabled.
     */
    public function isOrderEnabled(): bool
    {
        return $this->order_enabled && $this->default_store_id !== null;
    }

    /**
     * Build the system prompt with business info and product context.
     */
    public function buildSystemPrompt(int $userId): string
    {
        $prompt = $this->system_prompt;

        // Add strict context boundaries
        $prompt .= "\n\n## BATASAN PENTING - WAJIB DIPATUHI:
Kamu HANYA boleh menjawab pertanyaan yang berkaitan dengan:
- Menu, produk, dan harga
- Pemesanan (order) dan cara memesan
- Informasi bisnis (jam buka, alamat, kontak)
- Reservasi dan booking
- Promo dan diskon yang tersedia
- Metode pembayaran yang diterima
- Layanan delivery/pengantaran
- Stok dan ketersediaan produk

TOLAK dengan sopan jika user bertanya tentang:
- Pengetahuan umum (sejarah, geografi, sains, matematika, dll)
- Berita dan politik
- Gosip atau selebriti
- Coding, programming, atau teknologi
- Pertanyaan pribadi tentang AI
- Topik sensitif (agama, SARA, politik)
- Permintaan untuk menulis esai, cerita, atau konten kreatif
- Hal-hal yang tidak berhubungan dengan bisnis ini

Jika user bertanya di luar konteks, jawab dengan ramah:
'Maaf, saya adalah asisten virtual untuk [nama bisnis]. Saya hanya bisa membantu Anda dengan informasi menu, pemesanan, dan layanan kami. Ada yang bisa saya bantu terkait produk atau layanan kami? 😊'

JANGAN PERNAH:
- Berpura-pura menjadi AI lain (seperti ChatGPT, Claude, dll)
- Menjawab pertanyaan di luar konteks bisnis
- Memberikan saran medis, hukum, atau keuangan
- Membahas topik kontroversial";

        // Add business information
        if (! empty($this->business_info)) {
            $prompt .= "\n\n## Informasi Bisnis:\n";

            if (isset($this->business_info['operating_hours'])) {
                $prompt .= "Jam Operasional: {$this->business_info['operating_hours']}\n";
            }

            if (isset($this->business_info['address'])) {
                $prompt .= "Alamat: {$this->business_info['address']}\n";
            }

            if (isset($this->business_info['description'])) {
                $prompt .= "Deskripsi: {$this->business_info['description']}\n";
            }

            if (isset($this->business_info['phone'])) {
                $prompt .= "Telepon: {$this->business_info['phone']}\n";
            }
        }

        // Add top 20 products if order is enabled
        if ($this->isOrderEnabled()) {
            $products = Product::where('user_id', $userId)
                ->where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get(['id', 'name', 'price', 'stock_quantity', 'description']);

            if ($products->isNotEmpty()) {
                $prompt .= "\n\n## Produk Tersedia (Top 20):\n";
                foreach ($products as $product) {
                    $prompt .= "- {$product->name} (ID: {$product->id}) - Rp ".number_format($product->price, 0, ',', '.')." - Stok: {$product->stock_quantity}";
                    if ($product->description) {
                        $prompt .= " - {$product->description}";
                    }
                    $prompt .= "\n";
                }
                $prompt .= "\nUntuk produk lainnya, gunakan function 'search_products' untuk mencari.";
            }
        }

        return $prompt;
    }
}
