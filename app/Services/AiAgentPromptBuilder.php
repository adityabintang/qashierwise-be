<?php

namespace App\Services;

use App\Enums\UserIntent;
use App\Helpers\ToonFormatter;
use App\Models\AiAgent;
use App\Models\Product;
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
        // Check if caching is enabled
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

        return implode("\n\n", array_filter($sections));
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

        return implode("\n\n", array_filter($sections));
    }

    private function getCoreRules(): string
    {
        // Use TOON-optimized rules if enabled
        $configKey = ($this->agent->use_toon_format ?? false)
            ? 'ai_agent_prompts.toon_core_rules'
            : 'ai_agent_prompts.core_rules';

        $rules = config($configKey);

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

        // Limit to 5 products in TOON mode for token efficiency
        $limit = ($this->agent->use_toon_format ?? false) ? 5 : 10;
        $cacheKey = "ai_agent_products_{$this->userId}_limit{$limit}";

        // Clear old cache format
        Cache::forget("ai_agent_products_{$this->userId}");

        $products = Cache::remember($cacheKey, 300, function () use ($limit) {
            return Product::where('user_id', $this->userId)
                ->where('is_active', true)
                ->orderBy('name', 'asc')
                ->limit($limit)
                ->get(['id', 'name', 'price', 'stock_quantity']);
        });

        if ($products->isEmpty()) {
            return '';
        }

        // Use TOON format if enabled (saves ~35% tokens)
        if ($this->agent->use_toon_format ?? false) {
            $productArray = $products->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => $p->price,
                'stock' => $p->stock_quantity,
            ])->toArray();

            $lines = ['## Produk:'];
            $lines[] = ToonFormatter::encodeProducts($productArray);
            $lines[] = 'Full: get_all_products() | Hide ID';

            return implode("\n", $lines);
        }

        // Legacy text format
        $lines = ['## Sample Produk (10 teratas):'];

        foreach ($products as $product) {
            $price = number_format($product->price, 0, ',', '.');
            $lines[] = "- {$product->name} [ID:{$product->id}] Rp{$price} Stok:{$product->stock_quantity}";
        }

        $lines[] = "\n**PENTING**: Ini hanya sample. Untuk data lengkap: `get_all_products()`";
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
