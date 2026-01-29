<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class ProductService
{
    /**
     * Create a new product with auto-generated SKU
     *
     * @param  array  $data  Product data (name, category_id, price, stock_quantity, description, is_active)
     */
    public function create(array $data): Product
    {
        // Generate SKU if not provided
        if (empty($data['sku'])) {
            $data['sku'] = $this->generateSku($data['name'], $data['category_id'] ?? null);
        }

        // Set default values
        $data['is_active'] = $data['is_active'] ?? true;
        $data['stock_quantity'] = $data['stock_quantity'] ?? 0;

        return Product::create($data);
    }

    /**
     * Update an existing product
     *
     * @param  Product  $product  Product to update
     * @param  array  $data  Updated data
     */
    public function update(Product $product, array $data): Product
    {
        $product->update($data);

        return $product->fresh();
    }

    /**
     * Soft delete a product
     *
     * @param  Product  $product  Product to delete
     */
    public function delete(Product $product): bool
    {
        return $product->delete();
    }

    /**
     * Search products by name or SKU
     *
     * @param  string  $query  Search query
     */
    public function search(string $query, int $userId, bool $activeOnly = true): Collection
    {
        $queryBuilder = Product::where('user_id', $userId)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('sku', 'like', "%{$query}%");
            });

        if ($activeOnly) {
            $queryBuilder->where('is_active', true);
        }

        return $queryBuilder->get();
    }

    /**
     * Generate a unique SKU for a product
     *
     * @param  string  $name  Product name
     * @param  int|null  $categoryId  Category ID (optional)
     */
    public function generateSku(string $name, ?int $categoryId = null): string
    {
        // Get category prefix if category exists
        $categoryPrefix = '';
        if ($categoryId) {
            $category = Category::find($categoryId);
            if ($category) {
                // Take first 3 characters of category name, uppercase
                $categoryPrefix = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $category->name), 0, 3));
            }
        }

        // Generate product prefix from name (first 3 chars, uppercase)
        $namePrefix = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name), 0, 3));

        // Generate unique suffix using timestamp and random string
        $uniqueSuffix = strtoupper(Str::random(4));
        $timestamp = substr(time(), -4);

        // Combine parts to create SKU
        $baseSku = $categoryPrefix ? "{$categoryPrefix}-{$namePrefix}-{$timestamp}{$uniqueSuffix}" : "{$namePrefix}-{$timestamp}{$uniqueSuffix}";

        // Ensure uniqueness
        $sku = $baseSku;
        $counter = 1;
        while (Product::withTrashed()->where('sku', $sku)->exists()) {
            $sku = "{$baseSku}-{$counter}";
            $counter++;
        }

        return $sku;
    }

    /**
     * Find a product by ID
     *
     * @param  int  $id  Product ID
     */
    public function find(int $id, ?int $userId = null): ?Product
    {
        $query = Product::where('id', $id);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->first();
    }

    /**
     * Get all active products
     */
    public function getActive(?int $userId = null): Collection
    {
        $query = Product::where('is_active', true);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }

    /**
     * Get products by category
     *
     * @param  int  $categoryId  Category ID
     */
    public function getByCategory(int $categoryId, ?int $userId = null): Collection
    {
        $query = Product::where('category_id', $categoryId)
            ->where('is_active', true);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }

    /**
     * Update product stock quantity
     *
     * @param  Product  $product  Product to update
     * @param  int  $quantity  New quantity (can be negative for reduction)
     */
    public function updateStock(Product $product, int $quantity): Product
    {
        $product->stock_quantity = $quantity;
        $product->save();

        return $product->fresh();
    }

    /**
     * Reduce product stock by a given amount
     *
     * @param  Product  $product  Product to update
     * @param  int  $amount  Amount to reduce
     *
     * @throws \InvalidArgumentException If insufficient stock
     */
    public function reduceStock(Product $product, int $amount): Product
    {
        if ($product->stock_quantity < $amount) {
            throw new \InvalidArgumentException("Insufficient stock for product: {$product->name}");
        }

        $product->stock_quantity -= $amount;
        $product->save();

        return $product->fresh();
    }

    /**
     * Increase product stock by a given amount
     *
     * @param  Product  $product  Product to update
     * @param  int  $amount  Amount to increase
     */
    public function increaseStock(Product $product, int $amount): Product
    {
        $product->stock_quantity += $amount;
        $product->save();

        return $product->fresh();
    }
}
