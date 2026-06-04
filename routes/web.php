<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\DeliveryProofController;
use App\Http\Controllers\MonitoringDashboardController;
use App\Http\Controllers\QrisPaymentPageController;
use App\Http\Controllers\ReservationFormController;
use App\Http\Controllers\SubscriptionController;
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

// Landing page (React island, ported from Next.js)
Route::get('/', function () {
    return view('react.app', [
        'page' => 'landing',
        'title' => 'QashierWise — Chatbot WhatsApp untuk Restoran',
        'description' => 'QashierWise menghadirkan Chatbot WhatsApp berbasis AI untuk restoran — Inbox, Pesanan, Reservasi, Menu, dan CRM dalam satu Console.',
    ]);
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

// Legal pages (React island, ported from Next.js)
Route::get('/privacy-policy', function () {
    return view('react.app', [
        'page' => 'privacy',
        'title' => 'Kebijakan Privasi - QashierWise',
        'description' => 'Kebijakan Privasi QashierWise. Pelajari bagaimana kami mengumpulkan, menggunakan, dan melindungi data Anda serta kepatuhan terhadap regulasi perlindungan data.',
    ]);
})->name('privacy-policy');

Route::get('/terms-of-service', function () {
    return view('react.app', [
        'page' => 'terms',
        'title' => 'Ketentuan Layanan - QashierWise',
        'description' => 'Ketentuan Layanan QashierWise.',
    ]);
})->name('terms-of-service');

Route::get('/refund-policy', function () {
    return view('react.app', [
        'page' => 'refund',
        'title' => 'Kebijakan Pengembalian - QashierWise',
        'description' => 'Kebijakan Pengembalian Dana QashierWise.',
    ]);
})->name('refund-policy');

// Public documentation pages
Route::get('/docs/meta-catalog', function () {
    return view('docs.meta-catalog');
})->name('docs.meta-catalog');

// User-facing documentation (React island; sidebar + markdown from resources/docs).
// Registered AFTER /docs/meta-catalog so that specific route still wins. The React
// app reads the path after /docs to pick which markdown page to render.
Route::get('/docs/{path?}', function () {
    return view('react.app', [
        'page' => 'docs',
        'title' => 'Dokumentasi QashierWise — Panduan Penggunaan',
        'description' => 'Panduan lengkap penggunaan QashierWise: WhatsApp, AI Agent, Katalog, POS Kasir, Reservasi, Delivery, Pembayaran, dan langganan.',
    ]);
})->where('path', '.*')->name('docs');

// Public QRIS Payment Page
Route::get('/pay/qris/{orderId}', [QrisPaymentPageController::class, 'show'])
    ->name('qris.payment.page');

// Public Driver Proof-of-Delivery Page (token-protected, no auth)
Route::get('/delivery/{token}', [DeliveryProofController::class, 'show'])
    ->name('delivery.proof');
Route::post('/delivery/{token}', [DeliveryProofController::class, 'submit'])
    ->name('delivery.proof.submit');

// Public Reservation Form Routes
Route::prefix('reservations')->name('reservation.')->group(function () {
    Route::get('/form', [ReservationFormController::class, 'show'])
        ->name('form');
    Route::post('/form/submit', [ReservationFormController::class, 'submit'])
        ->name('submit');
    Route::get('/form/status/{orderId}', [ReservationFormController::class, 'status'])
        ->name('status');
    Route::get('/tables', [ReservationFormController::class, 'getAvailableTables'])
        ->name('tables');
    Route::get('/products', [ReservationFormController::class, 'getAvailableProducts'])
        ->name('products');
});

// Dashboard routes (protected by authentication middleware)
Route::middleware(['web', 'check.web.auth', 'block.author.login'])->group(function () {
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

    Route::get('/dashboard/meta-catalog', function () {
        return view('dashboard.meta-catalog');
    })->name('dashboard.meta-catalog');

    Route::get('/dashboard/meta-catalog/{catalogId}', function ($catalogId) {
        return view('dashboard.meta-catalog', ['initialCatalogId' => $catalogId]);
    })->name('dashboard.meta-catalog.catalog');

    Route::get('/dashboard/ai-agent', function () {
        return view('dashboard.ai-agent');
    })->name('dashboard.ai-agent');

    Route::get('/dashboard/customer-tags', function () {
        return view('dashboard.customer-tags');
    })->name('dashboard.customer-tags');

    Route::get('/dashboard/delivery', function () {
        return view('dashboard.delivery');
    })->name('dashboard.delivery');

    Route::get('/dashboard/complain', function () {
        return view('dashboard.complain');
    })->name('dashboard.complain');

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

    // Developer Webhook UI
    Route::get('/dashboard/developer/webhooks', function () {
        return view('dashboard.developer-webhooks');
    })->name('dashboard.developer-webhooks');

    // Subscription routes
    Route::prefix('subscription')->name('subscription.')->group(function () {
        Route::get('/pricing', [SubscriptionController::class, 'index'])
            ->name('pricing');
        Route::post('/checkout', [SubscriptionController::class, 'createCheckout'])
            ->name('checkout');
        // Card tokenization for Midtrans Subscription API
        Route::get('/tokenization', [SubscriptionController::class, 'tokenization'])
            ->name('tokenization');
        Route::post('/create-subscription', [SubscriptionController::class, 'createSubscription'])
            ->name('create-subscription');
        // Legacy payment route (kept for compatibility)
        Route::get('/payment', [SubscriptionController::class, 'payment'])
            ->name('payment');
        Route::get('/success', [SubscriptionController::class, 'success'])
            ->name('success');
        Route::get('/cancel', [SubscriptionController::class, 'cancel'])
            ->name('cancel');
        Route::get('/error', [SubscriptionController::class, 'error'])
            ->name('error');
        Route::get('/manage', [SubscriptionController::class, 'manage'])
            ->name('manage');
        Route::post('/cancel', [SubscriptionController::class, 'cancelSubscription'])
            ->name('cancel.post');
    });

    // Monitoring Dashboard routes (admin only)
    Route::prefix('monitoring')->name('monitoring.')->middleware('can:view-monitoring')->group(function () {
        Route::get('/dashboard', [MonitoringDashboardController::class, 'index'])
            ->name('dashboard');
        Route::get('/metrics', [MonitoringDashboardController::class, 'metrics'])
            ->name('metrics');
        Route::get('/health', [MonitoringDashboardController::class, 'health'])
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
