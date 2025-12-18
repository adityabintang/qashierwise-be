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
     * @param array $data Category data (name, description, is_active)
     * @return Category
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
     * @param Category $category Category to update
     * @param array $data Updated data
     * @return Category
     */
    public function update(Category $category, array $data): Category
    {
        // Regenerate slug if name changed and slug not provided
        if (isset($data['name']) && !isset($data['slug']) && $data['name'] !== $category->name) {
            $data['slug'] = $this->generateSlug($data['name'], $category->id);
        }

        $category->update($data);
        return $category->fresh();
    }

    /**
     * Delete a category (only if it has no products)
     *
     * @param Category $category Category to delete
     * @return bool
     * @throws \InvalidArgumentException If category has products
     */
    public function delete(Category $category): bool
    {
        // Check if category has products
        if ($category->products()->count() > 0) {
            throw new \InvalidArgumentException("Cannot delete category with products");
        }

        return $category->delete();
    }

    /**
     * Generate a unique slug for a category
     *
     * @param string $name Category name
     * @param int|null $excludeId Category ID to exclude from uniqueness check (for updates)
     * @return string
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
     * @param string $slug Slug to check
     * @param int|null $excludeId Category ID to exclude from check
     * @return bool
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
     * @param int $id Category ID
     * @return Category|null
     */
    public function find(int $id): ?Category
    {
        return Category::find($id);
    }

    /**
     * Find a category by slug
     *
     * @param string $slug Category slug
     * @return Category|null
     */
    public function findBySlug(string $slug): ?Category
    {
        return Category::where('slug', $slug)->first();
    }

    /**
     * Get all active categories
     *
     * @return Collection
     */
    public function getActive(): Collection
    {
        return Category::where('is_active', true)->get();
    }

    /**
     * Get all categories with product counts
     *
     * @param int|null $userId Filter by user ID (null for all)
     * @return Collection
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
