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
    'core_rules' => "RULES:fn_data_only|search_first|empty=skip|HIDE_ID|scope:menu,order
Format:'Nama-RpHarga'|Off-topic:'Maaf,saya :business_name untuk pemesanan.'",

    // Minimal workflow (~40 tokens)
    'ordering_workflow' => 'FLOW:menu→get_all(p=1)|more→p+1|search→keyword|multi→search_multi|order→add_to_cart(id,qty)',

    // Anti-hallucination - already minimal (~15 tokens)
    'anti_hallucination_reminder' => 'empty=N/A|fn=truth|HIDE_ID',

    // Remove verbose format - LLM understands from examples
    'response_format' => '',

    /*
    |--------------------------------------------------------------------------
    | TOON Format - Ultra Compact (~50 tokens total)
    |--------------------------------------------------------------------------
    */

    'toon_core_rules' => "R:fn_only|empty=skip|HIDE_ID|'Nama-RpHarga'",

    'toon_ordering_workflow' => 'F:menu→get_all|search→kw|order→add_to_cart',

    'toon_response_format' => '',

    /*
    |--------------------------------------------------------------------------
    | Minimal Tool Definitions (reduced descriptions)
    |--------------------------------------------------------------------------
    */

    'minimal_tools' => [
        'get_all_products' => 'Get menu (20/page)',
        'search_products' => 'Search 1 product',
        'search_multiple_products' => 'Search n products',
        'add_to_cart' => 'Add to cart',
        'get_cart_summary' => 'Cart summary',
        'confirm_order' => 'Confirm order',
    ],
];
