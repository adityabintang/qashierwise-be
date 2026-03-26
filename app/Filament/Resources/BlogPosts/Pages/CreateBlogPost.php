<?php

namespace App\Filament\Resources\BlogPosts\Pages;

use App\Filament\Resources\BlogPosts\BlogPostResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class CreateBlogPost extends CreateRecord
{
    protected static string $resource = BlogPostResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->processImages($data);
        return $data;
    }

    protected function processImages(array $data): array
    {
        if (!empty($data['featured_image'])) {
            $data['featured_image'] = $this->convertToWebP($data['featured_image'], 1920);
        }

        if (!empty($data['seo_image'])) {
            $data['seo_image'] = $this->convertToWebP($data['seo_image'], 1200);
        }

        return $data;
    }

    protected function convertToWebP(string $path, int $maxWidth = 1920): string
    {
        $disk = Storage::disk('r2');
        
        if (!$disk->exists($path)) {
            return $path;
        }

        $manager = new ImageManager(new Driver());
        $imageContent = $disk->get($path);
        $image = $manager->read($imageContent);

        // Resize jika lebar > maxWidth
        if ($image->width() > $maxWidth) {
            $image->scale(width: $maxWidth);
        }

        // Convert ke WebP
        $webpContent = $image->toWebp(quality: 85)->toString();

        // Generate new path dengan .webp extension
        $newPath = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '.webp', $path);
        
        // Delete old file
        $disk->delete($path);
        
        // Upload WebP
        $disk->put($newPath, $webpContent, ['ContentType' => 'image/webp']);

        return $newPath;
    }
}
