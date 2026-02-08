<?php

return [
    // Page title and description
    'title' => 'AI Agent',
    'description' => 'Configure your WhatsApp AI Assistant',
    'configure_assistant' => 'Configure your WhatsApp AI Assistant',

    // Loading states
    'loading' => 'Loading AI Agent configuration...',

    // WhatsApp account requirement
    'whatsapp_required' => [
        'title' => 'WhatsApp Account Required',
        'message' => 'Please connect your WhatsApp Business account first before configuring AI Agent.',
        'button' => 'Connect WhatsApp Account',
    ],

    // Status card
    'status' => [
        'title' => 'AI Agent Status',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'enable' => 'Enable AI Agent',
        'save_to_activate' => 'Save configuration to activate AI Agent',
    ],

    // Bot identity section
    'identity' => [
        'title' => 'Bot Identity',
        'bot_name' => 'Bot Name',
        'bot_name_placeholder' => 'Example: My Restaurant Assistant',
        'bot_name_help' => 'Name that AI will use to introduce itself',
        'system_prompt' => 'System Prompt',
        'system_prompt_placeholder' => 'Example: You are a virtual assistant for a seafood restaurant. Your main task is to help customers in a friendly and professional manner. Answer questions about menu, prices, and operating hours. Use polite Indonesian language.',
        'system_prompt_help' => 'Basic instructions for AI Agent. Explain how the bot should behave, its function, and the language style to use.',
    ],

    // Business information section
    'business_info' => [
        'title' => 'Business Information',
        'description' => 'This information will be included in AI context to answer customer questions',
        'operating_hours' => 'Operating Hours',
        'operating_hours_placeholder' => 'Example: Monday-Friday 08:00-22:00',
        'phone' => 'Phone Number',
        'phone_placeholder' => 'Example: 021-1234567',
        'address' => 'Address',
        'address_placeholder' => 'Example: Jl. Sudirman No. 123, Central Jakarta',
        'business_description' => 'Business Description',
        'business_description_placeholder' => 'Example: Premium seafood restaurant with signature dishes of crab in padang sauce and honey grilled shrimp. Provides private rooms for family events.',
    ],

    // Order feature section
    'order' => [
        'title' => 'Order Feature',
        'description' => 'Enable this feature so AI Agent can help customers place orders',
        'select_store' => 'Select Store',
        'select_store_placeholder' => '-- Select Store --',
        'select_store_help' => 'Orders from AI Agent will go to this store',
        'no_stores' => 'No stores yet.',
        'create_store_first' => 'Create a store first',
        'enable_order' => 'Enable Order via Chat',
        'order_description' => 'Customers can view products, add to cart, and order directly via chat',
        'select_store_first' => 'Select a store first to enable this feature',
        'order_active' => 'Order feature active! AI Agent can:',
        'capabilities' => [
            'search_products' => 'Search and display products',
            'add_to_cart' => 'Add products to cart',
            'show_summary' => 'Display order summary',
            'create_order' => 'Create order after confirmation',
        ],
    ],

    // QRIS payment section
    'qris' => [
        'title' => 'QRIS Payment',
        'description' => 'Enable automatic QRIS payment when customers confirm purchase',
        'enable_qris' => 'Enable QRIS Payment',
        'qris_enabled_description' => 'QRIS will be automatically generated when customers confirm purchase',
        'qris_disabled_description' => 'Manual payment - customers will be directed to cashier',
        'qris_active' => 'QRIS Payment active! When customers confirm purchase:',
        'qris_capabilities' => [
            'auto_generate' => 'QRIS will be automatically generated with order total price',
            'scan_to_pay' => 'Customers can scan QR code to pay',
            'confirmation' => 'After successful payment, AI will send confirmation with Order ID, product name, and total price',
        ],
        'provider_settings_note' => 'Make sure payment provider is configured in',
        'provider_settings_link' => 'Provider Settings',
        'manual_mode' => 'Manual Mode - When customers confirm purchase:',
        'manual_capabilities' => [
            'send_summary' => 'AI will send order summary (Order ID, product name, total price)',
            'pending_status' => 'Payment status: Pending',
            'show_to_cashier' => 'Customers are asked to show this message to cashier for processing',
        ],
    ],

    // Action buttons
    'actions' => [
        'test' => 'Test AI Agent',
        'save' => 'Save Configuration',
        'saving' => 'Saving...',
        'last_saved' => 'Last saved:',
        'not_saved' => 'Configuration has never been saved',
        'complete_required' => 'Complete Bot Name and System Prompt to save configuration',
        'enable_to_test' => 'Enable AI Agent and save configuration to test',
    ],

    // Test modal
    'test_modal' => [
        'title' => 'Test AI Agent',
        'description' => 'Send a test message to see how AI Agent responds',
        'message_placeholder' => 'Type your test message...',
        'send' => 'Send',
        'sending' => 'Sending...',
        'conversation_history' => 'Conversation History',
        'reset' => 'Reset Conversation',
        'close' => 'Close',
        'you' => 'You',
        'ai' => 'AI',
    ],

    // Conversation history
    'conversation' => [
        'title' => 'Conversation History',
        'no_conversations' => 'No conversations yet',
        'view_details' => 'View Details',
        'reset_confirm' => 'Are you sure you want to reset this conversation?',
        'reset_success' => 'Conversation reset successfully',
        'reset_error' => 'Failed to reset conversation',
    ],

    // API response messages
    'messages' => [
        'not_connected' => 'WhatsApp account not connected',
        'not_configured' => 'AI Agent not configured yet',
        'config_retrieved' => 'AI Agent configuration retrieved',
        'config_saved' => 'AI Agent configuration saved successfully',
        'config_save_failed' => 'Failed to save AI Agent configuration',
        'activated' => 'AI Agent activated',
        'deactivated' => 'AI Agent deactivated',
        'toggle_failed' => 'Failed to toggle AI Agent status',
        'order_enabled' => 'Order feature enabled',
        'order_disabled' => 'Order feature disabled',
        'order_toggle_failed' => 'Failed to toggle order feature',
        'set_store_first' => 'Cannot enable order feature. Please set a default store first.',
        'qris_enabled' => 'QRIS feature enabled',
        'qris_disabled' => 'QRIS feature disabled',
        'qris_toggle_failed' => 'Failed to toggle QRIS feature',
        'qris_not_ready' => 'Cannot enable QRIS feature',
        'test_processed' => 'Test message processed',
        'test_failed' => 'Failed to process test message',
        'invalid_store' => 'Invalid store. Store not found or does not belong to you.',
        'connect_whatsapp_first' => 'WhatsApp account not connected. Please connect your WhatsApp Business Account first.',
    ],

    // Error messages
    'errors' => [
        'general' => 'An error occurred. Please try again.',
        'network' => 'Network error. Please check your connection.',
        'timeout' => 'Request timeout. Please try again.',
        'invalid_response' => 'Invalid response from server.',
        'missing_config' => 'Configuration is incomplete.',
        'tool_not_found' => 'Function not recognized.',
        'invalid_parameters' => 'Invalid parameters provided.',
    ],

    // Settings
    'settings' => [
        'title' => 'Settings',
        'advanced' => 'Advanced Settings',
        'temperature' => 'Temperature',
        'temperature_help' => 'Controls randomness in responses (0-1)',
        'max_tokens' => 'Max Tokens',
        'max_tokens_help' => 'Maximum length of response',
    ],

    // Validation
    'validation' => [
        'bot_name_required' => 'Bot name is required',
        'system_prompt_required' => 'System prompt is required',
        'store_required' => 'Store is required when order feature is enabled',
        'message_required' => 'Message is required',
    ],
];
