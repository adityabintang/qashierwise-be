<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ChecksBlogPermissions;
use App\Http\Controllers\Controller;
use App\Models\BlogTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BlogTagController extends Controller
{
    use ChecksBlogPermissions;

    public function index(Request $request): JsonResponse
    {
        $this->authorizeBlog('view', 'blog_tag');

        $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $query = BlogTag::query()
            ->withCount('posts')
            ->when($request->filled('search'), function ($q) use ($request, $operator) {
                $search = $request->query('search');
                $q->where(fn ($i) => $i->where('name', $operator, "%{$search}%")
                    ->orWhere('slug', $operator, "%{$search}%"));
            })
            ->orderBy('name');

        // `all=1` returns the full list (used by the post form's tag picker).
        if ($request->boolean('all')) {
            return response()->json([
                'success' => true,
                'data' => $query->get()->map(fn (BlogTag $t) => $this->shape($t))->all(),
            ]);
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $tags = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'success' => true,
            'data' => collect($tags->items())->map(fn (BlogTag $t) => $this->shape($t))->all(),
            'meta' => [
                'current_page' => $tags->currentPage(),
                'last_page' => $tags->lastPage(),
                'per_page' => $tags->perPage(),
                'total' => $tags->total(),
            ],
        ]);
    }

    public function show(BlogTag $tag): JsonResponse
    {
        $this->authorizeBlog('view', 'blog_tag');

        $tag->loadCount('posts');

        return response()->json(['success' => true, 'data' => $this->shape($tag)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeBlog('create', 'blog_tag');

        $tag = BlogTag::create($this->validatePayload($request));
        $tag->loadCount('posts');

        return response()->json(['success' => true, 'data' => $this->shape($tag)], 201);
    }

    public function update(Request $request, BlogTag $tag): JsonResponse
    {
        $this->authorizeBlog('update', 'blog_tag');

        $tag->update($this->validatePayload($request, $tag->id));
        $tag->loadCount('posts');

        return response()->json(['success' => true, 'data' => $this->shape($tag)]);
    }

    public function destroy(BlogTag $tag): JsonResponse
    {
        $this->authorizeBlog('delete', 'blog_tag');

        $tag->delete();

        return response()->json(['success' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('blog_tags', 'slug')->ignore($ignoreId)],
        ]);

        $data['slug'] = ! empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['name']);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(BlogTag $tag): array
    {
        return [
            'id' => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
            'posts_count' => $tag->posts_count ?? $tag->posts()->count(),
            'created_at' => $tag->created_at?->toIso8601String(),
            'updated_at' => $tag->updated_at?->toIso8601String(),
        ];
    }
}
