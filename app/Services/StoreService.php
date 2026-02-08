<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StoreService
{
    /**
     * Create a new store with auto-generated code if not provided
     *
     * @param  array  $data  Store data (name, code, address, phone, is_active)
     */
    public function create(array $data): Store
    {
        // Generate code if not provided
        if (empty($data['code'])) {
            $data['code'] = $this->generateStoreCode($data['name']);
        }

        // Set default values
        $data['is_active'] = $data['is_active'] ?? true;

        return Store::create($data);
    }

    /**
     * Update an existing store
     *
     * @param  Store  $store  Store to update
     * @param  array  $data  Updated data
     */
    public function update(Store $store, array $data): Store
    {
        $store->update($data);

        return $store->fresh();
    }

    /**
     * Deactivate a store (prevents new orders)
     *
     * @param  Store  $store  Store to deactivate
     */
    public function deactivate(Store $store): Store
    {
        $store->is_active = false;
        $store->save();

        return $store->fresh();
    }

    /**
     * Activate a store
     *
     * @param  Store  $store  Store to activate
     */
    public function activate(Store $store): Store
    {
        $store->is_active = true;
        $store->save();

        return $store->fresh();
    }

    /**
     * Validate if a store can accept new orders
     *
     * @param  Store  $store  Store to validate
     *
     * @throws InvalidArgumentException If store is inactive
     */
    public function validateForOrderCreation(Store $store): bool
    {
        if (! $store->is_active) {
            throw new InvalidArgumentException('Cannot create order at inactive store');
        }

        return true;
    }

    /**
     * Check if a store can accept new orders (non-throwing version)
     *
     * @param  Store  $store  Store to check
     */
    public function canAcceptOrders(Store $store): bool
    {
        return $store->is_active === true;
    }

    /**
     * Generate a unique store code
     *
     * @param  string  $name  Store name
     */
    protected function generateStoreCode(string $name): string
    {
        // Generate prefix from name (first 3 chars, uppercase)
        $namePrefix = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name), 0, 3));

        // Ensure we have at least 3 characters
        $namePrefix = str_pad($namePrefix, 3, 'X');

        // Generate unique suffix
        $uniqueSuffix = strtoupper(Str::random(3));

        // Combine parts to create code
        $baseCode = "{$namePrefix}{$uniqueSuffix}";

        // Ensure uniqueness
        $code = $baseCode;
        $counter = 1;
        while (Store::where('code', $code)->exists()) {
            $code = "{$baseCode}{$counter}";
            $counter++;
        }

        return $code;
    }

    /**
     * Find a store by ID
     *
     * @param  int  $id  Store ID
     */
    public function find(int $id, ?int $userId = null): ?Store
    {
        $query = Store::where('id', $id);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->first();
    }

    /**
     * Find a store by code
     *
     * @param  string  $code  Store code
     */
    public function findByCode(string $code, ?int $userId = null): ?Store
    {
        $query = Store::where('code', $code);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->first();
    }

    /**
     * Get all stores
     */
    public function getAll(?int $userId = null): Collection
    {
        $query = Store::query();

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }

    /**
     * Get all active stores
     */
    public function getActive(?int $userId = null): Collection
    {
        $query = Store::where('is_active', true);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }

    /**
     * Get store with order counts
     *
     * @param  Store  $store  Store to get statistics for
     */
    public function getWithStatistics(Store $store): array
    {
        return [
            'store' => $store,
            'order_count' => $store->orders()->count(),
            'pending_orders' => $store->orders()->where('status', 'pending')->count(),
            'completed_orders' => $store->orders()->where('status', 'completed')->count(),
            'table_count' => $store->tables()->count(),
            'staff_count' => $store->posUsers()->where('is_active', true)->count(),
        ];
    }
}
