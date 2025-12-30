<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Conversation Summarization Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for the conversation
    | summarization feature used by the AI Agent.
    |
    */

    'summarization' => [
        /*
        |--------------------------------------------------------------------------
        | Enable/Disable Summarization
        |--------------------------------------------------------------------------
        |
        | This option allows you to enable or disable the conversation
        | summarization feature globally.
        |
        */
        'enabled' => env('CONVERSATION_SUMMARIZATION_ENABLED', true),

        /*
        |--------------------------------------------------------------------------
        | Message Count Threshold
        |--------------------------------------------------------------------------
        |
        | The minimum number of messages in a conversation before summarization
        | is triggered. Default is 6 messages.
        |
        */
        'message_count_threshold' => env('CONVERSATION_MESSAGE_COUNT_THRESHOLD', 6),

        /*
        |--------------------------------------------------------------------------
        | Token Threshold
        |--------------------------------------------------------------------------
        |
        | The minimum number of estimated tokens in a conversation before
        | summarization is triggered. Default is 800 tokens.
        |
        */
        'token_threshold' => env('CONVERSATION_TOKEN_THRESHOLD', 800),

        /*
        |--------------------------------------------------------------------------
        | Minimum Substantive Messages
        |--------------------------------------------------------------------------
        |
        | The minimum number of substantive messages (messages longer than 5
        | characters) required before summarization is triggered. Default is 3.
        |
        */
        'min_substantive_messages' => env('CONVERSATION_MIN_SUBSTANTIVE_MESSAGES', 3),

        /*
        |--------------------------------------------------------------------------
        | Recent Messages Count
        |--------------------------------------------------------------------------
        |
        | The number of recent messages to include alongside the summary when
        | providing context to the LLM. Default is 3.
        |
        */
        'recent_messages_count' => env('CONVERSATION_RECENT_MESSAGES_COUNT', 3),

        /*
        |--------------------------------------------------------------------------
        | LLM Temperature
        |--------------------------------------------------------------------------
        |
        | The temperature setting for the LLM when generating summaries.
        | Lower values (0.1-0.3) produce more consistent output. Default is 0.3.
        |
        */
        'llm_temperature' => env('CONVERSATION_SUMMARIZATION_TEMPERATURE', 0.3),

        /*
        |--------------------------------------------------------------------------
        | LLM Max Tokens
        |--------------------------------------------------------------------------
        |
        | The maximum number of tokens the LLM can generate for a summary.
        | Default is 500 tokens.
        |
        */
        'llm_max_tokens' => env('CONVERSATION_SUMMARIZATION_MAX_TOKENS', 500),
    ],

];
