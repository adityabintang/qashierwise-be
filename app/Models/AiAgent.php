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
        'qris_enabled',
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
            'qris_enabled' => 'boolean',
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
     * Check if QRIS feature is properly enabled.
     * Requires qris_enabled flag, active SubMerchant, and active payment provider.
     */
    public function isQrisEnabled(): bool
    {
        return $this->qris_enabled 
            && $this->hasActiveSubMerchant()
            && $this->hasActivePaymentProvider();
    }

    /**
     * Get the user associated with this AI Agent.
     */
    public function getUser(): ?User
    {
        return $this->whatsappAccount?->user;
    }

    /**
     * Get the user's active SubMerchant.
     */
    public function getSubMerchant(): ?SubMerchant
    {
        $user = $this->getUser();
        if (!$user) {
            return null;
        }

        return SubMerchant::where('user_id', $user->id)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Check if user has an active SubMerchant.
     */
    public function hasActiveSubMerchant(): bool
    {
        return $this->getSubMerchant() !== null;
    }

    /**
     * Check if user has active payment provider credentials.
     */
    public function hasActivePaymentProvider(): bool
    {
        $user = $this->getUser();
        if (!$user) {
            return false;
        }

        return PaymentProviderCredential::where('user_id', $user->id)
            ->where('is_active', true)
            ->where('connection_status', 'valid')
            ->exists();
    }

    /**
     * Validate QRIS configuration and return error messages if invalid.
     */
    public function validateQrisConfiguration(): array
    {
        $errors = [];

        if (!$this->hasActiveSubMerchant()) {
            $errors[] = 'Sub-merchant belum dikonfigurasi atau tidak aktif. Silakan daftarkan sub-merchant terlebih dahulu.';
        }

        if (!$this->hasActivePaymentProvider()) {
            $errors[] = 'Payment provider belum dikonfigurasi atau tidak valid. Silakan konfigurasi provider di menu Provider Settings.';
        }

        return $errors;
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
                $prompt .= "\n\n## Produk Tersedia (Top 20) - UNTUK REFERENSI AI:\n";
                foreach ($products as $product) {
                    $prompt .= "- {$product->name} [ID:{$product->id}] - Rp ".number_format($product->price, 0, ',', '.')." - Stok: {$product->stock_quantity}";
                    if ($product->description) {
                        $prompt .= " - {$product->description}";
                    }
                    $prompt .= "\n";
                }
                $prompt .= "\n**PENTING UNTUK AI**: 
- ID produk dalam [ID:X] adalah untuk internal AI saja
- Saat user bertanya 'menunya apa?', tampilkan TANPA [ID:X]
- Format ke user: '1. Dimsum Keju - Rp 40.000 - Stok: 10'
- Saat user memesan, WAJIB panggil search_products dulu untuk mendapatkan ID terbaru
- Untuk produk lainnya, gunakan function 'search_products' untuk mencari";
            }

            // Add ordering instructions
            $prompt .= "\n\n## INSTRUKSI PEMESANAN - WAJIB DIPATUHI:

1. **JANGAN PERNAH menghitung harga sendiri**
   - SELALU gunakan hasil dari function tools
   - JANGAN tambahkan atau kurangi angka sendiri
   - JANGAN hitung pajak atau total sendiri

2. **JANGAN PERNAH tampilkan ID produk ke user**
   - ID produk hanya untuk internal AI
   - User TIDAK PERLU tahu ID produk
   - User hanya perlu menyebutkan NAMA produk

3. **WORKFLOW PEMESANAN - WAJIB IKUTI:**
   
   **Langkah 1: User bertanya menu**
   - User: 'menunya apa aja?'
   - AI: Tampilkan daftar produk dari konteks (TANPA ID)
   - Format: '1. Dimsum Keju - Rp 40.000'
   
   **Langkah 2: User memesan produk**
   - JIKA user memesan SATU produk: gunakan search_products('nama_produk')
   - JIKA user memesan LEBIH DARI SATU produk: WAJIB gunakan search_multiple_products(['produk1', 'produk2'])
   - Contoh: 'pesan dimsum dan teh' → search_multiple_products(['dimsum', 'teh'])
   - TIPS: Gunakan kata kunci PENDEK (contoh: 'dimsum', 'teh', 'nasi')
   
   **Langkah 3: Tambahkan ke keranjang**
   - Setelah dapat ID dari search
   - Panggil add_to_cart dengan ID tersebut
   - Untuk multiple produk: add_to_cart(products=[{product_id:X, quantity:Y}, ...])
   - Tampilkan hasil dari function

4. **PENTING: SELALU SEARCH DULU SEBELUM ADD TO CART**
   - Meskipun produk sudah ditampilkan sebelumnya
   - Untuk SATU produk: search_products('nama')
   - Untuk MULTIPLE produk: search_multiple_products(['nama1', 'nama2'])
   - Baru kemudian panggil add_to_cart dengan ID tersebut
   - EKSTRAK ID dari hasil search yang berbentuk [ID:X]
   - Contoh: \"Dimsum Keju [ID:123]\" gunakan product_id: 123

5. **Saat menambahkan produk ke keranjang:**
   - Gunakan function 'add_to_cart' dengan parameter 'products' (array)
   - **PENTING: Untuk MULTIPLE produk, masukkan SEMUA produk dalam SATU array**
   - Format: products: [{product_id: X, quantity: Y}, {product_id: Z, quantity: W}]
   - Tampilkan PERSIS hasil yang dikembalikan function
   - JANGAN ubah atau hitung ulang harga

6. **Saat menampilkan keranjang:**
   - Gunakan function 'get_cart_summary'
   - Tampilkan PERSIS hasil yang dikembalikan function
   - JANGAN hitung ulang subtotal, pajak, atau total

7. **Saat konfirmasi pesanan:**
   - Gunakan function 'confirm_order'
   - Tampilkan PERSIS hasil yang dikembalikan function
   - Function akan otomatis generate QRIS jika enabled

8. **Format response:**
   - Salin PERSIS output dari function
   - Boleh tambahkan kalimat pembuka/penutup yang ramah
   - JANGAN ubah angka atau perhitungan apapun

CONTOH BENAR - User memesan MULTIPLE produk:
User: 'pesan dimsum keju 2 dan teh jumbo 1'

Step 1: Search SEMUA produk sekaligus
AI: [panggil search_multiple_products(['dimsum', 'teh'])]
Hasil: 
- Dimsum Keju [ID:1] - Harga: Rp 40.000 - Stok: 10
- Teh Jumbo [ID:2] - Harga: Rp 5.000 - Stok: 20

Step 2: Add semua produk ke cart dalam SATU panggilan
AI: [panggil add_to_cart(products=[{product_id:1, quantity:2}, {product_id:2, quantity:1}])]

Step 3: Tampilkan hasil ke user (TANPA ID)
AI Response: 'Baik! Berhasil menambahkan ke keranjang!

Dimsum Keju x2
Teh Jumbo x1

Ketik lihat keranjang untuk melihat ringkasan pesanan.'

CONTOH BENAR - User memesan SATU produk:
User: 'pesan dimsum 2'

Step 1: [panggil search_products('dimsum')]
Step 2: [dapat hasil dengan ID:1]
Step 3: [panggil add_to_cart(products=[{product_id:1, quantity:2}])]

CONTOH SALAH 1:
User: 'pesan dimsum keju 2'
AI: [langsung panggil add_to_cart tanpa search] ❌ SALAH! Harus search dulu!

CONTOH SALAH 2:
User: 'pesan dimsum dan teh'
AI: [panggil search_products('dimsum')] ❌ SALAH! Untuk multiple produk, gunakan search_multiple_products(['dimsum', 'teh'])

CONTOH SALAH 3:
User: 'pesan dimsum 2'
AI: [panggil search_products('dimsum keju')] ❌ SALAH! Gunakan kata kunci PENDEK: 'dimsum'

**TIPS PENTING UNTUK SEARCH:**
- Untuk SATU produk: search_products('kata_kunci')
- Untuk MULTIPLE produk: search_multiple_products(['kata1', 'kata2', ...])
- Gunakan kata kunci PENDEK dan UMUM (contoh: 'dimsum', 'teh', 'nasi', 'ayam')
- Sistem akan mencocokkan dengan semua produk yang mengandung kata tersebut
- Jika user bilang 'dimsum', sistem akan menemukan 'Dimsum Keju', 'Dimsum Ayam', dll
- Jika user bilang 'teh', sistem akan menemukan 'Teh Jumbo', 'Teh Manis', dll
- Jika hasil search lebih dari 1 untuk satu kata kunci, tanyakan ke user produk mana yang dimaksud
- EKSTRAK ID dari hasil search yang berbentuk [ID:X] dan gunakan untuk add_to_cart";
        }

        return $prompt;
    }
}
