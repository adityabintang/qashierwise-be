<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WhatsAppController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use Illuminate\Support\Facades\Broadcast;
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

// Broadcast authentication - MUST be outside protected group but with auth:sanctum middleware
Broadcast::routes(['middleware' => ['auth:sanctum', \App\Http\Middleware\LogBroadcastingAuth::class]]);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/user', [AuthController::class, 'me']); // Alias for /me

    // WhatsApp Business API routes
    Route::prefix('whatsapp')->group(function () {
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

        // Phone Number Info
        Route::get('/phone-info', [WhatsAppController::class, 'getPhoneNumberInfo']);

        // Business Account Info
        Route::get('/business-account', [WhatsAppController::class, 'getBusinessAccount']);
        Route::get('/business-accounts/all', [WhatsAppController::class, 'getAllBusinessAccounts']);

        // Templates
        Route::get('/templates', [WhatsAppController::class, 'getTemplates']);
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
});
