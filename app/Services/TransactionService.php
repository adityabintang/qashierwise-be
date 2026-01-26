<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TransactionService
{
    /**
     * Get paginated transaction list
     *
     * @param int $perPage Items per page
     * @param int|null $storeId Optional store filter
     * @param int|null $userId Optional user filter
     * @return LengthAwarePaginator
     */
    public function list(int $perPage = 20, ?int $storeId = null, ?int $userId = null): LengthAwarePaginator
    {
        $query = Order::where('status', Order::STATUS_PAID)
            ->with(['items.product', 'payments', 'store', 'posUser.user']);

        if ($userId !== null) {
            $query->whereHas('store', fn($q) => $q->where('user_id', $userId));
        }

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Search transactions by order number
     *
     * @param string $orderNumber Order number to search for
     * @param int|null $storeId Optional store filter
     * @param int|null $userId Optional user filter
     * @return Collection
     */
    public function searchByOrderNumber(string $orderNumber, ?int $storeId = null, ?int $userId = null): Collection
    {
        $query = Order::where('status', Order::STATUS_PAID)
            ->where('order_number', 'LIKE', "%{$orderNumber}%")
            ->with(['items.product', 'payments', 'store', 'posUser.user']);

        if ($userId !== null) {
            $query->whereHas('store', fn($q) => $q->where('user_id', $userId));
        }

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Filter transactions by date
     *
     * @param Carbon $date Date to filter by
     * @param int|null $storeId Optional store filter
     * @param int|null $userId Optional user filter
     * @return Collection
     */
    public function filterByDate(Carbon $date, ?int $storeId = null, ?int $userId = null): Collection
    {
        $query = Order::where('status', Order::STATUS_PAID)
            ->whereDate('created_at', $date->toDateString())
            ->with(['items.product', 'payments', 'store', 'posUser.user']);

        if ($userId !== null) {
            $query->whereHas('store', fn($q) => $q->where('user_id', $userId));
        }

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }


    /**
     * Filter transactions by date range
     *
     * @param Carbon $startDate Start date
     * @param Carbon $endDate End date
     * @param int|null $storeId Optional store filter
     * @param int|null $userId Optional user filter
     * @return Collection
     */
    public function filterByDateRange(Carbon $startDate, Carbon $endDate, ?int $storeId = null, ?int $userId = null): Collection
    {
        $query = Order::where('status', Order::STATUS_PAID)
            ->whereDate('created_at', '>=', $startDate->toDateString())
            ->whereDate('created_at', '<=', $endDate->toDateString())
            ->with(['items.product', 'payments', 'store', 'posUser.user']);

        if ($userId !== null) {
            $query->whereHas('store', fn($q) => $q->where('user_id', $userId));
        }

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get transaction details by ID
     *
     * @param int $id Order/Transaction ID
     * @param int|null $userId Optional user filter
     * @return Order|null
     */
    public function find(int $id, ?int $userId = null): ?Order
    {
        $query = Order::where('status', Order::STATUS_PAID)
            ->with(['items.product', 'payments', 'store', 'table', 'posUser.user']);

        if ($userId !== null) {
            $query->whereHas('store', fn($q) => $q->where('user_id', $userId));
        }

        return $query->find($id);
    }

    /**
     * Get transaction by order number
     *
     * @param string $orderNumber Exact order number
     * @return Order|null
     */
    public function findByOrderNumber(string $orderNumber): ?Order
    {
        return Order::where('status', Order::STATUS_PAID)
            ->where('order_number', $orderNumber)
            ->with(['items.product', 'payments', 'store', 'table', 'posUser.user'])
            ->first();
    }
}
