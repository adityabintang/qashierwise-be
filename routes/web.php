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

// Dashboard routes (protected by authentication middleware)
Route::middleware(['web', 'check.web.auth'])->group(function () {
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

    Route::get('/dashboard/whatsapp-account', function () {
        return view('dashboard.whatsapp-account');
    })->name('dashboard.whatsapp-account');

    Route::get('/dashboard/ai-agent', function () {
        return view('dashboard.ai-agent');
    })->name('dashboard.ai-agent');

    // POS Routes
    Route::prefix('dashboard/pos')->name('dashboard.pos.')->group(function () {
        Route::get('/products', function () {
            return view('dashboard.pos.products');
        })->name('products');

        Route::get('/categories', function () {
            return view('dashboard.pos.categories');
        })->name('categories');

        Route::get('/orders', function () {
            return view('dashboard.pos.orders');
        })->name('orders');

        Route::get('/payment', function () {
            return view('dashboard.pos.payment');
        })->name('payment');

        Route::get('/stores', function () {
            return view('dashboard.pos.stores');
        })->name('stores');

        Route::get('/tables', function () {
            return view('dashboard.pos.tables');
        })->name('tables');

        Route::get('/users', function () {
            return view('dashboard.pos.users');
        })->name('users');

        Route::get('/reports', function () {
            return view('dashboard.pos.reports');
        })->name('reports');

        Route::get('/transactions', function () {
            return view('dashboard.pos.transactions');
        })->name('transactions');
    });
});
