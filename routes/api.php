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
use App\Http\Controllers\Api\PolarWebhookController;
use App\Http\Controllers\Api\Pos\CategoryController;
use App\Http\Controllers\Api\Pos\OrderController;
use App\Http\Controllers\Api\Pos\PaymentController;
use App\Http\Controllers\Api\Pos\PosUserController;
use App\Http\Controllers\Api\Pos\ProductController;
use App\Http\Controllers\Api\Pos\ReportController;
use App\Http\Controllers\Api\Pos\StoreController;
use App\Http\Controllers\Api\Pos\TableController;
use App\Http\Controllers\Api\Pos\TransactionController;
use App\Http\Controllers\Api\ProviderCredentialController;
use App\Http\Controllers\Api\ProviderValidationController;
use App\Http\Controllers\Api\QrisController;
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

// Polar.sh Webhook (must be public for Polar to access)
Route::post('/webhooks/polar', [PolarWebhookController::class, 'handle']);

// Payment Provider Webhooks (must be public for providers to access)
Route::post('/webhooks/midtrans', [MidtransWebhookController::class, 'handleNotification']);
Route::post('/webhooks/doku', [DokuWebhookController::class, 'handleNotification']);
Route::post('/webhooks/xendit', [XenditWebhookController::class, 'handleNotification']);
Route::post('/webhooks/duitku', [DuitkuWebhookController::class, 'handleNotification']);

// Broadcast authentication - Custom controller for Sanctum token auth
Route::post('/broadcasting/auth', [BroadcastAuthController::class, 'authenticate'])
    ->middleware(['auth:sanctum', \App\Http\Middleware\LogBroadcastingAuth::class]);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/user', [AuthController::class, 'me']); // Alias for /me

    // Subscription routes
    Route::prefix('subscription')->group(function () {
        Route::get('/status', [SubscriptionController::class, 'status']);
        Route::post('/checkout', [SubscriptionController::class, 'createCheckout']);
        Route::get('/portal', [SubscriptionController::class, 'getPortalUrl']);
        Route::post('/sync', [SubscriptionController::class, 'syncFromPolar']);
        Route::post('/verify-checkout', [SubscriptionController::class, 'verifyCheckout']);
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
        // Products
        Route::get('/products/search', [ProductController::class, 'search']);
        Route::apiResource('products', ProductController::class);

        // Categories
        Route::apiResource('categories', CategoryController::class);

        // Orders
        Route::post('/orders/{order}/items', [OrderController::class, 'addItem']);
        Route::delete('/orders/{order}/items/{item}', [OrderController::class, 'removeItem']);
        Route::post('/orders/{order}/discount', [OrderController::class, 'applyDiscount']);
        Route::post('/orders/{order}/complete', [OrderController::class, 'complete']);
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
        Route::apiResource('orders', OrderController::class)->except(['update', 'destroy']);

        // Payments
        Route::get('/payments/methods', [PaymentController::class, 'methods']);
        Route::post('/payments/calculate-change', [PaymentController::class, 'calculateChange']);
        Route::post('/payments/split', [PaymentController::class, 'splitPayment']);
        Route::post('/payments', [PaymentController::class, 'store']);

        // Stores
        Route::post('/stores/{store}/deactivate', [StoreController::class, 'deactivate']);
        Route::post('/stores/{store}/activate', [StoreController::class, 'activate']);
        Route::apiResource('stores', StoreController::class)->except(['destroy']);

        // Tables
        Route::apiResource('tables', TableController::class);

        // POS Users
        Route::post('/pos-users/{posUser}/deactivate', [PosUserController::class, 'deactivate']);
        Route::post('/pos-users/{posUser}/activate', [PosUserController::class, 'activate']);
        Route::apiResource('pos-users', PosUserController::class)->except(['destroy']);

        // Reports
        Route::get('/reports/daily', [ReportController::class, 'dailySales']);
        Route::get('/reports/range', [ReportController::class, 'salesByRange']);
        Route::get('/reports/top-products', [ReportController::class, 'topProducts']);
        Route::get('/reports/payment-methods', [ReportController::class, 'salesByPaymentMethod']);
        Route::get('/reports/hourly', [ReportController::class, 'hourlySales']);

        // Transactions
        Route::get('/transactions/search', [TransactionController::class, 'search']);
        Route::get('/transactions/filter-by-date', [TransactionController::class, 'filterByDate']);
        Route::get('/transactions/filter-by-date-range', [TransactionController::class, 'filterByDateRange']);
        Route::get('/transactions', [TransactionController::class, 'index']);
        Route::get('/transactions/{id}', [TransactionController::class, 'show']);
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
