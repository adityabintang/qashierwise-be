<?php

return [
    'required' => 'The :attribute field is required.',
    'email' => 'The :attribute must be a valid email address.',
    'min' => 'The :attribute must be at least :min characters.',
    'max' => 'The :attribute must not exceed :max characters.',
    'confirmed' => 'The :attribute confirmation does not match.',
    'unique' => 'The :attribute has already been taken.',
    'regex' => 'The :attribute format is invalid.',
    'date' => 'The :attribute is not a valid date.',
    'date_format' => 'The :attribute does not match the format :format.',
    'numeric' => 'The :attribute must be a number.',
    'integer' => 'The :attribute must be an integer.',
    'string' => 'The :attribute must be a string.',
    'array' => 'The :attribute must be an array.',
    'in' => 'The selected :attribute is invalid.',
    'not_in' => 'The selected :attribute is invalid.',
    'between' => 'The :attribute must be between :min and :max.',
    'size' => 'The :attribute must be :size.',
    'url' => 'The :attribute format is invalid.',
    'active_url' => 'The :attribute is not a valid URL.',
    'ip' => 'The :attribute must be a valid IP address.',
    'json' => 'The :attribute must be a valid JSON string.',
    'mimes' => 'The :attribute must be a file of type: :values.',
    'file' => 'The :attribute must be a file.',
    'image' => 'The :attribute must be an image.',
    'before' => 'The :attribute must be a date before :date.',
    'after' => 'The :attribute must be a date after :date.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Messages
    |--------------------------------------------------------------------------
    */
    'custom' => [
        'opening_time' => [
            'date_format' => 'The opening time must be in HH:MM:SS format.',
        ],
        'closing_time' => [
            'date_format' => 'The closing time must be in HH:MM:SS format.',
        ],
        'blocked_times' => [
            'array' => 'The blocked times must be an array.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    */
    'attributes' => [
        'opening_time' => 'opening time',
        'closing_time' => 'closing time',
        'blocked_times' => 'blocked times',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Values
    |--------------------------------------------------------------------------
    */
    'values' => [],

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines (Laravel defaults)
    |--------------------------------------------------------------------------
    */
    '(and :count more errors)' => '(and :count more errors)',
];
