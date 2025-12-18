<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Models\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableController extends Controller
{
    /**
     * Display a listing of tables.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = auth()->id();

        if (! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $tables = Table::where('user_id', $userId)
            ->with('store')
            ->when($request->input('store_id'), fn ($q, $storeId) => $q->where('store_id', $storeId))
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('store_id')
            ->orderBy('number')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tables,
        ]);
    }

    /**
     * Store a newly created table.
     */
    public function store(Request $request): JsonResponse
    {
        $userId = auth()->id();

        if (! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'number' => 'required|string|max:50',
            'capacity' => 'required|integer|min:1',
            'status' => 'nullable|in:available,occupied,reserved,unavailable',
        ]);

        $validated['status'] = $validated['status'] ?? Table::STATUS_AVAILABLE;
        $validated['user_id'] = $userId;

        $table = Table::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Table created successfully',
            'data' => $table->load('store'),
        ], 201);
    }

    /**
     * Display the specified table.
     */
    public function show(Table $table): JsonResponse
    {
        $userId = auth()->id();

        if (! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        if ((int) $table->user_id !== (int) $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $table->load(['store', 'orders' => fn ($q) => $q->where('status', 'pending')->latest()]),
        ]);
    }

    /**
     * Update the specified table.
     */
    public function update(Request $request, Table $table): JsonResponse
    {
        $userId = auth()->id();

        if (! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        if ($table->user_id !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access',
            ], 403);
        }

        $validated = $request->validate([
            'store_id' => 'sometimes|exists:stores,id',
            'number' => 'sometimes|string|max:50',
            'capacity' => 'sometimes|integer|min:1',
            'status' => 'sometimes|in:available,occupied,reserved,unavailable',
        ]);

        $table->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Table updated successfully',
            'data' => $table->fresh()->load('store'),
        ]);
    }

    /**
     * Remove the specified table.
     */
    public function destroy(Table $table): JsonResponse
    {
        $userId = auth()->id();

        if (! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        if ($table->user_id !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access',
            ], 403);
        }

        // Check if table has active orders
        if ($table->orders()->whereIn('status', ['pending', 'paid'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete table with active orders',
            ], 400);
        }

        $table->delete();

        return response()->json([
            'success' => true,
            'message' => 'Table deleted successfully',
        ]);
    }
}
