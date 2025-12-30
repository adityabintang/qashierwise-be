<?php

namespace App\Helpers;

use Carbon\Carbon;

class LocalizationHelper
{
    /**
     * Get translated string with parameters
     *
     * @param string $key
     * @param array $params
     * @return string
     */
    public static function trans(string $key, array $params = []): string
    {
        return __($key, $params);
    }

    /**
     * Get current locale
     *
     * @return string
     */
    public static function getCurrentLocale(): string
    {
        return app()->getLocale();
    }

    /**
     * Check if locale is supported
     *
     * @param string $locale
     * @return bool
     */
    public static function isLocaleSupported(string $locale): bool
    {
        return in_array($locale, config('app.supported_locales', ['en', 'id']));
    }

    /**
     * Get all supported locales
     *
     * @return array
     */
    public static function getSupportedLocales(): array
    {
        return config('app.supported_locales', ['en', 'id']);
    }

    /**
     * Format date according to locale
     *
     * @param mixed $date
     * @param string $format
     * @return string
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
     * @param int|float $amount
     * @return string
     */
    public static function formatCurrency($amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    /**
     * Get locale code for Open Graph
     *
     * @param string|null $locale
     * @return string
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
     *
     * @param string|null $locale
     * @return string
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
     *
     * @param string|null $path
     * @return array
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
