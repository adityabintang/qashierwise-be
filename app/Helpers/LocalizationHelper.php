<?php

namespace App\Helpers;

use Carbon\Carbon;

class LocalizationHelper
{
    /**
     * Get translated string with parameters
     */
    public static function trans(string $key, array $params = []): string
    {
        return __($key, $params);
    }

    /**
     * Get current locale
     */
    public static function getCurrentLocale(): string
    {
        return app()->getLocale();
    }

    /**
     * Check if locale is supported
     */
    public static function isLocaleSupported(string $locale): bool
    {
        return in_array($locale, config('app.supported_locales', ['en', 'id']));
    }

    /**
     * Get all supported locales
     */
    public static function getSupportedLocales(): array
    {
        return config('app.supported_locales', ['en', 'id']);
    }

    /**
     * Format date according to locale
     *
     * @param  mixed  $date
     */
    public static function formatDate($date, string $format = 'medium'): string
    {
        $carbon = Carbon::parse($date)->locale(app()->getLocale());

        // Map format strings to Carbon format patterns
        $formats = [
            'short' => 'd/m/Y',
            'medium' => 'd M Y',
            'long' => 'd F Y',
            'full' => 'l, d F Y',
            'datetime' => 'd M Y H:i',
            'time' => 'H:i',
        ];

        if (isset($formats[$format])) {
            return $carbon->translatedFormat($formats[$format]);
        }

        // If custom format provided, use it directly
        return $carbon->translatedFormat($format);
    }

    /**
     * Format currency (Indonesian Rupiah)
     *
     * @param  int|float  $amount
     */
    public static function formatCurrency($amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    /**
     * Get locale code for Open Graph
     */
    public static function getOgLocale(?string $locale = null): string
    {
        $locale = $locale ?? self::getCurrentLocale();

        $ogLocales = [
            'en' => 'en_US',
            'id' => 'id_ID',
        ];

        return $ogLocales[$locale] ?? 'en_US';
    }

    /**
     * Get language name in native format
     */
    public static function getLanguageName(?string $locale = null): string
    {
        $locale = $locale ?? self::getCurrentLocale();

        $names = [
            'en' => 'English',
            'id' => 'Bahasa Indonesia',
        ];

        return $names[$locale] ?? 'English';
    }

    /**
     * Get alternate language URLs for hreflang
     */
    public static function getAlternateUrls(?string $path = null): array
    {
        $baseUrl = config('app.url');
        $currentPath = $path ?? request()->path();
        $currentPath = ltrim($currentPath, '/');

        $urls = [];
        foreach (self::getSupportedLocales() as $locale) {
            $url = empty($currentPath) || $currentPath === '/'
                ? $baseUrl
                : "{$baseUrl}/{$currentPath}";

            $urls[$locale] = $url;
        }

        return $urls;
    }
}
