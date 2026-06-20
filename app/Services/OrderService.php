<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderService
{
    /**
     * Default tax rate (11% for Indonesia PPN)
     */
    const DEFAULT_TAX_RATE = 0.11;

    /**
     * Create a new order with auto-generated order number
     *
     * @param  array  $data  Order data (store_id, table_id, pos_user_id)
     *
     * @throws InvalidArgumentException If store is inactive
     */
    public function create(array $data): Order
    {
        // Validate store is active
        $store = Store::find($data['store_id']);
        if (! $store || ! $store->is_active) {
            throw new InvalidArgumentException('Cannot create order at inactive store');
        }

        // Generate unique order number
        $data['order_number'] = $this->generateOrderNumber($data['store_id']);
        $data['status'] = Order::STATUS_PENDING;
        $data['subtotal'] = 0;
        $data['tax_amount'] = 0;
        $data['discount_amount'] = 0;
        $data['total'] = 0;
        $data['ongkir'] = $data['ongkir'] ?? 0;

        $order = Order::create($data);

        // Update table status if table is assigned
        if (! empty($data['table_id'])) {
            $this->updateTableStatus($data['table_id'], Table::STATUS_OCCUPIED);
        }

        return $order;
    }

    /**
     * Add an item to an order
     *
     * @param  Order  $order  Order to add item to
     * @param  Product  $product  Product to add
     * @param  int  $quantity  Quantity to add
     *
     * @throws InvalidArgumentException If order is not pending or insufficient stock
     */
    public function addItem(Order $order, Product $product, int $quantity): OrderItem
    {
        if ($order->status !== Order::STATUS_PENDING) {
            throw new InvalidArgumentException('Cannot add items to a non-pending order');
        }

        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be positive');
        }

        if ($product->stock_quantity < $quantity) {
            throw new InvalidArgumentException("Insufficient stock for product: {$product->name}");
        }

        // Check if item already exists in order
        $existingItem = $order->items()->where('product_id', $product->id)->first();

        if ($existingItem) {
            // Update existing item
            $newQuantity = $existingItem->quantity + $quantity;
            if ($product->stock_quantity < $newQuantity) {
                throw new InvalidArgumentException("Insufficient stock for product: {$product->name}");
            }
            $existingItem->quantity = $newQuantity;
            $existingItem->subtotal = $newQuantity * $existingItem->unit_price;
            $existingItem->save();
            $item = $existingItem->fresh();
        } else {
            // Create new item
            $item = OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $product->price,
                'subtotal' => $quantity * $product->price,
            ]);
        }

        // Recalculate order totals
        $this->calculateTotals($order);

        return $item;
    }

    /**
     * Remove an item from an order
     *
     * @param  Order  $order  Order to remove item from
     * @param  OrderItem  $item  Item to remove
     *
     * @throws InvalidArgumentException If order is not pending
     */
    public function removeItem(Order $order, OrderItem $item): void
    {
        if ($order->status !== Order::STATUS_PENDING) {
            throw new InvalidArgumentException('Cannot remove items from a non-pending order');
        }

        if ($item->order_id !== $order->id) {
            throw new InvalidArgumentException('Item does not belong to this order');
        }

        $item->delete();

        // Recalculate order totals
        $this->calculateTotals($order);
    }

    /**
     * Calculate and update order totals
     *
     * @param  Order  $order  Order to calculate totals for
     * @return array Array with subtotal, tax_amount, discount_amount, total
     */
    public function calculateTotals(Order $order): array
    {
        $order->refresh();

        // Calculate subtotal from items
        $subtotal = $order->items->sum('subtotal');

        // Calculate tax on subtotal minus discount
        $taxableAmount = max(0, $subtotal - $order->discount_amount);
        $taxAmount = round($taxableAmount * self::DEFAULT_TAX_RATE, 2);

        // Calculate total
        $total = $subtotal + $taxAmount - $order->discount_amount + $order->ongkir;

        // Update order
        $order->subtotal = $subtotal;
        $order->tax_amount = $taxAmount;
        $order->total = max(0, $total);
        $order->save();

        return [
            'subtotal' => (float) $order->subtotal,
            'tax_amount' => (float) $order->tax_amount,
            'discount_amount' => (float) $order->discount_amount,
            'total' => (float) $order->total,
        ];
    }

    /**
     * Apply a discount to an order
     *
     * @param  Order  $order  Order to apply discount to
     * @param  float  $discount  Discount amount
     *
     * @throws InvalidArgumentException If order is not pending or discount is invalid
     */
    public function applyDiscount(Order $order, float $discount): Order
    {
        if ($order->status !== Order::STATUS_PENDING) {
            throw new InvalidArgumentException('Cannot apply discount to a non-pending order');
        }

        if ($discount < 0) {
            throw new InvalidArgumentException('Discount cannot be negative');
        }

        // Refresh to get current subtotal
        $order->refresh();

        if ($discount > $order->subtotal) {
            throw new InvalidArgumentException('Discount cannot exceed subtotal');
        }

        $order->discount_amount = $discount;
        $order->save();

        // Recalculate totals with new discount
        $this->calculateTotals($order);

        return $order->fresh();
    }

    /**
     * Complete an order and update inventory
     *
     * @param  Order  $order  Order to complete
     *
     * @throws InvalidArgumentException If order cannot be completed
     */
    public function complete(Order $order): Order
    {
        if ($order->status !== Order::STATUS_PENDING && $order->status !== Order::STATUS_PAID) {
            throw new InvalidArgumentException('Only pending or paid orders can be completed');
        }

        return DB::transaction(function () use ($order) {
            // Update inventory for each item
            foreach ($order->items as $item) {
                $product = $item->product;
                if ($product->stock_quantity < $item->quantity) {
                    throw new InvalidArgumentException("Insufficient stock for product: {$product->name}");
                }
                $product->stock_quantity -= $item->quantity;
                $product->save();
            }

            // Update order status
            $order->status = Order::STATUS_PAID;
            $order->save();

            // Update table status if table is assigned
            if ($order->table_id) {
                $this->updateTableStatus($order->table_id, Table::STATUS_AVAILABLE);
            }

            return $order->fresh();
        });
    }

    /**
     * Cancel an order and restore inventory if needed
     *
     * @param  Order  $order  Order to cancel
     *
     * @throws InvalidArgumentException If order cannot be cancelled
     */
    public function cancel(Order $order): Order
    {
        if ($order->status === Order::STATUS_CANCELLED) {
            throw new InvalidArgumentException('Order is already cancelled');
        }

        return DB::transaction(function () use ($order) {
            // Restore inventory if order was paid
            if ($order->status === Order::STATUS_PAID) {
                foreach ($order->items as $item) {
                    $product = $item->product;
                    $product->stock_quantity += $item->quantity;
                    $product->save();
                }
            }

            // Update order status
            $order->status = Order::STATUS_CANCELLED;
            $order->save();

            // Update table status if table is assigned
            if ($order->table_id) {
                $this->updateTableStatus($order->table_id, Table::STATUS_AVAILABLE);
            }

            return $order->fresh();
        });
    }

    /**
     * Generate a unique order number
     *
     * @param  int  $storeId  Store ID
     */
    protected function generateOrderNumber(int $storeId): string
    {
        $date = now()->format('Ymd');
        $prefix = "ORD-{$storeId}-{$date}";

        // Get the count of orders for this store today
        $count = Order::where('store_id', $storeId)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        $sequence = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        $orderNumber = "{$prefix}-{$sequence}";

        // Ensure uniqueness
        while (Order::where('order_number', $orderNumber)->exists()) {
            $count++;
            $sequence = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
            $orderNumber = "{$prefix}-{$sequence}";
        }

        return $orderNumber;
    }

    /**
     * Update table status
     *
     * @param  int  $tableId  Table ID
     * @param  string  $status  New status
     */
    protected function updateTableStatus(int $tableId, string $status): void
    {
        $table = Table::find($tableId);
        if ($table) {
            $table->status = $status;
            $table->save();
        }
    }

    /**
     * Find an order by ID
     *
     * @param  int  $id  Order ID
     */
    public function find(int $id): ?Order
    {
        return Order::with(['items.product', 'payments'])->find($id);
    }

    /**
     * Get orders by store
     *
     * @param  int  $storeId  Store ID
     */
    public function getByStore(int $storeId): Collection
    {
        return Order::where('store_id', $storeId)
            ->with(['items.product', 'payments'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get pending orders
     *
     * @param  int|null  $storeId  Optional store filter
     */
    public function getPending(?int $storeId = null): Collection
    {
        $query = Order::where('status', Order::STATUS_PENDING)
            ->with(['items.product', 'table']);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query->orderBy('created_at', 'asc')->get();
    }
}
