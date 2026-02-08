# Implementasi Optimasi AI Agent Prompt - Complete Guide

## 📁 Struktur File

```
app/
├── Models/
│   └── AiAgent.php (refactored)
├── Services/
│   ├── AiAgentPromptBuilder.php (new)
│   ├── AiResponseValidator.php (new)
│   └── AiChatService.php (updated)
├── Enums/
│   └── UserIntent.php (new)
└── Config/
    └── ai_agent_prompts.php (new)
```

---

## 1. Config File untuk Prompt Templates

**File: `config/ai_agent_prompts.php`**

```php
<?php

return [
    'core_rules' => "
## ATURAN INTI (WAJIB DIPATUHI):

1. **Anti-Halusinasi**: HANYA gunakan data dari function calls
2. **Wajib Search**: Panggil function sebelum jawab tentang produk
3. **Produk Tidak Ada**: Jika tidak ditemukan → katakan tidak tersedia
4. **Jangan Tampilkan ID**: User tidak perlu tahu product_id
5. **Fokus Bisnis**: Hanya jawab tentang menu, pesanan, info bisnis

**Jika ditanya di luar topik:**
'Maaf, saya asisten untuk :business_name. Saya hanya bantu pemesanan dan info layanan kami. Ada yang bisa dibantu? 😊'
",

    'ordering_workflow' => "
## Alur Pemesanan:

**User tanya menu** → panggil `get_all_products()`

**User pesan produk:**
- 1 item: `search_products('keyword')`  
- >1 item: `search_multiple_products(['k1','k2'])`

**Dapat hasil** → `add_to_cart(products=[{product_id:X, quantity:Y}])`

**Tampilkan hasil function** (JANGAN hitung sendiri)

**Tips keyword:** Pendek & umum ('dimsum' bukan 'dimsum keju')
",

    'anti_hallucination_reminder' => "
**INGAT**: 
- Function result KOSONG = produk TIDAK ADA
- JANGAN asumsikan/tebak produk
- JANGAN hitung ulang harga
- Hasil function = sumber kebenaran MUTLAK
",

    'response_format' => "
## Format Response:

**Tampilkan ke user (TANPA ID):**
```
Dimsum Keju - Rp 40.000
Teh Jumbo - Rp 5.000
```

**JANGAN tampilkan:**
```
Dimsum Keju [ID:123] ❌
```
",
];
```

---

## 2. Enum untuk User Intent Detection

**File: `app/Enums/UserIntent.php`**

```php
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
            'cerita', 'puisi', 'coding', 'program'
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
```

---

## 3. Prompt Builder Service

**File: `app/Services/AiAgentPromptBuilder.php`**

