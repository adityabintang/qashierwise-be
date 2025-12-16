<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Models\PosUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosUserController extends Controller
{
    /**
     * Display a listing of POS users.
     */
    public function index(Request $request): JsonResponse
    {
        $posUsers = PosUser::with(['user', 'store', 'role'])
            ->when($request->input('store_id'), fn($q, $storeId) => $q->where('store_id', $storeId))
            ->when($request->boolean('active_only', false), fn($q) => $q->where('is_active', true))
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $posUsers,
        ]);
    }

    /**
     * Store a newly created POS user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'store_id' => 'required|exists:stores,id',
            'role_id' => 'required|exists:roles,id',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;

        // Check if user already has a POS user record for this store
        $existing = PosUser::where('user_id', $validated['user_id'])
            ->where('store_id', $validated['store_id'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'User already assigned to this store',
            ], 400);
        }

        $posUser = PosUser::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'POS user created successfully',
            'data' => $posUser->load(['user', 'store', 'role']),
        ], 201);
    }

    /**
     * Display the specified POS user.
     */
    public function show(PosUser $posUser): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $posUser->load(['user', 'store', 'role']),
        ]);
    }

    /**
     * Update the specified POS user.
     */
    public function update(Request $request, PosUser $posUser): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'sometimes|exists:stores,id',
            'role_id' => 'sometimes|exists:roles,id',
            'is_active' => 'sometimes|boolean',
        ]);

        $posUser->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'POS user updated successfully',
            'data' => $posUser->fresh()->load(['user', 'store', 'role']),
        ]);
    }

    /**
     * Deactivate the specified POS user.
     */
    public function deactivate(PosUser $posUser): JsonResponse
    {
        $posUser->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'POS user deactivated successfully',
            'data' => $posUser->fresh()->load(['user', 'store', 'role']),
        ]);
    }

    /**
     * Activate the specified POS user.
     */
    public function activate(PosUser $posUser): JsonResponse
    {
        $posUser->update(['is_active' => true]);

        return response()->json([
            'success' => true,
            'message' => 'POS user activated successfully',
            'data' => $posUser->fresh()->load(['user', 'store', 'role']),
        ]);
    }
}
