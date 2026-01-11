<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Agent Prompts - Ultra-Optimized for <1000 Token Target
    |--------------------------------------------------------------------------
    |
    | Token targets per operation:
    | - Menu display: ~600 tokens (prompt + tools)
    | - Checkout: ~500 tokens
    | - Create order: ~500 tokens
    |
    */

    // Ultra-compact core rules (~80 tokens)
    'core_rules' => "RULES:
1. NEVER invent/hallucinate menu items or prices
2. For MENU request: call get_all_products()
3. For ORDER request: call add_to_cart() DIRECTLY with product names - NO search needed!
4. add_to_cart auto-searches products, supports multiple items at once
5. Format: 'Nama - RpHarga'
6. HIDE product IDs from user
7. If product not found: say 'tidak tersedia'
8. Off-topic: 'Maaf, saya :business_name untuk pemesanan.'
9. Cancel specific item: use remove_from_cart(product_name)
10. Cancel all: use clear_cart()",

    // Minimal workflow (~40 tokens)
    'ordering_workflow' => 'FLOW:menu→get_all_products|order→add_to_cart(items=[{product_name,quantity}]) DIRECTLY|cancel_item→remove_from_cart(name)|cancel_all→clear_cart',

    // Anti-hallucination - CRITICAL: Prevent LLM from making up menu items
    'anti_hallucination_reminder' => '⚠️ NEVER invent menu! For orders: use add_to_cart() DIRECTLY with product names. NO need to search first!',

    // Remove verbose format - LLM understands from examples
    'response_format' => '',

    /*
    |--------------------------------------------------------------------------
    | TOON Format - Ultra Compact (~50 tokens total)
    |--------------------------------------------------------------------------
    */

    'toon_core_rules' => "R:fn_only|empty=skip|HIDE_ID|'Nama-RpHarga'|order→add_to_cart DIRECT",

    'toon_ordering_workflow' => 'F:menu→get_all|order→add_to_cart(items) DIRECT|cancel→remove_from_cart(name)|clear→clear_cart',

    'toon_response_format' => '',

    /*
    |--------------------------------------------------------------------------
    | Minimal Tool Definitions (reduced descriptions)
    |--------------------------------------------------------------------------
    */

    'minimal_tools' => [
        'get_all_products' => 'Show menu (20/page)',
        'add_to_cart' => 'DIRECT add by name (auto-search)',
        'remove_from_cart' => 'Remove specific item by name',
        'clear_cart' => 'Clear all cart items',
        'get_cart_summary' => 'Cart summary',
        'confirm_order' => 'Confirm order',
    ],
];
