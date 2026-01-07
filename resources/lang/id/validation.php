<?php

return [
    'required' => 'Kolom :attribute wajib diisi.',
    'email' => 'Kolom :attribute harus berupa alamat email yang valid.',
    'min' => 'Kolom :attribute harus minimal :min karakter.',
    'max' => 'Kolom :attribute tidak boleh lebih dari :max karakter.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'unique' => 'Nilai :attribute sudah digunakan.',
    'regex' => 'Format :attribute tidak valid.',
    'date' => ':attribute bukan tanggal yang valid.',
    'date_format' => ':attribute tidak sesuai dengan format :format.',
    'numeric' => 'Kolom :attribute harus berupa angka.',
    'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
    'string' => 'Kolom :attribute harus berupa teks.',
    'array' => 'Kolom :attribute harus berupa array.',
    'in' => 'Nilai :attribute yang dipilih tidak valid.',
    'not_in' => 'Nilai :attribute yang dipilih tidak valid.',
    'between' => 'Kolom :attribute harus antara :min dan :max.',
    'size' => 'Kolom :attribute harus :size.',
    'url' => 'Format :attribute tidak valid.',
    'active_url' => ':attribute bukan URL yang valid.',
    'ip' => 'Kolom :attribute harus berupa alamat IP yang valid.',
    'json' => 'Kolom :attribute harus berupa string JSON yang valid.',
    'mimes' => 'Kolom :attribute harus berupa file dengan tipe: :values.',
    'file' => 'Kolom :attribute harus berupa file.',
    'image' => 'Kolom :attribute harus berupa gambar.',
    'before' => 'Kolom :attribute harus berupa tanggal sebelum :date.',
    'after' => 'Kolom :attribute harus berupa tanggal setelah :date.',

    /*
    |--------------------------------------------------------------------------
    | Pesan Validasi Kustom
    |--------------------------------------------------------------------------
    */
    'custom' => [
        'opening_time' => [
            'date_format' => 'Jam buka harus dalam format HH:MM:SS.',
        ],
        'closing_time' => [
            'date_format' => 'Jam tutup harus dalam format HH:MM:SS.',
        ],
        'blocked_times' => [
            'array' => 'Waktu yang diblokir harus berupa array.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Atribut Validasi Kustom
    |--------------------------------------------------------------------------
    */
    'attributes' => [
        'opening_time' => 'jam buka',
        'closing_time' => 'jam tutup',
        'blocked_times' => 'waktu diblokir',
    ],

    /*
    |--------------------------------------------------------------------------
    | Nilai Validasi Kustom
    |--------------------------------------------------------------------------
    */
    'values' => [],

    /*
    |--------------------------------------------------------------------------
    | Laravel Default Messages
    |--------------------------------------------------------------------------
    */
    '(and :count more errors)' => '(dan :count error lainnya)',
];
