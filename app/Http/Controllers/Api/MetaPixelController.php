<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CapiEvent;
use App\Models\MetaPixelSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetaPixelController extends Controller
{
    /**
     * GET /api/meta-pixel/settings
     * Returns the authenticated user's Meta pixel configuration.
     * The access_token is masked for security.
     */
    public function show(Request $request): JsonResponse
    {
        $setting = MetaPixelSetting::where('user_id', $request->user()->id)->first();

        if (! $setting) {
            return response()->json(['data' => null], 200);
        }

        return response()->json([
            'data' => [
                'id' => $setting->id,
                'pixel_id' => $setting->pixel_id,
                'access_token_preview' => '••••••'.substr($setting->getRawOriginal('access_token') ?? '', -4),
                'is_active' => $setting->is_active,
                'test_event_code' => $setting->test_event_code,
                'created_at' => $setting->created_at,
                'updated_at' => $setting->updated_at,
            ],
        ]);
    }

    /**
     * POST /api/meta-pixel/settings
     * Create or update the Meta pixel configuration for the authenticated user.
     */
    public function upsert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pixel_id' => 'required|string|max:255',
            'access_token' => 'required|string|max:1000',
            'is_active' => 'boolean',
            'test_event_code' => 'nullable|string|max:50',
        ]);

        $setting = MetaPixelSetting::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'pixel_id' => $validated['pixel_id'],
                'access_token' => $validated['access_token'],
                'is_active' => $validated['is_active'] ?? true,
                'test_event_code' => $validated['test_event_code'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Meta pixel settings saved.',
            'data' => [
                'id' => $setting->id,
                'pixel_id' => $setting->pixel_id,
                'is_active' => $setting->is_active,
                'test_event_code' => $setting->test_event_code,
                'updated_at' => $setting->updated_at,
            ],
        ], 200);
    }

    /**
     * DELETE /api/meta-pixel/settings
     * Remove the Meta pixel configuration for the authenticated user.
     */
    public function destroy(Request $request): JsonResponse
    {
        $deleted = MetaPixelSetting::where('user_id', $request->user()->id)->delete();

        if (! $deleted) {
            return response()->json(['message' => 'No settings found.'], 404);
        }

        return response()->json(['message' => 'Meta pixel settings deleted.'], 200);
    }

    /**
     * GET /api/meta-pixel/events
     * Paginated list of CAPI events sent by the authenticated user.
     */
    public function events(Request $request): JsonResponse
    {
        $query = CapiEvent::where('user_id', $request->user()->id)
            ->with('contact:id,wa_id,name')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('event_name')) {
            $query->where('event_name', $request->event_name);
        }

        if ($request->filled('event_source')) {
            $query->where('event_source', $request->event_source);
        }

        $events = $query->paginate($request->integer('per_page', 20));

        return response()->json($events);
    }
}
