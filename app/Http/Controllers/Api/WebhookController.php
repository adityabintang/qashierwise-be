<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    const VALID_EVENTS = [
        'message.incoming',
        'message.status_updated',
        'template.status_changed',
    ];

    const MAX_WEBHOOKS_PER_USER = 10;

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'url' => 'required|url|starts_with:https://',
            'events' => 'required|array|min:1',
            'events.*' => 'in:' . implode(',', self::VALID_EVENTS),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid input',
                ],
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($user->webhooks()->count() >= self::MAX_WEBHOOKS_PER_USER) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'MAX_WEBHOOKS_EXCEEDED',
                    'message' => "Maximum {$this->MAX_WEBHOOKS_PER_USER} webhooks allowed per user",
                ],
            ], 422);
        }

        $webhook = $user->webhooks()->create([
            'url' => $request->input('url'),
            'events' => $request->input('events'),
            'secret' => Str::random(32),
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatWebhook($webhook),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $webhooks = $user->webhooks()->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $webhooks->items(),
            'pagination' => [
                'total' => $webhooks->total(),
                'per_page' => $webhooks->perPage(),
                'current_page' => $webhooks->currentPage(),
                'last_page' => $webhooks->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, Webhook $webhook): JsonResponse
    {
        if ($webhook->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Webhook not found',
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatWebhook($webhook),
        ]);
    }

    public function update(Request $request, Webhook $webhook): JsonResponse
    {
        if ($webhook->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Webhook not found',
                ],
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'url' => 'sometimes|url|starts_with:https://',
            'events' => 'sometimes|array|min:1',
            'events.*' => 'in:' . implode(',', self::VALID_EVENTS),
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid input',
                ],
                'errors' => $validator->errors(),
            ], 422);
        }

        $webhook->update($request->only(['url', 'events', 'is_active']));

        return response()->json([
            'success' => true,
            'data' => $this->formatWebhook($webhook),
        ]);
    }

    public function destroy(Request $request, Webhook $webhook): JsonResponse
    {
        if ($webhook->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Webhook not found',
                ],
            ], 404);
        }

        $webhook->delete();

        return response()->json([
            'success' => true,
            'message' => 'Webhook deleted successfully',
        ]);
    }

    public function deliveries(Request $request, Webhook $webhook): JsonResponse
    {
        if ($webhook->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Webhook not found',
                ],
            ], 404);
        }

        $deliveries = $webhook->deliveries()
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $deliveries->items(),
            'pagination' => [
                'total' => $deliveries->total(),
                'per_page' => $deliveries->perPage(),
                'current_page' => $deliveries->currentPage(),
                'last_page' => $deliveries->lastPage(),
            ],
        ]);
    }

    private function formatWebhook(Webhook $webhook): array
    {
        return [
            'id' => $webhook->id,
            'url' => $webhook->url,
            'events' => $webhook->events,
            'secret' => $webhook->secret,
            'is_active' => $webhook->is_active,
            'created_at' => $webhook->created_at->toIso8601String(),
            'updated_at' => $webhook->updated_at->toIso8601String(),
        ];
    }
}
