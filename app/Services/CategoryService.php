<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class CategoryService
{
    /**
     * Create a new category with auto-generated slug
     *
     * @param  array  $data  Category data (name, description, is_active)
     */
    public function create(array $data): Category
    {
        // Generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['name']);
        }

        // Set default values
        $data['is_active'] = $data['is_active'] ?? true;

        return Category::create($data);
    }

    /**
     * Update an existing category
     *
     * @param  Category  $category  Category to update
     * @param  array  $data  Updated data
     */
    public function update(Category $category, array $data): Category
    {
        // Regenerate slug if name changed and slug not provided
        if (isset($data['name']) && ! isset($data['slug']) && $data['name'] !== $category->name) {
            $data['slug'] = $this->generateSlug($data['name'], $category->id);
        }

        $category->update($data);

        return $category->fresh();
    }

    /**
     * Delete a category (only if it has no products)
     *
     * @param  Category  $category  Category to delete
     *
     * @throws \InvalidArgumentException If category has products
     */
    public function delete(Category $category): bool
    {
        // Check if category has products
        if ($category->products()->count() > 0) {
            throw new \InvalidArgumentException('Cannot delete category with products');
        }

        return $category->delete();
    }

    /**
     * Generate a unique slug for a category
     *
     * @param  string  $name  Category name
     * @param  int|null  $excludeId  Category ID to exclude from uniqueness check (for updates)
     */
    public function generateSlug(string $name, ?int $excludeId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        // Check for uniqueness
        while ($this->slugExists($slug, $excludeId)) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * Check if a slug already exists
     *
     * @param  string  $slug  Slug to check
     * @param  int|null  $excludeId  Category ID to exclude from check
     */
    private function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $query = Category::where('slug', $slug);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Find a category by ID
     *
     * @param  int  $id  Category ID
     */
    public function find(int $id, ?int $userId = null): ?Category
    {
        $query = Category::where('id', $id);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->first();
    }

    /**
     * Find a category by slug
     *
     * @param  string  $slug  Category slug
     */
    public function findBySlug(string $slug, ?int $userId = null): ?Category
    {
        $query = Category::where('slug', $slug);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->first();
    }

    /**
     * Get all active categories
     */
    public function getActive(?int $userId = null): Collection
    {
        $query = Category::where('is_active', true);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }

    public function getAll(?int $userId = null, bool $activeOnly = true): Collection
    {
        $query = Category::query();

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    /**
     * Get all categories with product counts
     *
     * @param  int|null  $userId  Filter by user ID (null for all)
     */
    public function getAllWithProductCounts(?int $userId = null): Collection
    {
        $query = Category::withCount('products');

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }
}
