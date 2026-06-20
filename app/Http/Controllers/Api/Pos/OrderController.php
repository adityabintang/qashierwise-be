<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosUser;
use App\Models\Product;
use App\Services\DeliveryFulfillmentService;
use App\Services\OrderService;
use App\Services\QrisService;
use App\Services\SubMerchantService;
use App\Services\WhatsAppAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;
use Spatie\Permission\Models\Role;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected QrisService $qrisService,
        protected SubMerchantService $subMerchantService,
        protected WhatsAppAccountService $whatsAppAccountService,
        protected DeliveryFulfillmentService $deliveryFulfillment,
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

        $orders = Order::with(['store', 'table', 'posUser.user', 'items.product', 'deliveryDriver'])
            ->whereHas('store', fn ($q) => $q->where('user_id', $effectiveUserId))
            ->when($request->input('store_id'), fn ($q, $storeId) => $q->where('store_id', $storeId))
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('delivery_type'), fn ($q, $type) => $q->where('delivery_type', $type))
            ->when($request->input('fulfillment_status'), fn ($q, $fs) => $q->where('fulfillment_status', $fs))
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
            'customer_name' => 'nullable|string|max:100',
            'customer_phone' => 'nullable|string|max:20',
            'payment_method' => 'nullable|in:cash,qris',
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

            $qrisTransaction = null;

            // Generate QRIS if payment_method is qris
            $qrisWarning = null;
            if (($validated['payment_method'] ?? 'cash') === 'qris') {
                $user = $request->user();
                $subMerchant = $this->subMerchantService->findByUserId($user->getEffectiveUserId());

                if (! $subMerchant) {
                    $qrisWarning = 'Sub-merchant belum terdaftar. Daftarkan akun QRIS terlebih dahulu di menu Pengaturan QRIS.';
                    Log::warning('QRIS requested but no SubMerchant found', ['user_id' => $user->getEffectiveUserId(), 'order_id' => $order->id]);
                } else {
                    try {
                        $qrisTransaction = $this->qrisService->generateQris(
                            $subMerchant,
                            (float) $order->total,
                            [
                                'description' => 'POS Order #'.$order->order_number,
                                'customer_name' => $validated['customer_name'] ?? null,
                            ]
                        );

                        // Link QRIS transaction to this order
                        $qrisTransaction->update(['linked_order_id' => $order->id]);

                        Log::info('QRIS generated for POS order', ['order_id' => $order->id, 'qris_order_id' => $qrisTransaction->order_id]);

                        // Send WhatsApp message with payment link if customer_phone is set
                        if (! empty($validated['customer_phone'])) {
                            $this->sendQrisPaymentLink(
                                $user->getEffectiveUserId(),
                                $validated['customer_phone'],
                                $order->order_number,
                                $qrisTransaction->getShareableLink()
                            );
                        }
                    } catch (\Exception $e) {
                        $qrisWarning = 'Gagal membuat QRIS: '.$e->getMessage();
                        Log::warning('Failed to generate QRIS for POS order', ['order_id' => $order->id, 'error' => $e->getMessage()]);
                    }
                }
            }

            $responseData = $order->load(['store', 'table', 'posUser.user', 'items.product']);

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $responseData,
                'qris_transaction' => $qrisTransaction ? [
                    'order_id' => $qrisTransaction->order_id,
                    'qr_code_url' => $qrisTransaction->qr_code_url,
                    'shareable_link' => $qrisTransaction->getShareableLink(),
                    'amount' => (float) $qrisTransaction->amount,
                    'status' => $qrisTransaction->status,
                    'expires_at' => $qrisTransaction->expires_at?->toIso8601String(),
                ] : null,
                'qris_warning' => $qrisWarning,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    private function sendQrisPaymentLink(int $userId, string $customerPhone, string $orderNumber, string $paymentLink): void
    {
        try {
            $account = $this->whatsAppAccountService->getActiveAccount($userId);
            if (! $account) {
                return;
            }

            $phone = preg_replace('/[^0-9]/', '', $customerPhone);

            $whatsapp = new WhatsAppCloudApi([
                'from_phone_number_id' => $account->phone_number_id,
                'access_token' => $account->access_token,
            ]);

            $message = "🛍️ *Order #{$orderNumber}*\n\n";
            $message .= "Silakan selesaikan pembayaran QRIS Anda melalui link berikut:\n";
            $message .= $paymentLink."\n\n";
            $message .= "Link berlaku selama 30 menit. Terima kasih! 🙏";

            $whatsapp->sendTextMessage($phone, $message);

            Log::info('QRIS payment link sent via WhatsApp', [
                'order_number' => $orderNumber,
                'customer_phone' => $phone,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to send QRIS payment link via WhatsApp', [
                'order_number' => $orderNumber,
                'error' => $e->getMessage(),
            ]);
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
            'data' => $order->load(['store', 'table', 'posUser.user', 'items.product', 'payments', 'qrisTransaction', 'deliveryDriver']),
        ]);
    }

    /**
     * Confirm an order from the dashboard.
     *
     * Pickup  → notify customer "dikonfirmasi & diproses".
     * Delivery → assign a courier (driver picked from directory OR typed
     *            manually), then fire driver + customer messages.
     */
    public function confirm(Order $order, Request $request): JsonResponse
    {
        $effectiveUserId = $request->user()->getEffectiveUserId();

        if ($order->store->user_id !== $effectiveUserId) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        if ($order->fulfillment_status !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan ini sudah dikonfirmasi sebelumnya.',
            ], 422);
        }

        try {
            if ($order->isPickup()) {
                $this->deliveryFulfillment->confirmPickup($order);

                return response()->json([
                    'success' => true,
                    'message' => 'Pesanan pickup dikonfirmasi. Notifikasi dikirim ke pelanggan.',
                    'data' => $this->reloadOrder($order),
                ]);
            }

            // Delivery: a driver is required (from directory or manual entry).
            $validated = $request->validate([
                'delivery_driver_id' => 'nullable|exists:delivery_drivers,id',
                'courier_name' => 'required_without:delivery_driver_id|string|max:100',
                'courier_phone' => 'required_without:delivery_driver_id|string|max:25',
            ]);

            $courierName = $validated['courier_name'] ?? null;
            $courierPhone = $validated['courier_phone'] ?? null;
            $driverId = $validated['delivery_driver_id'] ?? null;

            // Resolve name/phone from the chosen directory driver when present.
            if ($driverId) {
                $driver = \App\Models\DeliveryDriver::where('id', $driverId)
                    ->where('user_id', $effectiveUserId)
                    ->first();

                if (! $driver) {
                    return response()->json(['success' => false, 'message' => 'Driver tidak ditemukan.'], 404);
                }

                $courierName = $courierName ?: $driver->name;
                $courierPhone = $courierPhone ?: $driver->phone;
            }

            if (! $courierName || ! $courierPhone) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nama dan nomor WhatsApp pengantar wajib diisi.',
                ], 422);
            }

            $this->deliveryFulfillment->assignCourier($order, $courierName, $courierPhone, $driverId);

            return response()->json([
                'success' => true,
                'message' => 'Pesanan dikonfirmasi. Notifikasi dikirim ke kurir dan pelanggan.',
                'data' => $this->reloadOrder($order),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    private function reloadOrder(Order $order): Order
    {
        return $order->fresh(['store', 'table', 'posUser.user', 'items.product', 'payments', 'qrisTransaction', 'deliveryDriver']);
    }

    /**
     * Resend QRIS payment link via WhatsApp.
     */
    public function resendQrisLink(Order $order, Request $request): JsonResponse
    {
        $effectiveUserId = $request->user()->getEffectiveUserId();

        if ($order->store->user_id !== $effectiveUserId) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $qrisTransaction = $order->qrisTransaction;

        if (! $qrisTransaction) {
            return response()->json(['success' => false, 'message' => 'No QRIS transaction found for this order'], 404);
        }

        if (empty($order->customer_phone)) {
            return response()->json(['success' => false, 'message' => 'No customer phone on this order'], 400);
        }

        $this->sendQrisPaymentLink(
            $effectiveUserId,
            $order->customer_phone,
            $order->order_number,
            $qrisTransaction->getShareableLink()
        );

        return response()->json(['success' => true, 'message' => 'Payment link resent via WhatsApp']);
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
