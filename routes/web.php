<?php

use App\Http\Controllers\BlogController;
use Illuminate\Support\Facades\Route;

// Language switching route
Route::get('/language/{locale}', function ($locale) {
    if (! in_array($locale, config('app.supported_locales'))) {
        abort(400);
    }

    session(['locale' => $locale]);

    if (auth()->check()) {
        auth()->user()->update(['language_preference' => $locale]);
    }

    return redirect()->back();
})->name('language.switch');

// Landing page
Route::get('/', function () {
    return view('welcome');
});

// Public blog routes
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/load-more', [BlogController::class, 'loadMore'])->name('blog.load-more');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

// Authentication routes
Route::get('/login', function () {
    return view('auth.login');
})->name('login');

Route::get('/register', function () {
    return view('auth.register');
})->name('register');

Route::get('/verify-email', function () {
    return view('auth.verify-email');
})->name('verify-email');

Route::get('/forgot-password', function () {
    return view('auth.forgot-password');
})->name('forgot-password');

Route::get('/reset-password', function () {
    return view('auth.reset-password');
})->name('reset-password');

// Legal pages
Route::get('/privacy-policy', function () {
    return view('privacy-policy');
})->name('privacy-policy');

Route::get('/terms-of-service', function () {
    return view('terms-of-service');
})->name('terms-of-service');

Route::get('/refund-policy', function () {
    return view('refund-policy');
})->name('refund-policy');

// Public QRIS Payment Page
Route::get('/pay/qris/{orderId}', [App\Http\Controllers\QrisPaymentPageController::class, 'show'])
    ->name('qris.payment.page');

// Public Reservation Form Routes
Route::prefix('reservations')->name('reservation.')->group(function () {
    Route::get('/form', [App\Http\Controllers\ReservationFormController::class, 'show'])
        ->name('form');
    Route::post('/form/submit', [App\Http\Controllers\ReservationFormController::class, 'submit'])
        ->name('submit');
    Route::get('/form/status/{orderId}', [App\Http\Controllers\ReservationFormController::class, 'status'])
        ->name('status');
    Route::get('/tables', [App\Http\Controllers\ReservationFormController::class, 'getAvailableTables'])
        ->name('tables');
    Route::get('/products', [App\Http\Controllers\ReservationFormController::class, 'getAvailableProducts'])
        ->name('products');
});

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

    // Reservation routes
    Route::get('/dashboard/reservations', function () {
        return view('dashboard.reservations.index');
    })->name('dashboard.reservations');

    Route::get('/dashboard/reservations/calendar', function () {
        return view('dashboard.reservations.calendar');
    })->name('dashboard.reservations.calendar');

    Route::get('/dashboard/reservations/config', function () {
        return view('dashboard.reservations.config');
    })->name('dashboard.reservations.config');

    // Subscription routes
    Route::prefix('subscription')->name('subscription.')->group(function () {
        Route::get('/pricing', [App\Http\Controllers\SubscriptionController::class, 'index'])
            ->name('pricing');
        Route::post('/checkout', [App\Http\Controllers\SubscriptionController::class, 'createCheckout'])
            ->name('checkout');
        // Card tokenization for Midtrans Subscription API
        Route::get('/tokenization', [App\Http\Controllers\SubscriptionController::class, 'tokenization'])
            ->name('tokenization');
        Route::post('/create-subscription', [App\Http\Controllers\SubscriptionController::class, 'createSubscription'])
            ->name('create-subscription');
        // Legacy payment route (kept for compatibility)
        Route::get('/payment', [App\Http\Controllers\SubscriptionController::class, 'payment'])
            ->name('payment');
        Route::get('/success', [App\Http\Controllers\SubscriptionController::class, 'success'])
            ->name('success');
        Route::get('/cancel', [App\Http\Controllers\SubscriptionController::class, 'cancel'])
            ->name('cancel');
        Route::get('/error', [App\Http\Controllers\SubscriptionController::class, 'error'])
            ->name('error');
        Route::get('/manage', [App\Http\Controllers\SubscriptionController::class, 'manage'])
            ->name('manage');
        Route::post('/cancel', [App\Http\Controllers\SubscriptionController::class, 'cancelSubscription'])
            ->name('cancel.post');
    });

    // Monitoring Dashboard routes (admin only)
    Route::prefix('monitoring')->name('monitoring.')->middleware('can:view-monitoring')->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\MonitoringDashboardController::class, 'index'])
            ->name('dashboard');
        Route::get('/metrics', [App\Http\Controllers\MonitoringDashboardController::class, 'metrics'])
            ->name('metrics');
        Route::get('/health', [App\Http\Controllers\MonitoringDashboardController::class, 'health'])
            ->name('health');
    });

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

        Route::get('/roles', function () {
            return view('dashboard.pos.roles');
        })->name('roles');

        Route::get('/reports', function () {
            return view('dashboard.pos.reports');
        })->name('reports');

        Route::get('/transactions', function () {
            return view('dashboard.pos.transactions');
        })->name('transactions');
    });

    // Admin Routes
    Route::prefix('dashboard/admin')->name('dashboard.admin.')->group(function () {
        // Admin routes can be added here
    });

    // Sub-Merchant Routes
    Route::prefix('dashboard/sub-merchant')->name('dashboard.sub-merchant.')->group(function () {
        Route::get('/', function () {
            return view('dashboard.sub-merchant.index');
        })->name('index');

        Route::get('/register', function () {
            return view('dashboard.sub-merchant.register');
        })->name('register');

        Route::get('/settings', function () {
            return view('dashboard.sub-merchant.settings');
        })->name('settings');

        Route::get('/qris', function () {
            return view('dashboard.sub-merchant.qris');
        })->name('qris');

        Route::get('/balance', function () {
            return view('dashboard.sub-merchant.balance');
        })->name('balance');

        Route::get('/withdrawals', function () {
            return view('dashboard.sub-merchant.withdrawals');
        })->name('withdrawals');

        Route::get('/bank-account', function () {
            return view('dashboard.sub-merchant.bank-account');
        })->name('bank-account');
    });
});