```php
<?php

namespace App\Services;

use App\Models\AiAgent;
use App\Models\Product;
use App\Enums\UserIntent;
use Illuminate\Support\Facades\Cache;

class AiAgentPromptBuilder
{
    private AiAgent $agent;
    private int $userId;
    private ?UserIntent $intent;

    public function __construct(AiAgent $agent, int $userId, ?UserIntent $intent = null)
    {
        $this->agent = $agent;
        $this->userId = $userId;
        $this->intent = $intent;
    }

    /**
     * Build optimized system prompt based on context
     */
    public function build(): string
    {
        $sections = [
            $this->agent->system_prompt,
            $this->getCoreRules(),
        ];

        // Conditional sections berdasarkan intent
        if ($this->shouldIncludeBusinessInfo()) {
            $sections[] = $this->getBusinessInfo();
        }

        if ($this->shouldIncludeProductSamples()) {
            $sections[] = $this->getProductSamples();
        }

        if ($this->shouldIncludeOrderingWorkflow()) {
            $sections[] = $this->getOrderingWorkflow();
            $sections[] = $this->getAntiHallucinationReminder();
        }

        return implode("\n\n", array_filter($sections));
    }

    /**
     * Build prompt with caching support (for Anthropic Claude)
     */
    public function buildWithCaching(): array
    {
        return [
            [
                'type' => 'text',
                'text' => $this->agent->system_prompt . "\n\n" . $this->getCoreRules(),
                'cache_control' => ['type' => 'ephemeral'], // Static part
            ],
            [
                'type' => 'text',
                'text' => $this->getDynamicContext(), // Dynamic part
            ],
        ];
    }

    private function getCoreRules(): string
    {
        $rules = config('ai_agent_prompts.core_rules');
        return str_replace(':business_name', $this->agent->bot_name, $rules);
    }

    private function getBusinessInfo(): string
    {
        $info = $this->agent->business_info;
        if (empty($info)) {
            return '';
        }

        $lines = ["## Informasi Bisnis:"];

        $fields = [
            'operating_hours' => 'Jam Operasional',
            'address' => 'Alamat',
            'phone' => 'Telepon',
            'description' => 'Deskripsi',
        ];

        foreach ($fields as $key => $label) {
            if (!empty($info[$key])) {
                $lines[] = "{$label}: {$info[$key]}";
            }
        }

        return implode("\n", $lines);
    }

    private function getProductSamples(): string
    {
        if (!$this->agent->isOrderEnabled()) {
            return '';
        }

        $cacheKey = "ai_agent_products_{$this->userId}";
        
        $products = Cache::remember($cacheKey, 300, function () {
            return Product::where('user_id', $this->userId)
                ->where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(['id', 'name', 'price', 'stock_quantity']);
        });

        if ($products->isEmpty()) {
            return '';
        }

        $lines = ["## Sample Produk (10 teratas):"];
        
        foreach ($products as $product) {
            $price = number_format($product->price, 0, ',', '.');
            $lines[] = "- {$product->name}[ID:{$product->id}] Rp{$price} Stok:{$product->stock_quantity}";
        }

        $lines[] = "\n**PENTING**: Ini hanya sample. Untuk data lengkap: `get_all_products()`";
        $lines[] = "Jangan tampilkan [ID:X] ke user!";

        return implode("\n", $lines);
    }

    private function getOrderingWorkflow(): string
    {
        if (!$this->agent->isOrderEnabled()) {
            return '';
        }

        return config('ai_agent_prompts.ordering_workflow');
    }

    private function getAntiHallucinationReminder(): string
    {
        return config('ai_agent_prompts.anti_hallucination_reminder');
    }

    private function getDynamicContext(): string
    {
        $sections = [];

        if ($this->shouldIncludeBusinessInfo()) {
            $sections[] = $this->getBusinessInfo();
        }

        if ($this->shouldIncludeProductSamples()) {
            $sections[] = $this->getProductSamples();
        }

        if ($this->shouldIncludeOrderingWorkflow()) {
            $sections[] = $this->getOrderingWorkflow();
        }

        return implode("\n\n", array_filter($sections));
    }

    private function shouldIncludeBusinessInfo(): bool
    {
        return $this->intent === null || $this->intent->needsBusinessInfo();
    }

    private function shouldIncludeProductSamples(): bool
    {
        return $this->intent === null || $this->intent->needsProductList();
    }

    private function shouldIncludeOrderingWorkflow(): bool
    {
        return $this->agent->isOrderEnabled() && 
               ($this->intent === null || $this->intent->needsOrderWorkflow());
    }

    /**
     * Get few-shot examples for early conversations
     */
    public static function getFewShotExamples(): array
    {
        return [
            [
                'role' => 'user',
                'content' => 'menunya apa aja?',
            ],
            [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => [
                    [
                        'id' => 'call_example_1',
                        'type' => 'function',
                        'function' => [
                            'name' => 'get_all_products',
                            'arguments' => '{}',
                        ],
                    ],
                ],
            ],
            [
                'role' => 'tool',
                'tool_call_id' => 'call_example_1',
                'content' => json_encode([
                    'products' => [
                        ['name' => 'Dimsum Keju', 'price' => 40000],
                        ['name' => 'Teh Jumbo', 'price' => 5000],
                    ],
                ]),
            ],
            [
                'role' => 'assistant',
                'content' => "Berikut menu kami:\n\n1. Dimsum Keju - Rp 40.000\n2. Teh Jumbo - Rp 5.000\n\nMau pesan yang mana? 😊",
            ],
        ];
    }
}
```

---

## 4. Response Validator

**File: `app/Services/AiResponseValidator.php`**

