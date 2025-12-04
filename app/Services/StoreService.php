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
     * @param array $data Store data (name, code, address, phone, is_active)
     * @return Store
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
     * @param Store $store Store to update
     * @param array $data Updated data
     * @return Store
     */
    public function update(Store $store, array $data): Store
    {
        $store->update($data);
        return $store->fresh();
    }

    /**
     * Deactivate a store (prevents new orders)
     *
     * @param Store $store Store to deactivate
     * @return Store
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
     * @param Store $store Store to activate
     * @return Store
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
     * @param Store $store Store to validate
     * @return bool
     * @throws InvalidArgumentException If store is inactive
     */
    public function validateForOrderCreation(Store $store): bool
    {
        if (!$store->is_active) {
            throw new InvalidArgumentException('Cannot create order at inactive store');
        }
        return true;
    }

    /**
     * Check if a store can accept new orders (non-throwing version)
     *
     * @param Store $store Store to check
     * @return bool
     */
    public function canAcceptOrders(Store $store): bool
    {
        return $store->is_active === true;
    }

    /**
     * Generate a unique store code
     *
     * @param string $name Store name
     * @return string
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
     * @param int $id Store ID
     * @return Store|null
     */
    public function find(int $id): ?Store
    {
        return Store::find($id);
    }

    /**
     * Find a store by code
     *
     * @param string $code Store code
     * @return Store|null
     */
    public function findByCode(string $code): ?Store
    {
        return Store::where('code', $code)->first();
    }

    /**
     * Get all stores
     *
     * @return Collection
     */
    public function getAll(): Collection
    {
        return Store::all();
    }

    /**
     * Get all active stores
     *
     * @return Collection
     */
    public function getActive(): Collection
    {
        return Store::where('is_active', true)->get();
    }

    /**
     * Get store with order counts
     *
     * @param Store $store Store to get statistics for
     * @return array
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
