<?php

use Illuminate\Support\Facades\Route;

// Landing page
Route::get('/', function () {
    return view('welcome');
});

// Authentication routes
Route::get('/login', function () {
    return view('auth.login');
})->name('login');

Route::get('/register', function () {
    return view('auth.register');
})->name('register');

// Legal pages
Route::get('/privacy-policy', function () {
    return view('privacy-policy');
})->name('privacy-policy');

Route::get('/terms-of-service', function () {
    return view('terms-of-service');
})->name('terms-of-service');

// Dashboard routes (protected by middleware in production)
Route::middleware(['web'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard.index');
    })->name('dashboard');

    Route::get('/dashboard/contacts', function () {
        return view('dashboard.contacts');
    })->name('dashboard.contacts');

    Route::get('/dashboard/messages', function () {
        return view('dashboard.messages');
    })->name('dashboard.messages');

    Route::get('/dashboard/templates', function () {
        return view('dashboard.templates');
    })->name('dashboard.templates');

    Route::get('/dashboard/profile', function () {
        return view('dashboard.profile');
    })->name('dashboard.profile');
});
