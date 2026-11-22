<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ChecksBlogPermissions;
use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BlogCategoryController extends Controller
{
    use ChecksBlogPermissions;

    public function index(Request $request): JsonResponse
    {
        $this->authorizeBlog('view', 'blog_category');

        $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $query = BlogCategory::query()
            ->withCount('posts')
            ->when($request->filled('search'), function ($q) use ($request, $operator) {
                $search = $request->query('search');
                $q->where(fn ($i) => $i->where('name', $operator, "%{$search}%")
                    ->orWhere('slug', $operator, "%{$search}%"));
            })
            ->orderBy('name');

        // `all=1` returns the full list (used by the post form's category picker).
        if ($request->boolean('all')) {
            return response()->json([
                'success' => true,
                'data' => $query->get()->map(fn (BlogCategory $c) => $this->shape($c))->all(),
            ]);
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $categories = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'success' => true,
            'data' => collect($categories->items())->map(fn (BlogCategory $c) => $this->shape($c))->all(),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
            ],
        ]);
    }

    public function show(BlogCategory $category): JsonResponse
    {
        $this->authorizeBlog('view', 'blog_category');

        $category->loadCount('posts');

        return response()->json(['success' => true, 'data' => $this->shape($category)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeBlog('create', 'blog_category');

        $category = BlogCategory::create($this->validatePayload($request));
        $category->loadCount('posts');

        return response()->json(['success' => true, 'data' => $this->shape($category)], 201);
    }

    public function update(Request $request, BlogCategory $category): JsonResponse
    {
        $this->authorizeBlog('update', 'blog_category');

        $category->update($this->validatePayload($request, $category->id));
        $category->loadCount('posts');

        return response()->json(['success' => true, 'data' => $this->shape($category)]);
    }

    public function destroy(BlogCategory $category): JsonResponse
    {
        $this->authorizeBlog('delete', 'blog_category');

        $category->delete();

        return response()->json(['success' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('blog_categories', 'slug')->ignore($ignoreId)],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:160'],
        ]);

        $data['slug'] = ! empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['name']);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(BlogCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'is_active' => (bool) $category->is_active,
            'seo_title' => $category->seo_title,
            'seo_description' => $category->seo_description,
            'posts_count' => $category->posts_count ?? $category->posts()->count(),
            'created_at' => $category->created_at?->toIso8601String(),
            'updated_at' => $category->updated_at?->toIso8601String(),
        ];
    }
}
