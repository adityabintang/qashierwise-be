<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\StoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function __construct(
        protected StoreService $storeService
    ) {}

    /**
     * Display a listing of stores.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = auth()->id();

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $stores = Store::withCount(['orders', 'tables', 'posUsers'])
            ->where('user_id', $userId)
            ->when($request->boolean('active_only', false), fn($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'data' => $stores,
            ],
        ]);
    }

    /**
     * Store a newly created store.
     */
    public function store(Request $request): JsonResponse
    {
        $userId = auth()->id();

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:stores,code',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['user_id'] = $userId;

        $store = $this->storeService->create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Store created successfully',
            'data' => $store,
        ], 201);
    }

    /**
     * Display the specified store with statistics.
     */
    public function show(Store $store): JsonResponse
    {
        $statistics = $this->storeService->getWithStatistics($store);

        return response()->json([
            'success' => true,
            'data' => $statistics,
        ]);
    }

    /**
     * Update the specified store.
     */
    public function update(Request $request, Store $store): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50|unique:stores,code,' . $store->id,
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'sometimes|boolean',
        ]);

        $store = $this->storeService->update($store, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Store updated successfully',
            'data' => $store,
        ]);
    }

    /**
     * Deactivate the specified store.
     */
    public function deactivate(Store $store): JsonResponse
    {
        $store = $this->storeService->deactivate($store);

        return response()->json([
            'success' => true,
            'message' => 'Store deactivated successfully',
            'data' => $store,
        ]);
    }

    /**
     * Activate the specified store.
     */
    public function activate(Store $store): JsonResponse
    {
        $store = $this->storeService->activate($store);

        return response()->json([
            'success' => true,
            'message' => 'Store activated successfully',
            'data' => $store,
        ]);
    }
}