```php
<?php

namespace App\Services;

use Illuminate\Support\Str;

class AiResponseValidator
{
    private array $errors = [];
    private array $warnings = [];

    /**
     * Validate AI response
     */
    public function validate(string $response, array $toolResults = []): bool
    {
        $this->errors = [];
        $this->warnings = [];

        $this->checkProductIdExposure($response);
        $this->checkManualCalculation($response);
        $this->checkHallucinatedProducts($response, $toolResults);
        $this->checkOffTopicResponse($response);

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Check if response exposes product IDs
     */
    private function checkProductIdExposure(string $response): void
    {
        if (preg_match('/\[ID:\d+\]/', $response)) {
            $this->errors[] = 'Product ID exposed to user';
        }
    }

    /**
     * Check if response contains manual price calculations
     */
    private function checkManualCalculation(string $response): void
    {
        // Detect patterns like: 40.000 x 2 = 80.000
        if (preg_match('/[\d.,]+\s*[x×]\s*\d+\s*=\s*[\d.,]+/', $response)) {
            $this->errors[] = 'Manual calculation detected';
        }

        // Detect "total" calculations without tool result
        if (preg_match('/total\s*[:\-]?\s*rp\s*[\d.,]+/i', $response)) {
            $this->warnings[] = 'Total amount mentioned - verify from tool result';
        }
    }

    /**
     * Check if response mentions products not in tool results
     */
    private function checkHallucinatedProducts(string $response, array $toolResults): void
    {
        if (empty($toolResults)) {
            return;
        }

        $knownProducts = $this->extractKnownProducts($toolResults);
        $mentionedProducts = $this->extractMentionedProducts($response);

        $hallucinated = array_diff($mentionedProducts, $knownProducts);

        if (!empty($hallucinated)) {
            $this->errors[] = 'Possible hallucinated products: ' . implode(', ', $hallucinated);
        }
    }

    /**
     * Check if response is off-topic
     */
    private function checkOffTopicResponse(string $response): void
    {
        $offTopicIndicators = [
            'chatgpt', 'claude', 'AI model', 'language model',
            'ibu kota', 'presiden', 'sejarah',
            'politik', 'pemilu',
        ];

        foreach ($offTopicIndicators as $indicator) {
            if (stripos($response, $indicator) !== false) {
                $this->warnings[] = 'Possible off-topic response detected';
                break;
            }
        }
    }

    /**
     * Extract known product names from tool results
     */
    private function extractKnownProducts(array $toolResults): array
    {
        $products = [];

        foreach ($toolResults as $result) {
            if (isset($result['products']) && is_array($result['products'])) {
                foreach ($result['products'] as $product) {
                    if (isset($product['name'])) {
                        $products[] = strtolower(trim($product['name']));
                    }
                }
            }

            if (isset($result['product']['name'])) {
                $products[] = strtolower(trim($result['product']['name']));
            }
        }

        return array_unique($products);
    }

    /**
     * Extract product names mentioned in response
     */
    private function extractMentionedProducts(string $response): array
    {
        $products = [];

        // Pattern: Product name followed by price
        // Example: "Dimsum Keju - Rp 40.000"
        preg_match_all('/([A-Z][a-z\s]+(?:[A-Z][a-z\s]+)*)\s*[-–]\s*Rp\s*[\d.,]+/u', $response, $matches);
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $match) {
                $products[] = strtolower(trim($match));
            }
        }

        return array_unique($products);
    }

    /**
     * Sanitize response by removing product IDs
     */
    public function sanitize(string $response): string
    {
        // Remove [ID:123] patterns
        $response = preg_replace('/\[ID:\d+\]/', '', $response);

        return $response;
    }
}
```

---

## 5. Refactored AiAgent Model

**File: `app/Models/AiAgent.php`**

