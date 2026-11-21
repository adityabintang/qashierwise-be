<?php

return [
    'free_enabled' => env('WHATSAPP_EXPORTS_FREE_ENABLED', true),
    'max_rows' => (int) env('WHATSAPP_EXPORTS_MAX_ROWS', 25000),
    'max_bytes' => (int) env('WHATSAPP_EXPORTS_MAX_BYTES', 26214400),
];
