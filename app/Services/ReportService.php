<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Get daily sales report for a specific date
     *
     * @param Carbon $date The date to get sales for
     * @param int|null $storeId Optional store filter
     * @param int|null $userId Optional user filter for data isolation
     * @return array
     */
    public function dailySales(Carbon $date, ?int $storeId = null, ?int $userId = null): array
    {
        $query = Order::whereDate('created_at', $date->toDateString())
            ->whereIn('status', [Order::STATUS_COMPLETED, Order::STATUS_PAID]);

        if ($userId !== null) {
            $query->whereHas('store', fn($q) => $q->where('user_id', $userId));
        }

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        $orders = $query->get();

        $totalSales = $orders->sum('total');
        $totalOrders = $orders->count();
        $totalItems = 0;
        $totalTax = $orders->sum('tax_amount');
        $totalDiscount = $orders->sum('discount_amount');

        foreach ($orders as $order) {
            $totalItems += $order->items->sum('quantity');
        }

        return [
            'date' => $date->toDateString(),
            'store_id' => $storeId,
            'total_sales' => (float) $totalSales,
            'total_orders' => $totalOrders,
            'total_items' => $totalItems,
            'total_tax' => (float) $totalTax,
            'total_discount' => (float) $totalDiscount,
            'average_order_value' => $totalOrders > 0 ? round($totalSales / $totalOrders, 2) : 0,
        ];
    }

    /**
     * Get sales report for a date range
     *
     * @param Carbon $start Start date
     * @param Carbon $end End date
     * @param int|null $storeId Optional store filter
     * @param int|null $userId Optional user filter for data isolation
     * @return array
     */
    public function salesByRange(Carbon $start, Carbon $end, ?int $storeId = null, ?int $userId = null): array
    {
        $query = Order::whereDate('created_at', '>=', $start->toDateString())
            ->whereDate('created_at', '<=', $end->toDateString())
            ->whereIn('status', [Order::STATUS_COMPLETED, Order::STATUS_PAID]);

        if ($userId !== null) {
            $query->whereHas('store', fn($q) => $q->where('user_id', $userId));
        }

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        $orders = $query->get();

        $totalSales = $orders->sum('total');
        $totalOrders = $orders->count();
        $totalItems = 0;
        $totalTax = $orders->sum('tax_amount');
        $totalDiscount = $orders->sum('discount_amount');

        foreach ($orders as $order) {
            $totalItems += $order->items->sum('quantity');
        }

        // Get daily breakdown
        $dailyBreakdown = [];
        $currentDate = $start->copy();
        while ($currentDate->lte($end)) {
            $dailyBreakdown[] = $this->dailySales($currentDate->copy(), $storeId, $userId);
            $currentDate->addDay();
        }

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'store_id' => $storeId,
            'total_sales' => (float) $totalSales,
            'total_orders' => $totalOrders,
            'total_items' => $totalItems,
            'total_tax' => (float) $totalTax,
            'total_discount' => (float) $totalDiscount,
            'average_order_value' => $totalOrders > 0 ? round($totalSales / $totalOrders, 2) : 0,
            'daily_breakdown' => $dailyBreakdown,
        ];
    }

    /**
     * Get top selling products within a date range
     *
     * @param Carbon $start Start date
     * @param Carbon $end End date
     * @param int $limit Number of products to return
     * @param int|null $storeId Optional store filter
     * @param int|null $userId Optional user filter for data isolation
     * @return Collection
     */
    public function topProducts(Carbon $start, Carbon $end, int $limit = 10, ?int $storeId = null, ?int $userId = null): Collection
    {
        $query = OrderItem::select(
                'order_items.product_id',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('SUM(order_items.subtotal) as total_revenue')
            )
            ->join('orders', 'order_items.order_id', '=', 'orders.id');

        if ($userId !== null) {
            $query->join('stores', 'orders.store_id', '=', 'stores.id')
                ->where('stores.user_id', $userId);
        }

        $query->whereDate('orders.created_at', '>=', $start->toDateString())
            ->whereDate('orders.created_at', '<=', $end->toDateString())
            ->whereIn('orders.status', [Order::STATUS_COMPLETED, Order::STATUS_PAID]);

        if ($storeId !== null) {
            $query->where('orders.store_id', $storeId);
        }

        $results = $query->groupBy('order_items.product_id')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();

        // Load product details
        return $results->map(function ($item) {
            $product = Product::withTrashed()->find($item->product_id);
            return [
                'product_id' => $item->product_id,
                'product_name' => $product ? $product->name : 'Unknown Product',
                'product_sku' => $product ? $product->sku : null,
                'total_quantity' => (int) $item->total_quantity,
                'total_revenue' => (float) $item->total_revenue,
            ];
        });
    }

    /**
     * Get sales summary by payment method within a date range
     *
     * @param Carbon $start Start date
     * @param Carbon $end End date
     * @param int|null $storeId Optional store filter
     * @return Collection
     */
    public function salesByPaymentMethod(Carbon $start, Carbon $end, ?int $storeId = null): Collection
    {
        $query = DB::table('payments')
            ->select(
                'payments.method',
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('SUM(payments.amount) as total_amount')
            )
            ->join('orders', 'payments.order_id', '=', 'orders.id')
            ->whereDate('orders.created_at', '>=', $start->toDateString())
            ->whereDate('orders.created_at', '<=', $end->toDateString())
            ->whereIn('orders.status', [Order::STATUS_COMPLETED, Order::STATUS_PAID]);

        if ($storeId !== null) {
            $query->where('orders.store_id', $storeId);
        }

        return $query->groupBy('payments.method')
            ->orderByDesc('total_amount')
            ->get()
            ->map(function ($item) {
                return [
                    'method' => $item->method,
                    'transaction_count' => (int) $item->transaction_count,
                    'total_amount' => (float) $item->total_amount,
                ];
            });
    }

    /**
     * Get hourly sales distribution for a specific date
     *
     * @param Carbon $date The date to analyze
     * @param int|null $storeId Optional store filter
     * @return array
     */
    public function hourlySales(Carbon $date, ?int $storeId = null): array
    {
        $query = Order::whereDate('created_at', $date->toDateString())
            ->whereIn('status', [Order::STATUS_COMPLETED, Order::STATUS_PAID]);

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        $orders = $query->get();

        // Initialize hourly buckets
        $hourlyData = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $hourlyData[$hour] = [
                'hour' => $hour,
                'total_sales' => 0.0,
                'order_count' => 0,
            ];
        }

        // Aggregate by hour
        foreach ($orders as $order) {
            $hour = (int) $order->created_at->format('G');
            $hourlyData[$hour]['total_sales'] += (float) $order->total;
            $hourlyData[$hour]['order_count']++;
        }

        return array_values($hourlyData);
    }
}
