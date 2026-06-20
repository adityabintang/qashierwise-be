<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PostStatus;
use App\Http\Controllers\Admin\Concerns\ChecksBlogPermissions;
use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BlogPostController extends Controller
{
    use ChecksBlogPermissions;

    public function index(Request $request): JsonResponse
    {
        $this->authorizeBlog('view', 'blog_post');

        $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $allowedSorts = ['title', 'status', 'created_at', 'published_at'];
        $sort = in_array($request->query('sort'), $allowedSorts, true) ? $request->query('sort') : 'created_at';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $query = BlogPost::query()
            ->with(['author:id,name', 'category:id,name,slug'])
            ->when($request->filled('search'), function ($q) use ($request, $operator) {
                $search = $request->query('search');
                $q->where(function ($inner) use ($search, $operator) {
                    $inner->where('title', $operator, "%{$search}%")
                        ->orWhere('excerpt', $operator, "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('category'), fn ($q) => $q->where('blog_category_id', $request->query('category')))
            ->orderBy($sort, $direction);

        $perPage = min(max((int) $request->query('per_page', 10), 1), 100);
        $posts = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'success' => true,
            'data' => collect($posts->items())->map(fn (BlogPost $p) => $this->shape($p))->all(),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    public function show(BlogPost $post): JsonResponse
    {
        $this->authorizeBlog('view', 'blog_post');

        $post->load(['author:id,name', 'category:id,name,slug', 'tags:id,name,slug']);

        return response()->json(['success' => true, 'data' => $this->shape($post, full: true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeBlog('create', 'blog_post');

        $data = $this->validatePayload($request);
        $tags = $data['tags'] ?? [];
        unset($data['tags']);

        $data['user_id'] = auth()->id();

        $post = BlogPost::create($data);
        $post->tags()->sync($tags);
        $post->load(['author:id,name', 'category:id,name,slug', 'tags:id,name,slug']);

        return response()->json(['success' => true, 'data' => $this->shape($post, full: true)], 201);
    }

    public function update(Request $request, BlogPost $post): JsonResponse
    {
        $this->authorizeBlog('update', 'blog_post');

        $data = $this->validatePayload($request, $post->id);
        $tags = $data['tags'] ?? [];
        unset($data['tags']);

        $post->update($data);
        $post->tags()->sync($tags);
        $post->load(['author:id,name', 'category:id,name,slug', 'tags:id,name,slug']);

        return response()->json(['success' => true, 'data' => $this->shape($post, full: true)]);
    }

    public function destroy(BlogPost $post): JsonResponse
    {
        $this->authorizeBlog('delete', 'blog_post');

        $post->delete();

        return response()->json(['success' => true]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $this->authorizeBlog('delete', 'blog_post');

        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ])['ids'];

        BlogPost::whereIn('id', $ids)->delete();

        return response()->json(['success' => true, 'deleted' => count($ids)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('blog_posts', 'slug')->ignore($ignoreId)],
            'blog_category_id' => ['nullable', 'integer', 'exists:blog_categories,id'],
            'excerpt' => ['nullable', 'string'],
            'content' => ['required', 'string'],
            'featured_image' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(PostStatus::class)],
            'published_at' => ['nullable', 'date'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:160'],
            'seo_image' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:blog_tags,id'],
        ]);

        $status = $data['status'];
        $publishedAt = ! empty($data['published_at']) ? Carbon::parse($data['published_at']) : null;
        $now = Carbon::now();

        if ($status === PostStatus::Scheduled->value) {
            if (! $publishedAt || ! $publishedAt->isAfter($now)) {
                throw ValidationException::withMessages([
                    'published_at' => __('admin.resources.blog_post.notifications.invalid_schedule_date_body')
                        ?: 'Scheduled posts require a publish date in the future.',
                ]);
            }
        }

        if ($status === PostStatus::Published->value) {
            // Publishing now (or backdated): never allow a future date.
            if ($publishedAt && $publishedAt->isAfter($now)) {
                throw ValidationException::withMessages([
                    'published_at' => 'Tanggal publikasi tidak boleh melebihi waktu saat ini untuk status Published.',
                ]);
            }
            $data['published_at'] = $publishedAt ?? $now;
        } else {
            $data['published_at'] = $publishedAt;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(BlogPost $post, bool $full = false): array
    {
        $base = [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'status' => $post->status->value,
            'status_label' => $post->status->label(),
            'status_color' => $post->status->color(),
            'excerpt' => $post->excerpt,
            'featured_image' => $post->featured_image,
            'featured_image_url' => $post->featured_image_url,
            'category' => $post->category ? [
                'id' => $post->category->id,
                'name' => $post->category->name,
                'slug' => $post->category->slug,
            ] : null,
            'author' => $post->author ? ['id' => $post->author->id, 'name' => $post->author->name] : null,
            'published_at' => $post->published_at?->toIso8601String(),
            'created_at' => $post->created_at?->toIso8601String(),
        ];

        if (! $full) {
            return $base;
        }

        return array_merge($base, [
            'blog_category_id' => $post->blog_category_id,
            'content' => $post->content,
            'seo_title' => $post->seo_title,
            'seo_description' => $post->seo_description,
            'seo_image' => $post->seo_image,
            'seo_image_url' => $post->seo_image_url,
            'tags' => $post->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug])->all(),
            'updated_at' => $post->updated_at?->toIso8601String(),
        ]);
    }
}
