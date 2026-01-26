<?php

return [
    'expiration_minutes' => env('OTP_EXPIRATION_MINUTES', 10),
    'length' => env('OTP_LENGTH', 6),
    'rate_limit_max' => env('OTP_RATE_LIMIT_MAX', 3),
    'rate_limit_minutes' => env('OTP_RATE_LIMIT_MINUTES', 1),
];