```php
<?php

namespace App\Models;

use App\Services\AiAgentPromptBuilder;
use App\Enums\UserIntent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiAgent extends Model
{
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

    // Relationships
    public function whatsappAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class);
    }

    public function defaultStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'default_store_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(AiAgentConversation::class);
    }

    // Feature checks
    public function isOrderEnabled(): bool
    {
        return $this->order_enabled && $this->default_store_id !== null;
    }

    public function isQrisEnabled(): bool
    {
        return $this->qris_enabled 
            && $this->hasActiveSubMerchant()
            && $this->hasActivePaymentProvider();
    }

    // User and payment methods
    public function getUser(): ?User
    {
        return $this->whatsappAccount?->user;
    }

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

    public function hasActiveSubMerchant(): bool
    {
        return $this->getSubMerchant() !== null;
    }

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

    public function validateQrisConfiguration(): array
    {
        $errors = [];

        if (!$this->hasActiveSubMerchant()) {
            $errors[] = 'Sub-merchant belum dikonfigurasi atau tidak aktif.';
        }

        if (!$this->hasActivePaymentProvider()) {
            $errors[] = 'Payment provider belum dikonfigurasi atau tidak valid.';
        }

        return $errors;
    }

    /**
     * Build optimized system prompt
     * 
     * @param int $userId
     * @param string|null $userMessage For intent detection
     * @return string
     */
    public function buildSystemPrompt(int $userId, ?string $userMessage = null): string
    {
        $intent = $userMessage ? UserIntent::detect($userMessage) : null;
        
        $builder = new AiAgentPromptBuilder($this, $userId, $intent);
        
        return $builder->build();
    }

    /**
     * Build system prompt with caching support
     */
    public function buildSystemPromptWithCaching(int $userId, ?string $userMessage = null): array
    {
        $intent = $userMessage ? UserIntent::detect($userMessage) : null;
        
        $builder = new AiAgentPromptBuilder($this, $userId, $intent);
        
        return $builder->buildWithCaching();
    }
}
```

---

## 6. Updated Chat Service

**File: `app/Services/AiChatService.php`**

```php
<?php

namespace App\Services;

use App\Models\AiAgent;
use App\Models\AiAgentConversation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiChatService
{
    private AiAgent $agent;
    private AiResponseValidator $validator;
    private int $userId;

    public function __construct(AiAgent $agent, int $userId)
    {
        $this->agent = $agent;
        $this->userId = $userId;
        $this->validator = new AiResponseValidator();
    }

    /**
     * Process user message and get AI response
     */
    public function chat(string $userMessage, AiAgentConversation $conversation): array
    {
        $messages = $this->buildMessages($conversation, $userMessage);
        $tools = $this->getToolDefinitions();

        $response = $this->callAiApi($messages, $tools);

        // Validate response
        $toolResults = $this->extractToolResults($response);
        $assistantMessage = $response['content'] ?? '';

        if (!$this->validator->validate($assistantMessage, $toolResults)) {
            Log::warning('AI Response validation failed', [
                'agent_id' => $this->agent->id,
                'errors' => $this->validator->getErrors(),
                'response' => $assistantMessage,
            ]);

            // Optionally: sanitize or retry
            $assistantMessage = $this->validator->sanitize($assistantMessage);
        }

        return [
            'message' => $assistantMessage,
            'tool_calls' => $response['tool_calls'] ?? [],
            'validation_errors' => $this->validator->getErrors(),
            'validation_warnings' => $this->validator->getWarnings(),
        ];
    }

    /**
     * Build message array for API
     */
    private function buildMessages(AiAgentConversation $conversation, string $userMessage): array
    {
        $messages = [];

        // System prompt (optimized based on user message intent)
        $systemPrompt = $this->agent->buildSystemPrompt($this->userId, $userMessage);
        
        $messages[] = [
            'role' => 'system',
            'content' => $systemPrompt,
        ];

        // Add few-shot examples for first 3 messages
        $messageCount = $conversation->messages()->count();
        if ($messageCount < 3) {
            $examples = AiAgentPromptBuilder::getFewShotExamples();
            $messages = array_merge($messages, $examples);
        }

        // Add conversation history (last 10 messages)
        $history = $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->reverse();

        foreach ($history as $msg) {
            $messages[] = [
                'role' => $msg->role,
                'content' => $msg->content,
            ];
        }

        // Add current user message
        $messages[] = [
            'role' => 'user',
            'content' => $userMessage,
        ];

        return $messages;
    }

    /**
     * Get tool definitions for function calling
     */
    private function getToolDefinitions(): array
    {
        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_all_products',
                    'description' => 'Dapatkan semua produk aktif. Panggil saat user tanya "menunya apa?", "ada apa?", "produk apa?"',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_products',
                    'description' => 'Cari produk by keyword PENDEK. WAJIB panggil sebelum add_to_cart untuk 1 produk.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'keyword' => [
                                'type' => 'string',
                                'description' => 'Keyword PENDEK (contoh: "dimsum", "teh", "ayam"). Jangan terlalu spesifik.',
                            ],
                        ],
                        'required' => ['keyword'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_multiple_products',
                    'description' => 'Cari MULTIPLE produk sekaligus. Gunakan saat user pesan >1 item.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'keywords' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                                'description' => 'Array keyword PENDEK. Contoh: ["dimsum", "teh"]',
                            ],
                        ],
                        'required' => ['keywords'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'add_to_cart',
                    'description' => 'Tambah produk ke keranjang. Gunakan product_id dari hasil search. JANGAN hitung harga sendiri.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'products' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'product_id' => ['type' => 'integer'],
                                        'quantity' => ['type' => 'integer'],
                                    ],
                                    'required' => ['product_id', 'quantity'],
                                ],
                                'description' => 'Array produk: [{product_id: 1, quantity: 2}]',
                            ],
                        ],
                        'required' => ['products'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_cart_summary',
                    'description' => 'Lihat isi keranjang. Tampilkan PERSIS hasil function ke user.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'confirm_order',
                    'description' => 'Konfirmasi pesanan. Function auto-generate QRIS jika enabled.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'customer_name' => ['type' => 'string'],
                            'customer_phone' => ['type' => 'string'],
                            'notes' => ['type' => 'string'],
                        ],
                        'required' => ['customer_name', 'customer_phone'],
                    ],
                ],
            ],
        ];

        return $tools;
    }

    /**
     * Call AI API (OpenAI/Anthropic/etc)
     */
    private function callAiApi(array $messages, array $tools): array
    {
        // Example for OpenAI
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.openai.key'),
            'Content-Type' => 'application/json',
        ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4-turbo-preview',
            'messages' => $messages,
            'tools' => $tools,
            'tool_choice' => 'auto',
            'temperature' => 0.7,
            'max_tokens' => 1000,
        ]);

        if ($response->failed()) {
            throw new \Exception('AI API call failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'tool_calls' => $data['choices'][0]['message']['tool_calls'] ?? [],
        ];
    }

    /**
     * Extract tool results from response
     */
    private function extractToolResults(array $response): array
    {
        // This would be populated from actual tool execution results
        // For validation purposes
        return [];
    }
}
```

