<?php

namespace App\Filament\Resources\BlogPosts\Pages;

use App\Filament\Resources\BlogPosts\BlogPostResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class EditBlogPost extends EditRecord
{
    protected static string $resource = BlogPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->extraAttributes([
                    'wire:loading.attr' => 'disabled',
                    'wire:loading.class' => 'opacity-50 cursor-not-allowed',
                ]),
            $this->getCancelFormAction(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = $this->processImages($data);
        return $data;
    }

    protected function afterSave(): void
    {
        // Refresh form state untuk update preview gambar
        $this->fillForm();
    }

    protected function processImages(array $data): array
    {
        $originalData = $this->record->toArray();

        // Process featured_image jika ada perubahan
        if (!empty($data['featured_image']) && $data['featured_image'] !== $originalData['featured_image']) {
            $data['featured_image'] = $this->convertToWebP($data['featured_image'], 1920);
        }

        // Process seo_image jika ada perubahan
        if (!empty($data['seo_image']) && $data['seo_image'] !== $originalData['seo_image']) {
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

        // Skip jika sudah WebP
        if (str_ends_with(strtolower($path), '.webp')) {
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
