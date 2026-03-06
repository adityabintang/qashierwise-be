<?php

/**
 * Custom Router Script for PHP Built-in Server
 *
 * Usage: php -S localhost:8000 -t public server.php
 * Or:    php artisan serve --no-reload (uses this automatically via .env or config)
 *
 * This script adds proper cache headers to static assets
 * which the default PHP built-in server does not provide.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$publicPath = __DIR__ . '/public' . $uri;

// If the file exists as a static asset, serve it with cache headers
if ($uri !== '/' && file_exists($publicPath) && is_file($publicPath)) {
    $extension = strtolower(pathinfo($publicPath, PATHINFO_EXTENSION));

    // Map extensions to MIME types
    $mimeTypes = [
        'css'   => 'text/css',
        'js'    => 'application/javascript',
        'json'  => 'application/json',
        'woff2' => 'font/woff2',
        'woff'  => 'font/woff',
        'ttf'   => 'font/ttf',
        'eot'   => 'application/vnd.ms-fontobject',
        'otf'   => 'font/otf',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'webp'  => 'image/webp',
        'ico'   => 'image/x-icon',
        'webmanifest' => 'application/manifest+json',
    ];

    // Hashed Vite build assets (build/assets/*) — immutable, cache 1 year
    $isViteBuildAsset = str_starts_with($uri, '/build/assets/');

    if (isset($mimeTypes[$extension])) {
        header('Content-Type: ' . $mimeTypes[$extension]);
        header('X-Content-Type-Options: nosniff');

        if ($isViteBuildAsset) {
            // Vite assets have content hash in filename — safe to cache forever
            header('Cache-Control: public, max-age=31536000, immutable');
        } elseif (in_array($extension, ['css', 'js', 'woff2', 'woff', 'ttf', 'eot', 'otf'])) {
            // Other static assets — cache 1 year
            header('Cache-Control: public, max-age=31536000, immutable');
        } elseif (in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'])) {
            // Images — cache 1 month
            header('Cache-Control: public, max-age=2592000');
        } elseif ($extension === 'ico') {
            // Favicon — cache 1 year
            header('Cache-Control: public, max-age=31536000');
        }

        // CORS for fonts
        if (in_array($extension, ['woff2', 'woff', 'ttf', 'eot', 'otf'])) {
            header('Access-Control-Allow-Origin: *');
        }

        readfile($publicPath);
        return true;
    }

    // Let PHP built-in server handle other file types
    return false;
}

// Not a static file — pass to Laravel's index.php
require_once $publicPath = __DIR__ . '/public/index.php';
