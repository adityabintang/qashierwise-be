<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MediaStorageService
{
    /**
     * Valid media types for path generation
     */
    protected array $validTypes = ['image', 'document', 'audio', 'video'];

    /**
     * Get the storage disk name based on configuration
     *
     * @return string
     */
    public function getDiskName(): string
    {
        $disk = env('FILESYSTEM_DISK', 'public');

        // Validate R2 configuration if r2 disk is selected
        if ($disk === 'r2' && !$this->validateR2Configuration()) {
            Log::warning('R2 configuration is incomplete. Falling back to local storage.');
            return 'public';
        }

        return $disk;
    }

    /**
     * Validate R2 configuration
     *
     * @return bool
     */
    protected function validateR2Configuration(): bool
    {
        $required = ['R2_ACCESS_KEY_ID', 'R2_SECRET_ACCESS_KEY', 'R2_BUCKET', 'R2_ENDPOINT'];

        foreach ($required as $key) {
            if (empty(env($key))) {
                Log::warning("R2 configuration missing: {$key}");
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitize filename by removing special characters
     *
     * @param string $filename
     * @return string
     */
    public function sanitizeFilename(string $filename): string
    {
        // Remove any character that is not alphanumeric, dot, underscore, or hyphen
        $sanitized = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);

        // Ensure filename is not empty after sanitization
        if (empty($sanitized)) {
            $sanitized = 'file';
        }

        return $sanitized;
    }

    /**
     * Generate unique filename with timestamp prefix
     *
     * @param string $originalFilename
     * @return string
     */
    public function generateUniqueFilename(string $originalFilename): string
    {
        $sanitized = $this->sanitizeFilename($originalFilename);
        $timestamp = time();

        return "{$timestamp}_{$sanitized}";
    }

    /**
     * Generate storage path for media file
     *
     * @param string $type Media type (image, document, audio, video)
     * @param string $filename
     * @return string
     */
    public function generatePath(string $type, string $filename): string
    {
        // Validate type
        if (!in_array($type, $this->validTypes)) {
            throw new \InvalidArgumentException("Invalid media type: {$type}");
        }

        // Generate path following pattern: whatsapp/{type}s/{filename}
        return "whatsapp/{$type}s/{$filename}";
    }

    /**
     * Store media file to configured storage disk
     *
     * @param UploadedFile $file
     * @param string $type Media type (image, document, audio, video)
     * @return array{url: string, path: string, filename: string}
     * @throws \Exception
     */
    public function store(UploadedFile $file, string $type): array
    {
        $diskName = $this->getDiskName();
        $originalFilename = $file->getClientOriginalName();
        $uniqueFilename = $this->generateUniqueFilename($originalFilename);
        $path = $this->generatePath($type, $uniqueFilename);

        try {
            // Store file with appropriate content type
            $options = [
                'ContentType' => $file->getMimeType(),
            ];

            Storage::disk($diskName)->put($path, file_get_contents($file->getRealPath()), $options);

            $url = $this->getPublicUrl($path);

            return [
                'url' => $url,
                'path' => $path,
                'filename' => $uniqueFilename,
            ];
        } catch (\Exception $e) {
            Log::error('Media upload failed', [
                'error' => $e->getMessage(),
                'disk' => $diskName,
                'path' => $path,
            ]);
            throw $e;
        }
    }

    /**
     * Get public URL for stored file
     *
     * @param string $path
     * @return string
     */
    public function getPublicUrl(string $path): string
    {
        $diskName = $this->getDiskName();

        if ($diskName === 'r2') {
            // For R2, use the configured public URL
            $baseUrl = rtrim(env('R2_URL', ''), '/');
            return "{$baseUrl}/{$path}";
        }

        // For local storage, use Laravel's url() helper
        return url("storage/{$path}");
    }

    /**
     * Delete media file from storage
     *
     * @param string $path
     * @return bool
     */
    public function delete(string $path): bool
    {
        $diskName = $this->getDiskName();

        try {
            return Storage::disk($diskName)->delete($path);
        } catch (\Exception $e) {
            Log::error('Media deletion failed', [
                'error' => $e->getMessage(),
                'disk' => $diskName,
                'path' => $path,
            ]);
            return false;
        }
    }
}
