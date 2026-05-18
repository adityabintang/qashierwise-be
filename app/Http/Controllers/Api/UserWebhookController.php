<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Jobs\DeliverWebhook;
use App\Models\UserWebhook;
use App\Models\WebhookDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserWebhookController extends Controller
{
    private const SUPPORTED_EVENTS = ['whatsapp.message.received'];

    public function index(Request $request): JsonResponse
    {
        $webhooks = UserWebhook::where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return ApiResponse::success($webhooks);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'url' => 'required|url|max:500',
            'events' => 'required|array|min:1',
            'events.*' => 'string|in:'.implode(',', self::SUPPORTED_EVENTS),
        ]);

        $webhook = UserWebhook::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'url' => $validated['url'],
            'secret' => Str::random(32),
            'events' => array_unique($validated['events']),
            'is_active' => true,
        ]);

        return ApiResponse::success(
            array_merge($webhook->toArray(), ['secret' => $webhook->secret]),
            null,
            201
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $webhook = $this->findWebhook($request->user()->id, $id);

        if (! $webhook) {
            return ApiResponse::notFound('Webhook not found');
        }

        return ApiResponse::success($webhook);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $webhook = $this->findWebhook($request->user()->id, $id);

        if (! $webhook) {
            return ApiResponse::notFound('Webhook not found');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'url' => 'sometimes|url|max:500',
            'events' => 'sometimes|array|min:1',
            'events.*' => 'string|in:'.implode(',', self::SUPPORTED_EVENTS),
        ]);

        if (isset($validated['events'])) {
            $validated['events'] = array_unique($validated['events']);
        }

        $webhook->update($validated);

        return ApiResponse::success($webhook->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $webhook = $this->findWebhook($request->user()->id, $id);

        if (! $webhook) {
            return ApiResponse::notFound('Webhook not found');
        }

        $webhook->delete();

        return ApiResponse::success(null, 'Webhook deleted');
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        $webhook = $this->findWebhook($request->user()->id, $id);

        if (! $webhook) {
            return ApiResponse::notFound('Webhook not found');
        }

        $webhook->update(['is_active' => ! $webhook->is_active]);

        return ApiResponse::success($webhook->fresh());
    }

    public function regenerateSecret(Request $request, int $id): JsonResponse
    {
        $webhook = $this->findWebhook($request->user()->id, $id);

        if (! $webhook) {
            return ApiResponse::notFound('Webhook not found');
        }

        $newSecret = Str::random(32);
        $webhook->update(['secret' => $newSecret]);

        return ApiResponse::success(['secret' => $newSecret]);
    }

    public function deliveries(Request $request, int $id): JsonResponse
    {
        $webhook = $this->findWebhook($request->user()->id, $id);

        if (! $webhook) {
            return ApiResponse::notFound('Webhook not found');
        }

        $deliveries = WebhookDelivery::where('user_webhook_id', $id)
            ->latest()
            ->paginate(20);

        return ApiResponse::success($deliveries);
    }

    public function test(Request $request, int $id): JsonResponse
    {
        $webhook = $this->findWebhook($request->user()->id, $id);

        if (! $webhook) {
            return ApiResponse::notFound('Webhook not found');
        }

        $testPayload = [
            'event' => 'whatsapp.message.received',
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'message_id' => 'test_'.Str::random(10),
                'from' => '6281234567890',
                'contact_name' => 'Test User',
                'type' => 'text',
                'content' => 'This is a test webhook payload from QashierWise.',
                'phone_number_id' => 'test_phone_id',
                'received_at' => now()->toIso8601String(),
            ],
        ];

        DeliverWebhook::dispatch($webhook, 'whatsapp.message.received', $testPayload)
            ->onQueue('webhooks');

        return ApiResponse::success(null, 'Test webhook queued for delivery');
    }

    private function findWebhook(int $userId, int $id): ?UserWebhook
    {
        return UserWebhook::where('user_id', $userId)->find($id);
    }
}
