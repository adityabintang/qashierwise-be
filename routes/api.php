<?php

use App\Http\Controllers\AiAgentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BalanceController;
use App\Http\Controllers\Api\BroadcastAuthController;
use App\Http\Controllers\Api\DokuWebhookController;
use App\Http\Controllers\Api\DuitkuWebhookController;
use App\Http\Controllers\Api\EmbeddedSignupController;
use App\Http\Controllers\Api\MidtransWebhookController;
use App\Http\Controllers\Api\MigrationController;
use App\Http\Controllers\Api\Pos\CategoryController;
use App\Http\Controllers\Api\Pos\OrderController;
use App\Http\Controllers\Api\Pos\PaymentController;
use App\Http\Controllers\Api\Pos\PosUserController;
use App\Http\Controllers\Api\Pos\ProductController;
use App\Http\Controllers\Api\Pos\ReportController;
use App\Http\Controllers\Api\Pos\RoleController;
use App\Http\Controllers\Api\Pos\StoreController;
use App\Http\Controllers\Api\Pos\TableController;
use App\Http\Controllers\Api\Pos\TransactionController;
use App\Http\Controllers\Api\ProviderCredentialController;
use App\Http\Controllers\Api\ProviderValidationController;
use App\Http\Controllers\Api\QrisController;
use App\Http\Controllers\Api\ResendWebhookController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\SubMerchantController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\WhatsAppController;
use App\Http\Controllers\Api\WhatsAppFlowEndpointController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use App\Http\Controllers\Api\XenditWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/send-otp', [AuthController::class, 'sendOtp'])
    ->middleware('throttle:3,1');
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/resend-otp', [AuthController::class, 'resendOtp'])
    ->middleware('throttle:3,1');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// WhatsApp Webhook (must be public for WhatsApp to access)
Route::get('/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify']);
Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle']);

// WhatsApp Flow Data Endpoint (must be public for WhatsApp to access)
// This endpoint receives encrypted requests from WhatsApp Flow and returns encrypted responses
Route::post('/whatsapp/flow/endpoint', [WhatsAppFlowEndpointController::class, 'handleRequest']);

// WhatsApp Flow Public Key Endpoint
// Meta will fetch this endpoint to get the public key for signing
// See: https://developers.facebook.com/docs/whatsapp/flows/guides/implementingyourflowendpoint#upload-public-key
Route::get('/whatsapp/flow/public-key', [WhatsAppFlowEndpointController::class, 'getPublicKey']);

// Payment Provider Webhooks (must be public for providers to access)
Route::post('/webhooks/midtrans', [MidtransWebhookController::class, 'handleNotification'])
    ->middleware('throttle:60,1'); // Rate limit: 60 requests per minute
Route::post('/webhooks/doku', [DokuWebhookController::class, 'handleNotification']);
Route::post('/webhooks/xendit', [XenditWebhookController::class, 'handleNotification']);
Route::post('/webhooks/duitku', [DuitkuWebhookController::class, 'handleNotification']);

// Resend Email Webhook (must be public for Resend to access)
Route::post('/webhooks/resend', [ResendWebhookController::class, 'handleNotification'])
    ->middleware('throttle:60,1'); // Rate limit: 60 requests per minute

// Subscription Webhook (must be public for Midtrans to access)
Route::post('/webhooks/midtrans/subscription', [MidtransWebhookController::class, 'handleSubscriptionWebhook'])
    ->middleware('throttle:60,1'); // Rate limit: 60 requests per minute

// Health Check Endpoint (public for monitoring services)
Route::get('/health/subscription', [\App\Http\Controllers\MonitoringDashboardController::class, 'status']);

// Broadcast authentication - Custom controller for Sanctum token auth
Route::post('/broadcasting/auth', [BroadcastAuthController::class, 'authenticate'])
    ->middleware(['auth:sanctum', \App\Http\Middleware\LogBroadcastingAuth::class]);

