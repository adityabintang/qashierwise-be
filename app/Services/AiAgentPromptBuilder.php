<?php

namespace App\Services;

use App\Enums\UserIntent;
use App\Models\AiAgent;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Sbsaga\Toon\Facades\Toon;

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
     * Target: <1000 tokens for menu/checkout/order operations
     */
    public function build(): string
    {
        // For simple intents (greeting, off-topic), use minimal prompt to save tokens
        if ($this->isSimpleIntent()) {
            return $this->buildMinimalPrompt();
        }

        // ALWAYS use ultra-compact mode for ordering operations (including unknown/null intent)
        // This is the key optimization to achieve <1000 tokens
        if ($this->isOrderingIntent() || $this->intent === null || $this->intent === UserIntent::UNKNOWN) {
            return $this->buildUltraCompactPrompt();
        }

        // Check if caching is enabled (fallback for other intents)
        if ($this->agent->enable_prompt_caching ?? false) {
            return $this->buildWithCaching();
        }

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

        if ($this->shouldIncludeReservationInstructions()) {
            $sections[] = $this->getReservationInstructions();
        }

        return implode("\n\n", array_filter($sections));
    }

    /**
     * Check if this is an ordering-related intent
     */
    private function isOrderingIntent(): bool
    {
        if ($this->intent === null) {
            return false;
        }

        return in_array($this->intent, [
            UserIntent::VIEW_MENU,
            UserIntent::SEARCH_PRODUCT,
            UserIntent::ORDER,
            UserIntent::VIEW_CART,
            UserIntent::CHECKOUT,
        ]);
    }

    /**
     * Build ultra-compact prompt for ordering operations
     * Target: ~200-300 tokens for system prompt only
     */
    private function buildUltraCompactPrompt(): string
    {
        $botName = $this->agent->bot_name;

        // Ultra-compact system context (~50 tokens)
        $prompt = "Asisten {$botName}. ";

        // Use appropriate core rules based on order_enabled
        $configKey = $this->agent->isOrderEnabled()
            ? 'ai_agent_prompts.core_rules'
            : 'ai_agent_prompts.core_rules_without_ordering';
        $prompt .= config($configKey) ?? config('ai_agent_prompts.core_rules');

        // Only add ordering workflow if enabled
        if ($this->agent->isOrderEnabled()) {
            $prompt .= "\n".config('ai_agent_prompts.ordering_workflow');
        }

        if ($this->agent->isDeliveryEnabled()) {
            $deliveryPrompt = config('ai_agent_prompts.delivery_workflow');
            $deliveryPrompt = str_replace(':ongkir', number_format($this->agent->default_ongkir, 0, ',', '.'), $deliveryPrompt);
            $prompt .= "\n".$deliveryPrompt;
        }

        if ($this->shouldIncludeReservationInstructions()) {
            $reservationInstructions = $this->getReservationInstructions();
            if ($reservationInstructions !== '') {
                $prompt .= "\n".$reservationInstructions;
            }
        }

        return str_replace(':business_name', $botName, $prompt);
    }

    /**
     * Check if this is a simple intent that needs minimal prompt
     */
    private function isSimpleIntent(): bool
    {
        if ($this->intent === null) {
            return false;
        }

        return in_array($this->intent, [
            UserIntent::GREETING,
            UserIntent::OFF_TOPIC,
        ]);
    }

    /**
     * Build minimal prompt for simple intents (greeting, off-topic)
     * Saves ~800-1500 tokens compared to full prompt
     */
    private function buildMinimalPrompt(): string
    {
        $botName = $this->agent->bot_name;
        $businessInfo = $this->agent->business_info;

        // For greeting - friendly welcome with basic info
        if ($this->intent === UserIntent::GREETING) {
            $prompt = "Kamu adalah {$botName}, asisten virtual yang ramah.\n";
            $prompt .= "Tugas: menyapa pelanggan dan membantu pemesanan.\n";

            // Add minimal business info if available
            if (! empty($businessInfo['operating_hours'])) {
                $prompt .= "Jam buka: {$businessInfo['operating_hours']}\n";
            }

            $prompt .= "\nBahas singkat dan ramah. Tawarkan untuk lihat menu atau bantu pesan.";
            $prompt .= "\nContoh: 'Halo! Selamat datang di {$botName}. Ada yang bisa saya bantu? Ketik \"menu\" untuk lihat daftar menu kami.'";

            return $prompt;
        }

        // For off-topic - polite redirection
        if ($this->intent === UserIntent::OFF_TOPIC) {
            return "Kamu adalah {$botName}, asisten pemesanan.\n".
                   "Jika ditanya di luar topik (bukan tentang menu/pesanan/bisnis), jawab:\n".
                   "'Maaf, saya hanya bisa membantu untuk pemesanan di {$botName}. Ketik \"menu\" untuk lihat daftar menu kami.'";
        }

        // Fallback to custom system prompt only
        return $this->agent->system_prompt;
    }

    /**
     * Build prompt with caching strategy (separates static and dynamic parts)
     */
    public function buildWithCaching(): string
    {
        // For non-Anthropic APIs, we use Laravel cache to store static parts
        $cacheKey = "ai_prompt_static_{$this->agent->id}";

        $staticPart = Cache::remember($cacheKey, 3600, function () {
            return $this->agent->system_prompt."\n\n".$this->getCoreRules();
        });

        $dynamicParts = [];

        // Dynamic sections that change based on context
        if ($this->shouldIncludeBusinessInfo()) {
            $dynamicParts[] = $this->getBusinessInfo();
        }

        if ($this->shouldIncludeProductSamples()) {
            $dynamicParts[] = $this->getProductSamples();
        }

        if ($this->shouldIncludeOrderingWorkflow()) {
            $dynamicParts[] = $this->getOrderingWorkflow();
            $dynamicParts[] = $this->getAntiHallucinationReminder();
        }

        if ($this->shouldIncludeReservationInstructions()) {
            $dynamicParts[] = $this->getReservationInstructions();
        }

        return $staticPart."\n\n".implode("\n\n", array_filter($dynamicParts));
    }

    /**
     * Build prompt with Anthropic Claude prompt caching format
     * Returns array format for Anthropic API
     */
    public function buildForAnthropicCaching(): array
    {
        return [
            [
                'type' => 'text',
                'text' => $this->agent->system_prompt."\n\n".$this->getCoreRules(),
                'cache_control' => ['type' => 'ephemeral'], // Static part - cached
            ],
            [
                'type' => 'text',
                'text' => $this->getDynamicContext(), // Dynamic part - not cached
            ],
        ];
    }

    /**
     * Get dynamic context for caching
     */
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

        if ($this->shouldIncludeReservationInstructions()) {
            $sections[] = $this->getReservationInstructions();
        }

        return implode("\n\n", array_filter($sections));
    }

    private function getCoreRules(): string
    {
        // Use TOON-optimized rules if enabled
        if ($this->agent->use_toon_format ?? false) {
            $configKey = $this->agent->isOrderEnabled()
                ? 'ai_agent_prompts.toon_core_rules'
                : 'ai_agent_prompts.toon_core_rules_without_ordering';
        } else {
            $configKey = $this->agent->isOrderEnabled()
                ? 'ai_agent_prompts.core_rules'
                : 'ai_agent_prompts.core_rules_without_ordering';
        }

        $rules = config($configKey) ?? config('ai_agent_prompts.core_rules');

        return str_replace(':business_name', $this->agent->bot_name, $rules);
    }

    private function getBusinessInfo(): string
    {
        $info = $this->agent->business_info;
        if (empty($info)) {
            return '';
        }

        // Use compact format for TOON mode
        if ($this->agent->use_toon_format ?? false) {
            $lines = ['## Info:'];
            $fields = ['operating_hours' => 'Jam', 'address' => 'Alamat', 'phone' => 'Tel'];

            foreach ($fields as $key => $label) {
                if (! empty($info[$key])) {
                    $lines[] = "{$label}: {$info[$key]}";
                }
            }

            return implode("\n", $lines);
        }

        $lines = ['## Informasi Bisnis:'];

        $fields = [
            'operating_hours' => 'Jam Operasional',
            'address' => 'Alamat',
            'phone' => 'Telepon',
            'description' => 'Deskripsi',
        ];

        foreach ($fields as $key => $label) {
            if (! empty($info[$key])) {
                $lines[] = "{$label}: {$info[$key]}";
            }
        }

        return implode("\n", $lines);
    }

    private function getProductSamples(): string
    {
        if (! $this->agent->isOrderEnabled()) {
            return '';
        }

        // Limit products for token efficiency (5 for TOON, 5 for legacy)
        $limit = 5;
        $cacheKey = "ai_agent_products_{$this->userId}_limit{$limit}_v2";

        // Clear old cache format
        Cache::forget("ai_agent_products_{$this->userId}");
        Cache::forget("ai_agent_products_{$this->userId}_limit{$limit}");

        $products = Cache::remember($cacheKey, 300, function () use ($limit) {
            return Product::where('user_id', $this->userId)
                ->where('is_active', true)
                ->with('category:id,name')
                ->orderBy('category_id', 'asc')
                ->orderBy('name', 'asc')
                ->limit($limit)
                ->get(['id', 'name', 'price', 'stock_quantity', 'category_id']);
        });

        if ($products->isEmpty()) {
            return '';
        }

        // Group by category
        $groupedProducts = $products->groupBy(function ($product) {
            return $product->category?->name ?? 'Lainnya';
        });

        // Get total product count for info
        $totalProducts = Product::where('user_id', $this->userId)
            ->where('is_active', true)
            ->count();

        // Use TOON format if enabled (saves ~67% tokens with sbsaga/toon)
        if ($this->agent->use_toon_format ?? false) {
            $categorizedArray = [];
            foreach ($groupedProducts as $categoryName => $categoryProducts) {
                $categorizedArray[$categoryName] = $categoryProducts->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'price' => $p->price,
                    'stock' => $p->stock_quantity,
                ])->toArray();
            }

            $lines = ['## Produk (Sample):'];
            $lines[] = Toon::convert(['menu' => $categorizedArray]);
            $lines[] = "Total: {$totalProducts} produk | Full: get_all_products(page=1) | 10/page | Hide ID";

            return implode("\n", $lines);
        }

        // Legacy text format - grouped by category
        $lines = ["## Sample Produk ({$limit} dari {$totalProducts} produk):"];

        foreach ($groupedProducts as $categoryName => $categoryProducts) {
            $lines[] = "\n**{$categoryName}**";
            foreach ($categoryProducts as $product) {
                $price = number_format($product->price, 0, ',', '.');
                $lines[] = "- {$product->name} [ID:{$product->id}] Rp{$price} Stok:{$product->stock_quantity}";
            }
        }

        $lines[] = "\n**PENTING**: Ini hanya sample. Untuk menu lengkap: `get_all_products(page=1)` (10 produk per halaman, dikategorikan)";
        $lines[] = "Jika user minta 'menu lainnya': `get_all_products(page=2)`, dst.";
        $lines[] = 'Jangan tampilkan [ID:X] ke user!';

        return implode("\n", $lines);
    }

    private function getOrderingWorkflow(): string
    {
        if (! $this->agent->isOrderEnabled()) {
            return '';
        }

        // Use TOON-optimized workflow if enabled
        $configKey = ($this->agent->use_toon_format ?? false)
            ? 'ai_agent_prompts.toon_ordering_workflow'
            : 'ai_agent_prompts.ordering_workflow';

        return config($configKey);
    }

    private function getAntiHallucinationReminder(): string
    {
        return config('ai_agent_prompts.anti_hallucination_reminder');
    }

    private function getReservationInstructions(): string
    {
        $reservationLink = $this->agent->getReservationFormUrl();
        if (! $reservationLink) {
            return '';
        }

        $template = config('ai_agent_prompts.reservation_instructions');
        if (! $template) {
            return '';
        }

        return str_replace(':reservation_link', $reservationLink, $template);
    }

    private function shouldIncludeBusinessInfo(): bool
    {
        // Only include for greeting, business_info, or unknown intents
        if ($this->intent === null) {
            return true;
        }

        return in_array($this->intent, [
            UserIntent::GREETING,
            UserIntent::BUSINESS_INFO,
            UserIntent::UNKNOWN,
        ]);
    }

    /**
     * Product list is no longer injected into the system prompt — that was
     * the root cause of "AI hallucinates a product that doesn't exist".
     * The LLM now learns about products exclusively through the
     * `get_all_products` tool, which always returns ground truth from the DB.
     */
    private function shouldIncludeProductSamples(): bool
    {
        return false;
    }

    private function shouldIncludeOrderingWorkflow(): bool
    {
        if (! $this->agent->isOrderEnabled()) {
            return false;
        }

        // Only include for ordering-related intents
        if ($this->intent === null) {
            return true;
        }

        return in_array($this->intent, [
            UserIntent::VIEW_MENU,
            UserIntent::SEARCH_PRODUCT,
            UserIntent::ORDER,
            UserIntent::VIEW_CART,
            UserIntent::CHECKOUT,
            UserIntent::UNKNOWN,
        ]);
    }

    private function shouldIncludeReservationInstructions(): bool
    {
        return $this->agent->isReservationEnabled();
    }

    // /**
    //  * Get few-shot examples for early conversations
    //  */
    // public static function getFewShotExamples(): array
    // {
    //     return [
    //         [
    //             'role' => 'user',
    //             'content' => 'menunya apa aja?',
    //         ],
    //         [
    //             'role' => 'assistant',
    //             'content' => null,
    //             'tool_calls' => [
    //                 [
    //                     'id' => 'call_example_1',
    //                     'type' => 'function',
    //                     'function' => [
    //                         'name' => 'get_all_products',
    //                         'arguments' => '{}',
    //                     ],
    //                 ],
    //             ],
    //         ],
    //         [
    //             'role' => 'tool',
    //             'tool_call_id' => 'call_example_1',
    //             'content' => json_encode([
    //                 'products' => [
    //                     ['name' => 'Dimsum Keju', 'price' => 40000],
    //                     ['name' => 'Teh Jumbo', 'price' => 5000],
    //                 ],
    //             ]),
    //         ],
    //         [
    //             'role' => 'assistant',
    //             'content' => "Berikut menu kami:\n\n1. Dimsum Keju - Rp 40.000\n2. Teh Jumbo - Rp 5.000\n\nMau pesan yang mana? 😊",
    //         ],
    //     ];
    // }
}
