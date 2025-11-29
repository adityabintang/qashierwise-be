<?php

namespace Tests\Unit\Services;

use App\Services\MediaStorageService;
use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for MediaStorageService
 * 
 * Feature: r2-media-storage
 */
class MediaStorageServicePropertyTest extends TestCase
{
    use TestTrait;

    private MediaStorageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MediaStorageService();
    }

    /**
     * Feature: r2-media-storage, Property 2: Filename Sanitization
     * Validates: Requirements 6.2
     * 
     * For any input filename containing special characters, the sanitized output 
     * SHALL only contain alphanumeric characters, dots, underscores, and hyphens.
     */
    #[Test]
    public function sanitized_filename_contains_only_allowed_characters(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::string()
            )
            ->then(function (string $filename) {
                $sanitized = $this->service->sanitizeFilename($filename);
                
                // Property: sanitized filename should only contain allowed characters
                $this->assertMatchesRegularExpression(
                    '/^[a-zA-Z0-9._-]*$/',
                    $sanitized,
                    "Sanitized filename '{$sanitized}' contains invalid characters"
                );
                
                // Property: sanitized filename should never be empty (falls back to 'file')
                $this->assertNotEmpty(
                    $sanitized,
                    "Sanitized filename should never be empty"
                );
            });
    }
}
