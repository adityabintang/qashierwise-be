<?php

return [
    'title' => 'Login',
    'heading' => 'Sign in to your account',
    'actions' => [
        'request_password_reset' => [
            'label' => 'Forgot password?',
        ],
    ],
    'form' => [
        'email' => [
            'label' => 'Email address',
        ],
        'password' => [
            'label' => 'Password',
        ],
        'remember' => [
            'label' => 'Remember me',
        ],
        'actions' => [
            'authenticate' => [
                'label' => 'Sign in',
            ],
        ],
    ],
    'messages' => [
        'failed' => 'These credentials do not match our records.',
    ],
    'notifications' => [
        'throttled' => [
            'title' => 'Too many login attempts',
            'body' => 'Please try again in :seconds seconds.',
        ],
    ],
];