---

## 7. Migration untuk Settings

**File: `database/migrations/xxxx_add_prompt_settings_to_ai_agents.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_agents', function (Blueprint $table) {
            $table->boolean('use_optimized_prompt')->default(true)->after('settings');
            $table->boolean('enable_prompt_caching')->default(false)->after('use_optimized_prompt');
            $table->integer('product_sample_limit')->default(10)->after('enable_prompt_caching');
        });
    }

    public function down(): void
    {
        Schema::table('ai_agents', function (
            Blueprint $table) {
            $table->dropColumn(['use_optimized_prompt', 'enable_prompt_caching', 'product_sample_limit']);
        });
    }
};
```

---

## 8. Usage Example

**Example Controller:**

```php
<?php

namespace App\Http\Controllers;

use App\Models\AiAgent;
use App\Models\AiAgentConversation;
use App\Services\AiChatService;
use Illuminate\Http\Request;

class AiChatController extends Controller
{
    public function chat(Request $request, AiAgent $agent)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
            'conversation_id' => 'nullable|exists:ai_agent_conversations,id',
        ]);

        // Get or create conversation
        $conversation = $validated['conversation_id']
            ? AiAgentConversation::findOrFail($validated['conversation_id'])
            : $agent->conversations()->create([
                'customer_phone' => $request->input('phone'),
                'customer_name' => $request->input('name'),
            ]);

        // Process chat
        $chatService = new AiChatService($agent, $agent->getUser()->id);
        $result = $chatService->chat($validated['message'], $conversation);

        // Save messages
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $validated['message'],
        ]);

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $result['message'],
            'metadata' => [
                'tool_calls' => $result['tool_calls'],
                'validation_errors' => $result['validation_errors'],
                'validation_warnings' => $result['validation_warnings'],
            ],
        ]);

        return response()->json([
            'message' => $result['message'],
            'conversation_id' => $conversation->id,
            'has_errors' => !empty($result['validation_errors']),
        ]);
    }
}
```

---

## 9. Testing

