<?php

namespace App\Filament\Resources\BlogPosts\Pages;

use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Helpers\TimezoneDisplayHelper;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class CreateBlogPost extends CreateRecord
{
    protected static string $resource = BlogPostResource::class;

    protected function normalizeInputDateTimeToUtc(string $dateTime): Carbon
    {
        $hasExplicitTimezone = preg_match('/(Z|[+-]\d{2}:\d{2})$/', $dateTime) === 1;

        if ($hasExplicitTimezone) {
            return Carbon::parse($dateTime)->utc();
        }

        $viewerTimezone = TimezoneDisplayHelper::resolveDisplayTimezone()['timezone'];

        return Carbon::parse($dateTime, $viewerTimezone)->utc();
    }

    protected function onValidationError(ValidationException $exception): void
    {
        parent::onValidationError($exception);

        Notification::make()
            ->danger()
            ->title(__('admin.resources.blog_post.notifications.failed_title'))
            ->body(__('admin.resources.blog_post.notifications.failed_body'))
            ->icon('heroicon-o-exclamation-triangle')
            ->iconColor('danger')
            ->duration(6000)
            ->send();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Validate published_at for Published status
        if (isset($data['status'])) {
            // Convert enum to string if needed
            $status = is_object($data['status']) ? $data['status']->value : $data['status'];

            if ($status === 'published' && ! empty($data['published_at'])) {
                $publishedAtUtc = $this->normalizeInputDateTimeToUtc($data['published_at']);
                $nowUtc = Carbon::now('UTC');

                // Log for debugging
                \Log::info('Blog Post Validation (Create)', [
                    'status' => $status,
                    'published_at_input' => $data['published_at'],
                    'published_at_utc' => $publishedAtUtc->toDateTimeString(),
                    'now_utc' => $nowUtc->toDateTimeString(),
                    'is_future' => $publishedAtUtc->isAfter($nowUtc),
                ]);

                if ($publishedAtUtc->isAfter($nowUtc)) {
                    Notification::make()
                        ->danger()
                        ->title(__('admin.resources.blog_post.notifications.failed_title'))
                        ->body(__('admin.resources.blog_post.notifications.invalid_publish_date_body'))
                        ->icon('heroicon-o-exclamation-triangle')
                        ->iconColor('danger')
                        ->duration(9000)
                        ->send();

                    $this->halt();
                }
            }
        }

        $data = $this->processImages($data);

        return $data;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title(__('admin.resources.blog_post.notifications.created_title'))
            ->body(__('admin.resources.blog_post.notifications.created_body', ['title' => $this->record?->title]))
            ->icon('heroicon-o-check-circle')
            ->iconColor('success')
            ->duration(5000);
    }

    protected function processImages(array $data): array
    {
        if (! empty($data['featured_image'])) {
            $data['featured_image'] = $this->convertToWebP($data['featured_image'], 1920);
        }

        if (! empty($data['seo_image'])) {
            $data['seo_image'] = $this->convertToWebP($data['seo_image'], 1200);
        }

        return $data;
    }

    protected function convertToWebP(string $path, int $maxWidth = 1920): string
    {
        $disk = Storage::disk('r2');

        if (! $disk->exists($path)) {
            return $path;
        }

        $manager = new ImageManager(new Driver);
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
