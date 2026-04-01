<?php

return [
    'title' => 'Masuk',
    'heading' => 'Masuk ke akun Anda',
    'actions' => [
        'request_password_reset' => [
            'label' => 'Lupa kata sandi?',
        ],
    ],
    'form' => [
        'email' => [
            'label' => 'Alamat email',
        ],
        'password' => [
            'label' => 'Kata sandi',
        ],
        'remember' => [
            'label' => 'Ingat saya',
        ],
        'actions' => [
            'authenticate' => [
                'label' => 'Masuk',
            ],
        ],
    ],
    'messages' => [
        'failed' => 'Kredensial yang diberikan tidak dapat ditemukan.',
    ],
    'notifications' => [
        'throttled' => [
            'title' => 'Terlalu banyak percobaan masuk',
            'body' => 'Silakan coba lagi dalam :seconds detik.',
        ],
    ],
];