**File: `tests/Unit/AiAgentPromptBuilderTest.php`**

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\AiAgent;
use App\Services\AiAgentPromptBuilder;
use App\Enums\UserIntent;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AiAgentPromptBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_basic_prompt_generation()
    {
        $agent = AiAgent::factory()->create([
            'bot_name' => 'Test Bot',
            'system_prompt' => 'You are a helpful assistant.',
        ]);

        $builder = new AiAgentPromptBuilder($agent, 1);
        $prompt = $builder->build();

        $this->assertStringContainsString('ATURAN INTI', $prompt);
        $this->assertStringContainsString('Test Bot', $prompt);
    }

    public function test_prompt_excludes_ordering_when_disabled()
    {
        $agent = AiAgent::factory()->create([
            'order_enabled' => false,
        ]);

        $builder = new AiAgentPromptBuilder($agent, 1);
        $prompt = $builder->build();

        $this->assertStringNotContainsString('Alur Pemesanan', $prompt);
        $this->assertStringNotContainsString('add_to_cart', $prompt);
    }

    public function test_intent_based_prompt_optimization()
    {
        $agent = AiAgent::factory()->create([
            'order_enabled' => true,
            'business_info' => [
                'operating_hours' => '09:00 - 21:00',
            ],
        ]);

        // Greeting intent - should include business info
        $builder = new AiAgentPromptBuilder($agent, 1, UserIntent::GREETING);
        $prompt = $builder->build();
        $this->assertStringContainsString('Jam Operasional', $prompt);

        // Order intent - should include workflow
        $builder = new AiAgentPromptBuilder($agent, 1, UserIntent::ORDER);
        $prompt = $builder->build();
        $this->assertStringContainsString('Alur Pemesanan', $prompt);
    }

    public function test_prompt_token_reduction()
    {
        $agent = AiAgent::factory()->create([
            'order_enabled' => true,
        ]);

        $fullPrompt = $agent->buildSystemPrompt(1);
        $optimizedPrompt = (new AiAgentPromptBuilder($agent, 1, UserIntent::GREETING))->build();

        // Optimized should be shorter
        $this->assertLessThan(
            strlen($fullPrompt),
            strlen($optimizedPrompt)
        );
    }
}
```

---

## 10. Monitoring & Analytics

**File: `app/Services/AiPromptAnalytics.php`**

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AiPromptAnalytics
{
    /**
     * Track prompt token usage
     */
    public static function trackTokenUsage(int $agentId, int $tokens, string $promptType): void
    {
        DB::table('ai_prompt_analytics')->insert([
            'ai_agent_id' => $agentId,
            'tokens_used' => $tokens,
            'prompt_type' => $promptType,
            'created_at' => now(),
        ]);
    }

    /**
     * Get average token usage
     */
    public static function getAverageTokens(int $agentId, int $days = 7): float
    {
        return DB::table('ai_prompt_analytics')
            ->where('ai_agent_id', $agentId)
            ->where('created_at', '>=', now()->subDays($days))
            ->avg('tokens_used') ?? 0;
    }

    /**
     * Get token savings from optimization
     */
    public static function getTokenSavings(int $agentId): array
    {
        $beforeOptimization = DB::table('ai_prompt_analytics')
            ->where('ai_agent_id', $agentId)
            ->where('prompt_type', 'full')
            ->avg('tokens_used') ?? 0;

        $afterOptimization = DB::table('ai_prompt_analytics')
            ->where('ai_agent_id', $agentId)
            ->where('prompt_type', 'optimized')
            ->avg('tokens_used') ?? 0;

        $savings = $beforeOptimization - $afterOptimization;
        $savingsPercent = $beforeOptimization > 0 
            ? ($savings / $beforeOptimization) * 100 
            : 0;

        return [
            'before' => round($beforeOptimization),
            'after' => round($afterOptimization),
            'savings' => round($savings),
            'savings_percent' => round($savingsPercent, 2),
        ];
    }
}
```

**Migration:**

```php
Schema::create('ai_prompt_analytics', function (Blueprint $table) {
    $table->id();
    $table->foreignId('ai_agent_id')->constrained()->onDelete('cascade');
    $table->integer('tokens_used');
    $table->string('prompt_type'); // 'full', 'optimized', 'cached'
    $table->timestamp('created_at');
    
    $table->index(['ai_agent_id', 'created_at']);
});
```

## 🚀 Quick Start

```bash
# 1. Publish config
php artisan vendor:publish --tag=ai-agent-config

# 2. Run migrations
php artisan migrate

# 3. Clear cache
php artisan cache:clear

# 4. Test
php artisan test --filter=AiAgentPromptBuilderTest
```

---