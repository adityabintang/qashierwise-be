<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosUser;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    /**
     * Display a listing of orders.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        // Use effective user ID (master admin ID for sub-accounts)
        $effectiveUserId = $user->getEffectiveUserId();

        $perPage = $request->input('per_page', 20);

        // Clean search input - remove # if present
        $search = $request->input('search');
        if ($search) {
            $search = str_replace('#', '', $search);
        }

        $orders = Order::with(['store', 'table', 'posUser.user', 'items.product'])
            ->whereHas('store', fn ($q) => $q->where('user_id', $effectiveUserId))
            ->when($request->input('store_id'), fn ($q, $storeId) => $q->where('store_id', $storeId))
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('delivery_type'), fn ($q, $type) => $q->where('delivery_type', $type))
            ->when($search, fn ($q) => $q->where('order_number', 'like', '%'.$search.'%')
            )
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Store a newly created order.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'table_id' => 'nullable|exists:tables,id',
            'pos_user_id' => 'nullable|exists:pos_users,id',
            'delivery_type' => 'nullable|in:pickup,delivery',
            'alamat' => 'nullable|string',
            'ongkir' => 'nullable|numeric|min:0',
            'catatan' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.product_id' => 'required_with:items|exists:products,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
        ]);

        // If pos_user_id not provided, try to get from authenticated user
        if (empty($validated['pos_user_id'])) {
            $user = $request->user();
            if ($user) {
                $posUser = PosUser::where('user_id', $user->id)
                    ->where('is_active', true)
                    ->first();

                // If no PosUser exists, create one with default Spatie role
                if (! $posUser) {
                    $defaultRole = Role::where('name', 'Cashier')
                        ->where('guard_name', 'sanctum')
                        ->first();

                    if (! $defaultRole) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Default Cashier role not found. Please contact administrator.',
                        ], 500);
                    }

                    $posUser = PosUser::create([
                        'user_id' => $user->id,
                        'store_id' => $validated['store_id'],
                        'role_id' => $defaultRole->id,
                        'is_active' => true,
                    ]);

                    // Assign role to user via Spatie
                    $user->syncRoles([$defaultRole->name]);
                }

                $validated['pos_user_id'] = $posUser->id;
            }
        }

        if (empty($validated['pos_user_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'POS user is required. Please login or provide pos_user_id.',
            ], 400);
        }

        try {
            $order = $this->orderService->create($validated);

            // Add items if provided
            if (! empty($validated['items'])) {
                foreach ($validated['items'] as $itemData) {
                    $product = Product::find($itemData['product_id']);
                    if ($product) {
                        $this->orderService->addItem($order, $product, $itemData['quantity']);
                    }
                }
                $order->refresh();
            }

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $order->load(['store', 'table', 'posUser.user', 'items.product']),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Display the specified order.
     */
    public function show(Order $order, Request $request): JsonResponse
    {
        $effectiveUserId = $request->user()->getEffectiveUserId();

        if ($order->store->user_id !== $effectiveUserId) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to access this resource.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $order->load(['store', 'table', 'posUser.user', 'items.product', 'payments']),
        ]);
    }

    /**
     * Add an item to the order.
     */
    public function addItem(Request $request, Order $order): JsonResponse
    {
        $effectiveUserId = auth()->user()->getEffectiveUserId();

        if ($order->store->user_id !== $effectiveUserId) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to access this resource.',
            ], 403);
        }

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        try {
            $product = Product::findOrFail($validated['product_id']);
            $item = $this->orderService->addItem($order, $product, $validated['quantity']);

            return response()->json([
                'success' => true,
                'message' => 'Item added to order',
                'data' => [
                    'item' => $item->load('product'),
                    'order' => $order->fresh()->load(['items.product']),
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Remove an item from the order.
     */
    public function removeItem(Order $order, OrderItem $item, Request $request): JsonResponse
    {
        $effectiveUserId = $request->user()->getEffectiveUserId();

        if ($order->store->user_id !== $effectiveUserId) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to access this resource.',
            ], 403);
        }

        try {
            $this->orderService->removeItem($order, $item);

            return response()->json([
                'success' => true,
                'message' => 'Item removed from order',
                'data' => $order->fresh()->load(['items.product']),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Apply a discount to the order.
     */
    public function applyDiscount(Request $request, Order $order): JsonResponse
    {
        $effectiveUserId = auth()->user()->getEffectiveUserId();

        if ($order->store->user_id !== $effectiveUserId) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to access this resource.',
            ], 403);
        }

        $validated = $request->validate([
            'discount' => 'required|numeric|min:0',
        ]);

        try {
            $order = $this->orderService->applyDiscount($order, $validated['discount']);

            return response()->json([
                'success' => true,
                'message' => 'Discount applied successfully',
                'data' => $order->load(['items.product']),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Cancel the order.
     */
    public function cancel(Order $order, Request $request): JsonResponse
    {
        $effectiveUserId = $request->user()->getEffectiveUserId();

        if ($order->store->user_id !== $effectiveUserId) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to access this resource.',
            ], 403);
        }

        try {
            $order = $this->orderService->cancel($order);

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully',
                'data' => $order->load(['items.product']),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
