<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(Request $request): View
    {
        BlogPost::publishDueScheduledPosts();

        $search = $request->query('search', '');
        $sort = $request->query('sort', 'latest');
        $selectedCategory = $request->query('category', '');
        $searchOperator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $query = BlogPost::query()
            ->published()
            ->with(['author:id,name', 'category:id,name,slug']);

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search, $searchOperator) {
                $q->where('title', $searchOperator, "%{$search}%")
                    ->orWhere('excerpt', $searchOperator, "%{$search}%")
                    ->orWhere('content', $searchOperator, "%{$search}%");
            });
        }

        if (is_string($selectedCategory) && $selectedCategory !== '') {
            $query->whereHas('category', function ($q) use ($selectedCategory) {
                $q->where('slug', $selectedCategory);
            });
        }

        // Apply sorting
        switch ($sort) {
            case 'oldest':
                $query->orderBy('published_at')->orderBy('id');
                break;
            case 'title':
                $query->orderBy('title');
                break;
            case 'latest':
            default:
                $query->orderByDesc('published_at')->orderByDesc('id');
                break;
        }

        $initialPosts = $query->cursorPaginate(10);
        $categories = BlogCategory::query()
            ->where('is_active', true)
            ->whereHas('posts', function ($q) {
                $q->published();
            })
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $featuredPost = $initialPosts->getCollection()->first();
        $gridPosts = $initialPosts->getCollection()->slice(1)->values();

        return view('blog.index', [
            'featuredPost' => $featuredPost,
            'gridPosts' => $gridPosts,
            'nextCursor' => $initialPosts->nextCursor()?->encode(),
            'search' => $search,
            'sort' => $sort,
            'categories' => $categories,
            'selectedCategory' => is_string($selectedCategory) ? $selectedCategory : '',
        ]);
    }

    public function loadMore(Request $request): JsonResponse
    {
        BlogPost::publishDueScheduledPosts();

        $encodedCursor = $request->query('cursor');
        $search = $request->query('search', '');
        $sort = $request->query('sort', 'latest');
        $selectedCategory = $request->query('category', '');
        $searchOperator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $cursor = is_string($encodedCursor) && $encodedCursor !== ''
            ? Cursor::fromEncoded($encodedCursor)
            : null;

        $query = BlogPost::query()
            ->published()
            ->with(['author:id,name', 'category:id,name,slug']);

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search, $searchOperator) {
                $q->where('title', $searchOperator, "%{$search}%")
                    ->orWhere('excerpt', $searchOperator, "%{$search}%")
                    ->orWhere('content', $searchOperator, "%{$search}%");
            });
        }

        if (is_string($selectedCategory) && $selectedCategory !== '') {
            $query->whereHas('category', function ($q) use ($selectedCategory) {
                $q->where('slug', $selectedCategory);
            });
        }

        // Apply sorting
        switch ($sort) {
            case 'oldest':
                $query->orderBy('published_at')->orderBy('id');
                break;
            case 'title':
                $query->orderBy('title');
                break;
            case 'latest':
            default:
                $query->orderByDesc('published_at')->orderByDesc('id');
                break;
        }

        $posts = $query->cursorPaginate(9, ['*'], 'cursor', $cursor);

        $html = view('blog.partials.post-cards', [
            'posts' => collect($posts->items()),
        ])->render();

        return response()->json([
            'html' => $html,
            'next_cursor' => $posts->nextCursor()?->encode(),
            'has_more' => $posts->hasMorePages(),
        ]);
    }

    public function show(string $slug): View
    {
        BlogPost::publishDueScheduledPosts();

        $post = BlogPost::query()
            ->published()
            ->with(['author:id,name', 'category:id,name,slug', 'tags:id,name,slug'])
            ->where('slug', $slug)
            ->firstOrFail();

        $relatedPosts = BlogPost::query()
            ->published()
            ->where('id', '!=', $post->id)
            ->latest('published_at')
            ->limit(3)
            ->get(['id', 'title', 'slug', 'excerpt', 'published_at']);

        return view('blog.show', [
            'post' => $post,
            'relatedPosts' => $relatedPosts,
        ]);
    }
}
