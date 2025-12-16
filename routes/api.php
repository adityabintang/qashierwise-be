<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BroadcastAuthController;
use App\Http\Controllers\Api\PolarWebhookController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\EmbeddedSignupController;
use App\Http\Controllers\Api\WhatsAppController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use App\Http\Controllers\Api\Pos\ProductController;
use App\Http\Controllers\Api\Pos\CategoryController;
use App\Http\Controllers\Api\Pos\OrderController;
use App\Http\Controllers\Api\Pos\PaymentController;
use App\Http\Controllers\Api\Pos\StoreController;
use App\Http\Controllers\Api\Pos\TableController;
use App\Http\Controllers\Api\Pos\PosUserController;
use App\Http\Controllers\Api\Pos\ReportController;
use App\Http\Controllers\Api\Pos\TransactionController;
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

// Polar.sh Webhook (must be public for Polar to access)
Route::post('/webhooks/polar', [PolarWebhookController::class, 'handle']);

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
    });

    // WhatsApp Business API routes
    Route::prefix('whatsapp')->group(function () {
        // Embedded Signup routes
        Route::post('/embedded-signup/callback', [EmbeddedSignupController::class, 'handleCallback']);
        Route::get('/embedded-signup/config', [EmbeddedSignupController::class, 'getConfig']);
        
        // Account management routes
        Route::delete('/account', [EmbeddedSignupController::class, 'disconnect']);
        Route::get('/account', [EmbeddedSignupController::class, 'getAccountStatus']);

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
});
