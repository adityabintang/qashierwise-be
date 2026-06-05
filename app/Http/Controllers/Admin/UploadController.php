<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Image uploads for the blog admin (featured image, SEO image, rich-editor
 * attachments). Stores on the public `r2` disk and returns the key + URL.
 * Replaces Filament's FileUpload component.
 */
class UploadController extends Controller
{
    /** @var array<string, string> */
    private array $directories = [
        'featured' => 'blog/featured',
        'seo' => 'blog/seo',
        'content' => 'blog/attachments',
    ];

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'image', 'max:20480'], // 20 MB, same as Filament
            'type' => ['nullable', 'in:featured,seo,content'],
        ]);

        $directory = $this->directories[$request->input('type', 'content')] ?? 'blog/attachments';

        $path = $request->file('file')->store($directory, 'r2');

        return response()->json([
            'success' => true,
            'data' => [
                'path' => $path,
                'url' => Storage::disk('r2')->url($path),
            ],
        ]);
    }
}
