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
     */
    public function getDiskName(): string
    {
        $disk = env('FILESYSTEM_DISK', 'public');

        Log::debug('[R2-Upload] getDiskName called', [
            'configured_disk' => $disk,
            'timestamp' => now()->format('Y-m-d H:i:s.u'),
        ]);

        // Validate R2 configuration if r2 disk is selected
        if ($disk === 'r2' && ! $this->validateR2Configuration()) {
            Log::warning('[R2-Upload] R2 configuration validation failed, falling back to public disk', [
                'disk' => $disk,
            ]);

            return 'public';
        }

        if ($disk === 'r2') {
            Log::debug('[R2-Upload] R2 configuration valid', [
                'endpoint' => env('R2_ENDPOINT'),
                'bucket' => env('R2_BUCKET'),
                'region' => env('R2_REGION'),
            ]);
        }

        return $disk;
    }

    /**
     * Validate R2 configuration
     */
    protected function validateR2Configuration(): bool
    {
        $required = ['R2_ACCESS_KEY_ID', 'R2_SECRET_ACCESS_KEY', 'R2_BUCKET', 'R2_ENDPOINT'];
        $missing = [];

        foreach ($required as $key) {
            if (empty(env($key))) {
                $missing[] = $key;
                Log::warning("[R2-Upload] R2 configuration missing key: {$key}");
            }
        }

        if (! empty($missing)) {
            Log::warning('[R2-Upload] R2 validation failed', [
                'missing_keys' => $missing,
            ]);

            return false;
        }

        Log::debug('[R2-Upload] R2 configuration validation passed');

        return true;
    }

    /**
     * Sanitize filename by removing special characters
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
     * @param  string  $type  Media type (image, document, audio, video)
     */
    public function generatePath(string $type, string $filename): string
    {
        // Validate type
        if (! in_array($type, $this->validTypes)) {
            throw new \InvalidArgumentException("Invalid media type: {$type}");
        }

        // Generate path following pattern: whatsapp/{type}s/{filename}
        return "whatsapp/{$type}s/{$filename}";
    }

    /**
     * Store media file to configured storage disk
     *
     * @param  string  $type  Media type (image, document, audio, video)
     * @return array{url: string, path: string, filename: string}
     *
     * @throws \Exception
     */
    public function store(UploadedFile $file, string $type): array
    {
        $uploadId = uniqid('[R2-]', true);

        Log::debug("{$uploadId} [R2-Upload] store() called", [
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'media_type' => $type,
        ]);

        $diskName = $this->getDiskName();
        Log::debug("{$uploadId} [R2-Upload] Disk determined", ['disk' => $diskName]);

        $originalFilename = $file->getClientOriginalName();
        $uniqueFilename = $this->generateUniqueFilename($originalFilename);

        Log::debug("{$uploadId} [R2-Upload] Unique filename generated", [
            'original' => $originalFilename,
            'unique' => $uniqueFilename,
        ]);

        $path = $this->generatePath($type, $uniqueFilename);
        Log::debug("{$uploadId} [R2-Upload] Storage path generated", ['path' => $path]);

        try {
            Log::debug("{$uploadId} [R2-Upload] Starting file write to disk", [
                'disk' => $diskName,
                'path' => $path,
                'file_size' => $file->getSize(),
            ]);

            // Store file with appropriate content type
            $options = [
                'ContentType' => $file->getMimeType(),
            ];

            $fileContent = file_get_contents($file->getRealPath());
            Log::debug("{$uploadId} [R2-Upload] File content read", [
                'content_size' => strlen($fileContent),
            ]);

            Storage::disk($diskName)->put($path, $fileContent, $options);
            Log::debug("{$uploadId} [R2-Upload] File written successfully to disk");

            // Verify file exists
            $exists = Storage::disk($diskName)->exists($path);
            Log::debug("{$uploadId} [R2-Upload] File existence check after write", [
                'exists' => $exists,
            ]);

            $url = $this->getPublicUrl($path);
            Log::debug("{$uploadId} [R2-Upload] Public URL generated", ['url' => $url]);

            Log::info("{$uploadId} [R2-Upload] Store completed successfully", [
                'disk' => $diskName,
                'path' => $path,
                'url' => $url,
                'filename' => $uniqueFilename,
            ]);

            return [
                'url' => $url,
                'path' => $path,
                'filename' => $uniqueFilename,
            ];
        } catch (\Exception $e) {
            Log::error("{$uploadId} [R2-Upload] FAILED", [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'disk' => $diskName,
                'path' => $path,
                'file_size' => $file->getSize(),
                'exception_class' => get_class($e),
                'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 5),
            ]);
            throw $e;
        }
    }

    /**
     * Get public URL for stored file
     */
    public function getPublicUrl(string $path): string
    {
        $diskName = $this->getDiskName();

        Log::debug('[R2-Upload] getPublicUrl called', [
            'disk' => $diskName,
            'path' => $path,
        ]);

        if ($diskName === 'r2') {
            // For R2, use the configured public URL
            $baseUrl = rtrim(env('R2_URL', ''), '/');
            $url = "{$baseUrl}/{$path}";

            Log::debug('[R2-Upload] R2 public URL generated', [
                'base_url' => $baseUrl,
                'final_url' => $url,
            ]);

            return $url;
        }

        // For local storage, use Laravel's url() helper
        $url = url("storage/{$path}");
        Log::debug('[R2-Upload] Local storage URL generated', ['url' => $url]);

        return $url;
    }

    /**
     * Delete media file from storage
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
