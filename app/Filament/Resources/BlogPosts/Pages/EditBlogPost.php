<?php

namespace App\Filament\Resources\BlogPosts\Pages;

use App\Filament\Resources\BlogPosts\BlogPostResource;
use Carbon\Carbon;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class EditBlogPost extends EditRecord
{
    protected static string $resource = BlogPostResource::class;

    public ?string $publishDateValidationDetailsHtml = null;

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

    public function content(Schema $schema): Schema
    {
        $schema = parent::content($schema);

        return $schema->components([
            ...$schema->getComponents(),
            Html::make(function (): string {
                if (blank($this->publishDateValidationDetailsHtml)) {
                    return '';
                }

                return view('filament.notifications.publish-date-error-footer', [
                    'detailsHtml' => $this->publishDateValidationDetailsHtml,
                ])->render();
            })
                ->key('publish-date-validation-footer')
                ->columnSpanFull()
                ->hidden(fn (): bool => blank($this->publishDateValidationDetailsHtml)),
        ]);
    }

    protected function beforeValidate(): void
    {
        $this->publishDateValidationDetailsHtml = null;
    }

    protected function onValidationError(ValidationException $exception): void
    {
        parent::onValidationError($exception);

        Notification::make()
            ->danger()
            ->title('Gagal Menyimpan Blog Post')
            ->body('Periksa kembali input form yang belum valid.')
            ->icon('heroicon-o-exclamation-triangle')
            ->iconColor('danger')
            ->duration(6000)
            ->send();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Validate published_at for Published status
        if (isset($data['status'])) {
            // Convert enum to string if needed
            $status = is_object($data['status']) ? $data['status']->value : $data['status'];

            if ($status === 'published' && ! empty($data['published_at'])) {
                // Convert input datetime (Asia/Jakarta) to UTC
                $publishedAtUtc = Carbon::parse($data['published_at'], 'Asia/Jakarta')->setTimezone('UTC');
                $nowUtc = Carbon::now('UTC');

                // Log for debugging
                \Log::info('Blog Post Validation', [
                    'status' => $status,
                    'published_at_input' => $data['published_at'],
                    'published_at_utc' => $publishedAtUtc->toDateTimeString(),
                    'now_utc' => $nowUtc->toDateTimeString(),
                    'is_future' => $publishedAtUtc->isAfter($nowUtc),
                ]);

                if ($publishedAtUtc->isAfter($nowUtc)) {
                    $jakartaInput = Carbon::parse($data['published_at'], 'Asia/Jakarta');
                    $jakartaNow = $nowUtc->copy()->setTimezone('Asia/Jakarta');

                    $this->publishDateValidationDetailsHtml = view('filament.notifications.invalid-publish-date', [
                        'jakartaInput' => $jakartaInput->format('d M Y, H:i:s'),
                        'utcInput' => $publishedAtUtc->format('d M Y, H:i:s'),
                        'jakartaNow' => $jakartaNow->format('d M Y, H:i:s'),
                        'utcNow' => $nowUtc->format('d M Y, H:i:s'),
                    ])->render();

                    Notification::make()
                        ->danger()
                        ->title('Gagal Menyimpan Blog Post')
                        ->body('Tanggal publikasi tidak boleh lebih besar dari waktu saat ini untuk status Published.')
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

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Blog Post Berhasil Disimpan')
            ->body('Artikel "'.$this->record->title.'" telah diperbarui.')
            ->icon('heroicon-o-check-circle')
            ->iconColor('success')
            ->duration(5000);
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
        if (! empty($data['featured_image']) && $data['featured_image'] !== $originalData['featured_image']) {
            $data['featured_image'] = $this->convertToWebP($data['featured_image'], 1920);
        }

        // Process seo_image jika ada perubahan
        if (! empty($data['seo_image']) && $data['seo_image'] !== $originalData['seo_image']) {
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

        // Skip jika sudah WebP
        if (str_ends_with(strtolower($path), '.webp')) {
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