// Protected routes
Route::middleware(['auth:sanctum', 'clear.permission.cache'])->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/user', [AuthController::class, 'me']); // Alias for /me
    Route::get('/user/permissions', [AuthController::class, 'getUserPermissions']);
    Route::post('/user/check-permission', [AuthController::class, 'checkPermission']);

    // DEBUG: Direct permission test
    Route::get('/user/permissions/debug', function(\Illuminate\Http\Request $request) {
        $user = $request->user();
        $user->load('roles.permissions');

        $perms = $user->getAllPermissions()->pluck('name')->toArray();
        $roles = $user->getRoleNames()->toArray();

        return response()->json([
            'DEBUG' => true,
            'user_id' => $user->id,
            'email' => $user->email,
            'permissions_count' => count($perms),
            'permissions' => $perms,
            'roles' => $roles,
            'guard' => $user->guardName(),
        ]);
    });

    // Subscription routes
    Route::prefix('subscription')->group(function () {
        Route::get('/status', [SubscriptionController::class, 'status']);
        Route::get('/billing-history', [SubscriptionController::class, 'billingHistory']);
        Route::post('/checkout', [SubscriptionController::class, 'createCheckout']);
        Route::post('/cancel', [SubscriptionController::class, 'cancelSubscription']);
    });

    // Promo Code routes
    Route::prefix('promo-codes')->group(function () {
        Route::post('/validate', [\App\Http\Controllers\Api\PromoCodeController::class, 'validate']);
    });

    // WhatsApp Business API routes
    Route::prefix('whatsapp')->group(function () {
        // Embedded Signup routes
        Route::post('/embedded-signup/callback', [EmbeddedSignupController::class, 'handleCallback']);
        Route::get('/embedded-signup/config', [EmbeddedSignupController::class, 'getConfig']);

        // Account management routes
        Route::delete('/account', [EmbeddedSignupController::class, 'disconnect']);
        Route::get('/account', [EmbeddedSignupController::class, 'getAccountStatus']);

        // Webhook subscription management
        Route::post('/subscribe-webhooks', [EmbeddedSignupController::class, 'subscribeToWebhooks']);
        Route::get('/webhook-status', [EmbeddedSignupController::class, 'getWebhookStatus']);

        // Send Messages
        Route::post('/send/text', [WhatsAppController::class, 'sendTextMessage']);
        Route::post('/send/template', [WhatsAppController::class, 'sendTemplateMessage']);
        Route::post('/send/image', [WhatsAppController::class, 'sendImageMessage']);
        Route::post('/send/document', [WhatsAppController::class, 'sendDocumentMessage']);
        Route::post('/send/audio', [WhatsAppController::class, 'sendAudioMessage']);
        Route::post('/send/video', [WhatsAppController::class, 'sendVideoMessage']);
        Route::post('/send/location', [WhatsAppController::class, 'sendLocationMessage']);
        Route::post('/send/contact', [WhatsAppController::class, 'sendContactMessage']);
        Route::post('/send/button', [WhatsAppController::class, 'sendButtonMessage']);
        Route::post('/send/list', [WhatsAppController::class, 'sendListMessage']);

        // Message Actions
        Route::post('/message/read', [WhatsAppController::class, 'markAsRead']);

        // Media Management
        Route::get('/media/{media_id}', [WhatsAppController::class, 'getMediaUrl']);
        Route::post('/media/upload', [WhatsAppController::class, 'uploadMedia']);

        // Business Profile
        Route::get('/profile', [WhatsAppController::class, 'getBusinessProfile']);
        Route::match(['put', 'post'], '/profile', [WhatsAppController::class, 'updateBusinessProfile']);
        Route::post('/profile/picture', [WhatsAppController::class, 'uploadProfilePicture']);

        // Phone Number Info
        Route::get('/phone-info', [WhatsAppController::class, 'getPhoneNumberInfo']);

        // Business Account Info
        Route::get('/business-account', [WhatsAppController::class, 'getBusinessAccount']);
        Route::get('/business-accounts/all', [WhatsAppController::class, 'getAllBusinessAccounts']);

        // Templates
        Route::get('/templates', [WhatsAppController::class, 'getTemplates']);
        Route::post('/templates', [WhatsAppController::class, 'createTemplate']);
        Route::put('/templates/{id}', [WhatsAppController::class, 'updateTemplate']);
        Route::delete('/templates/{name}', [WhatsAppController::class, 'deleteTemplate']);
        Route::get('/templates/{name}', [WhatsAppController::class, 'getTemplateByName']);

        // Dashboard Stats
        Route::get('/stats', [WhatsAppController::class, 'getDashboardStats']);
        Route::get('/stats/weekly-chart', [WhatsAppController::class, 'getWeeklyChartData']);

        // Message History
        Route::get('/messages', [WhatsAppController::class, 'getMessages']);
        Route::get('/messages/{id}', [WhatsAppController::class, 'getMessage']);
        Route::get('/contacts', [WhatsAppController::class, 'getContacts']);
        Route::get('/contacts/{id}/messages', [WhatsAppController::class, 'getContactMessages']);
        Route::post('/contacts/{id}/mark-read', [WhatsAppController::class, 'markContactMessagesAsRead']);
    });

    // AI Agent routes
    Route::prefix('ai-agent')->group(function () {
        Route::get('/', [AiAgentController::class, 'show']);
        Route::post('/', [AiAgentController::class, 'store']);
        Route::put('/toggle-active', [AiAgentController::class, 'toggleActive']);
        Route::put('/toggle-order', [AiAgentController::class, 'toggleOrder']);
        Route::put('/toggle-qris', [AiAgentController::class, 'toggleQris']);
        Route::post('/test', [AiAgentController::class, 'test']);
        Route::delete('/conversations/test', [AiAgentController::class, 'clearTestConversation']);
        Route::delete('/conversations/{contactId}', [AiAgentController::class, 'clearConversation']);
    });

    // Reservation routes
    Route::prefix('reservations')->group(function () {
        // CRUD operations (non-parameterized first)
        Route::get('/', [ReservationController::class, 'index']);
        Route::post('/', [ReservationController::class, 'store']);
        Route::get('/statistics', [ReservationController::class, 'statistics']);

        // WhatsApp Flow management (legacy)
        Route::get('/flows/list', [ReservationController::class, 'listFlows']);
        Route::post('/flows/create', [ReservationController::class, 'createFlow']);
        Route::post('/flows/send', [ReservationController::class, 'sendFlow']);
        Route::post('/flows/publish', [ReservationController::class, 'publishFlow']);
        Route::delete('/flows/delete', [ReservationController::class, 'deleteFlow']);

        // Flow Configuration (new dashboard feature) - MUST be before {reservation} wildcard
        Route::prefix('flow-config')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\ReservationFlowConfigController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\ReservationFlowConfigController::class, 'update']);
            Route::get('/preview', [\App\Http\Controllers\Api\ReservationFlowConfigController::class, 'preview']);
            Route::post('/publish', [\App\Http\Controllers\Api\ReservationFlowConfigController::class, 'publish']);
            Route::post('/sync', [\App\Http\Controllers\Api\ReservationFlowConfigController::class, 'sync']);
            Route::post('/send', [\App\Http\Controllers\Api\ReservationFlowConfigController::class, 'send']);
            Route::post('/regenerate', [\App\Http\Controllers\Api\ReservationFlowConfigController::class, 'regenerateFlow']);
            Route::delete('/', [\App\Http\Controllers\Api\ReservationFlowConfigController::class, 'destroy']);
        });

        // Parameterized routes MUST come last
        Route::get('/{reservation}', [ReservationController::class, 'show']);
        Route::put('/{reservation}', [ReservationController::class, 'update']);
        Route::delete('/{reservation}', [ReservationController::class, 'destroy']);

        // Status actions
        Route::post('/{reservation}/confirm', [ReservationController::class, 'confirm']);
        Route::post('/{reservation}/cancel', [ReservationController::class, 'cancel']);
        Route::post('/{reservation}/complete', [ReservationController::class, 'complete']);
        Route::post('/{reservation}/no-show', [ReservationController::class, 'noShow']);
    });

    // POS (Point of Sale) API routes
    Route::prefix('pos')->group(function () {
        // Products - index/show accepts view_products OR manage_products
        Route::get('/products/search', [ProductController::class, 'search'])->middleware('pos.permission:view_products|manage_products');
        Route::get('/products', [ProductController::class, 'index'])->middleware('pos.permission:view_products|manage_products');
        Route::post('/products', [ProductController::class, 'store'])->middleware('pos.permission:manage_products');
        Route::get('/products/{product}', [ProductController::class, 'show'])->middleware('pos.permission:view_products|manage_products');
        Route::put('/products/{product}', [ProductController::class, 'update'])->middleware('pos.permission:manage_products');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('pos.permission:manage_products');

        // Categories - index/show accepts view_categories OR manage_categories
        Route::get('/categories', [CategoryController::class, 'index'])->middleware('pos.permission:view_categories|manage_categories');
        Route::post('/categories', [CategoryController::class, 'store'])->middleware('pos.permission:manage_categories');
        Route::get('/categories/{category}', [CategoryController::class, 'show'])->middleware('pos.permission:view_categories|manage_categories');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->middleware('pos.permission:manage_categories');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware('pos.permission:manage_categories');

        // Orders
        Route::post('/orders/{order}/items', [OrderController::class, 'addItem'])->middleware('pos.permission:manage_orders');
        Route::delete('/orders/{order}/items/{item}', [OrderController::class, 'removeItem'])->middleware('pos.permission:manage_orders');
        Route::post('/orders/{order}/discount', [OrderController::class, 'applyDiscount'])->middleware('pos.permission:manage_orders');
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->middleware('pos.permission:manage_orders');
        Route::get('/orders', [OrderController::class, 'index'])->middleware('pos.permission:view_orders|manage_orders');
        Route::post('/orders', [OrderController::class, 'store'])->middleware('pos.permission:manage_orders');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->middleware('pos.permission:view_orders|manage_orders');

        // Payments
        Route::get('/payments/methods', [PaymentController::class, 'methods'])->middleware('pos.permission:process_payment');
        Route::post('/payments/calculate-change', [PaymentController::class, 'calculateChange'])->middleware('pos.permission:process_payment');
        Route::post('/payments/split', [PaymentController::class, 'splitPayment'])->middleware('pos.permission:process_payment');
        Route::post('/payments', [PaymentController::class, 'store'])->middleware('pos.permission:process_payment');

        // Stores
        Route::post('/stores/{store}/deactivate', [StoreController::class, 'deactivate'])->middleware('pos.permission:manage_stores');
        Route::post('/stores/{store}/activate', [StoreController::class, 'activate'])->middleware('pos.permission:manage_stores');
        Route::get('/stores', [StoreController::class, 'index'])->middleware('pos.permission:view_stores|manage_stores');
        Route::post('/stores', [StoreController::class, 'store'])->middleware('pos.permission:manage_stores');
        Route::get('/stores/{store}', [StoreController::class, 'show'])->middleware('pos.permission:view_stores|manage_stores');
        Route::put('/stores/{store}', [StoreController::class, 'update'])->middleware('pos.permission:manage_stores');

        // Tables
        Route::get('/tables', [TableController::class, 'index'])->middleware('pos.permission:view_tables|manage_tables');
        Route::post('/tables', [TableController::class, 'store'])->middleware('pos.permission:manage_tables');
        Route::get('/tables/{table}', [TableController::class, 'show'])->middleware('pos.permission:view_tables|manage_tables');
        Route::put('/tables/{table}', [TableController::class, 'update'])->middleware('pos.permission:manage_tables');
        Route::delete('/tables/{table}', [TableController::class, 'destroy'])->middleware('pos.permission:manage_tables');

        // POS Users
        Route::post('/users/create-user', [PosUserController::class, 'createUser'])->middleware('pos.permission:manage_users');
        Route::post('/users/send-otp', [PosUserController::class, 'sendOtp'])->middleware('pos.permission:manage_users');
        Route::post('/users/verify-otp', [PosUserController::class, 'verifyOtp'])->middleware('pos.permission:manage_users');
        Route::get('/users/available', [PosUserController::class, 'getAvailableUsers'])->middleware('pos.permission:view_users|manage_users');
        Route::get('/users', [PosUserController::class, 'index'])->middleware('pos.permission:view_users|manage_users');
        Route::post('/users', [PosUserController::class, 'store'])->middleware('pos.permission:manage_users');
        Route::put('/users/{posUser}', [PosUserController::class, 'update'])->middleware('pos.permission:manage_users');
        Route::delete('/users/{posUser}', [PosUserController::class, 'destroy'])->middleware('pos.permission:manage_users');
        Route::post('/pos-users/{posUser}/deactivate', [PosUserController::class, 'deactivate'])->middleware('pos.permission:manage_users');
        Route::post('/pos-users/{posUser}/activate', [PosUserController::class, 'activate'])->middleware('pos.permission:manage_users');
        Route::get('/pos-users', [PosUserController::class, 'index'])->middleware('pos.permission:view_users|manage_users');
        Route::post('/pos-users', [PosUserController::class, 'store'])->middleware('pos.permission:manage_users');
        Route::get('/pos-users/{pos_user}', [PosUserController::class, 'show'])->middleware('pos.permission:view_users|manage_users');
        Route::put('/pos-users/{pos_user}', [PosUserController::class, 'update'])->middleware('pos.permission:manage_users');

        // Roles
        Route::get('/roles/permissions', [RoleController::class, 'permissions'])->middleware('pos.permission:view_roles|manage_roles');
        Route::get('/roles', [RoleController::class, 'index'])->middleware('pos.permission:view_roles|manage_roles');
        Route::post('/roles', [RoleController::class, 'store'])->middleware('pos.permission:manage_roles');
        Route::get('/roles/{role}', [RoleController::class, 'show'])->middleware('pos.permission:view_roles|manage_roles');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('pos.permission:manage_roles');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('pos.permission:manage_roles');

        // Reports
        Route::get('/reports/daily', [ReportController::class, 'dailySales'])->middleware('pos.permission:view_reports|manage_reports');
        Route::get('/reports/range', [ReportController::class, 'salesByRange'])->middleware('pos.permission:view_reports|manage_reports');
        Route::get('/reports/top-products', [ReportController::class, 'topProducts'])->middleware('pos.permission:view_reports|manage_reports');
        Route::get('/reports/payment-methods', [ReportController::class, 'salesByPaymentMethod'])->middleware('pos.permission:view_reports|manage_reports');
        Route::get('/reports/hourly', [ReportController::class, 'hourlySales'])->middleware('pos.permission:view_reports|manage_reports');

        // Transactions
        Route::get('/transactions/search', [TransactionController::class, 'search'])->middleware('pos.permission:view_transactions|manage_transactions');
        Route::get('/transactions/filter-by-date', [TransactionController::class, 'filterByDate'])->middleware('pos.permission:view_transactions|manage_transactions');
        Route::get('/transactions/filter-by-date-range', [TransactionController::class, 'filterByDateRange'])->middleware('pos.permission:view_transactions|manage_transactions');
        Route::get('/transactions', [TransactionController::class, 'index'])->middleware('pos.permission:view_transactions|manage_transactions');
        Route::get('/transactions/{id}', [TransactionController::class, 'show'])->middleware('pos.permission:view_transactions|manage_transactions');
    });

    // Sub-Merchant QRIS routes
    Route::prefix('sub-merchant')->group(function () {
        // Sub-Merchant registration and management
        Route::get('/status', [SubMerchantController::class, 'status']);
        Route::post('/register', [SubMerchantController::class, 'register']);
        Route::get('/profile', [SubMerchantController::class, 'profile']);
        Route::post('/deactivate', [SubMerchantController::class, 'deactivate']);
        Route::post('/activate', [SubMerchantController::class, 'activate']);

        // Migration to BYOK
        Route::prefix('migration')->group(function () {
            Route::get('/status', [MigrationController::class, 'checkMigrationStatus']);
            Route::post('/migrate', [MigrationController::class, 'migrate']);
            Route::post('/skip', [MigrationController::class, 'skipMigration']);
        });

        // Payment Provider Credential Management
        Route::prefix('providers')->middleware('sanitize.provider.errors')->group(function () {
            Route::get('/', [ProviderCredentialController::class, 'index']);
            Route::post('/', [ProviderCredentialController::class, 'store']);
            Route::put('/{id}', [ProviderCredentialController::class, 'update']);
            Route::delete('/{id}', [ProviderCredentialController::class, 'destroy']);
            Route::post('/set-active', [ProviderCredentialController::class, 'setActive']);

            // Provider Validation
            Route::post('/{id}/validate', [ProviderValidationController::class, 'validate']);
            Route::post('/{id}/revalidate', [ProviderValidationController::class, 'revalidate']);
            Route::get('/{id}/status', [ProviderValidationController::class, 'status']);
            Route::get('/status-all', [ProviderValidationController::class, 'statusAll']);
        });

        // QRIS generation and management
        Route::prefix('qris')->middleware('sanitize.provider.errors')->group(function () {
            Route::post('/generate', [QrisController::class, 'generate'])->middleware('qris.rate_limit');
            Route::get('/history', [QrisController::class, 'history']);
            Route::get('/pending', [QrisController::class, 'pending']);
            Route::get('/{orderId}', [QrisController::class, 'show']);
            Route::get('/{orderId}/qr-code', [QrisController::class, 'getQrCode']);
            Route::get('/{orderId}/share-link', [QrisController::class, 'getShareableLink']);
            Route::get('/{orderId}/status', [QrisController::class, 'checkStatus']);
            Route::post('/{orderId}/cancel', [QrisController::class, 'cancel']);
        });

        // Balance management
        Route::prefix('balance')->group(function () {
            Route::get('/', [BalanceController::class, 'current']);
            Route::get('/breakdown', [BalanceController::class, 'breakdown']);
            Route::get('/transactions', [BalanceController::class, 'transactions']);
            Route::get('/earnings', [BalanceController::class, 'earnings']);
            Route::get('/daily-earnings', [BalanceController::class, 'dailyEarnings']);
            Route::post('/calculate-fee', [BalanceController::class, 'calculateFee']);
        });
    });
});
