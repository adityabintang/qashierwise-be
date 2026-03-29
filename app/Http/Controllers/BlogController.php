<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(): View
    {
        $initialPosts = BlogPost::query()
            ->published()
            ->with(['author:id,name', 'category:id,name,slug'])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->cursorPaginate(10);

        $featuredPost = $initialPosts->getCollection()->first();
        $gridPosts = $initialPosts->getCollection()->slice(1)->values();

        return view('blog.index', [
            'featuredPost' => $featuredPost,
            'gridPosts' => $gridPosts,
            'nextCursor' => $initialPosts->nextCursor()?->encode(),
        ]);
    }

    public function loadMore(Request $request): JsonResponse
    {
        $encodedCursor = $request->query('cursor');
        $cursor = is_string($encodedCursor) && $encodedCursor !== ''
            ? Cursor::fromEncoded($encodedCursor)
            : null;

        $posts = BlogPost::query()
            ->published()
            ->with(['author:id,name', 'category:id,name,slug'])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->cursorPaginate(9, ['*'], 'cursor', $cursor);

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
