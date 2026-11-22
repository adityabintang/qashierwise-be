<?php

namespace App\Helpers;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use DateTimeZone;

class TimezoneDisplayHelper
{
    public static function formatWithLabel(mixed $dateTime, string $format = 'd M Y H:i'): string
    {
        $normalizedDateTime = self::normalizeDateTime($dateTime);

        if ($normalizedDateTime === null) {
            return '—';
        }

        ['timezone' => $timezone, 'label' => $label] = self::resolveDisplayTimezone();

        return $normalizedDateTime->copy()->timezone($timezone)->format($format).' '.$label;
    }

    public static function formatHtml(mixed $dateTime, string $format = 'd M Y H:i'): string
    {
        return e(self::formatWithLabel($dateTime, $format));
    }

    /**
     * @return array{timezone: string, label: string}
     */
    public static function resolveDisplayTimezone(): array
    {
        $timezone = self::resolveViewerTimezone();

        $indonesiaTimezoneMap = [
            'Asia/Jakarta' => 'WIB',
            'Asia/Pontianak' => 'WIB',
            'Asia/Makassar' => 'WITA',
            'Asia/Jayapura' => 'WIT',
        ];

        if (isset($indonesiaTimezoneMap[$timezone])) {
            return [
                'timezone' => $timezone,
                'label' => $indonesiaTimezoneMap[$timezone],
            ];
        }

        return [
            'timezone' => 'UTC',
            'label' => 'UTC',
        ];
    }

    private static function resolveViewerTimezone(): string
    {
        $request = request();

        $cookieTimezone = $request?->cookie('viewer_timezone');

        if (is_string($cookieTimezone) && self::isValidTimezoneIdentifier($cookieTimezone)) {
            return $cookieTimezone;
        }

        $cloudflareTimezone = $request?->header('CF-Timezone');

        if (is_string($cloudflareTimezone) && self::isValidTimezoneIdentifier($cloudflareTimezone)) {
            return $cloudflareTimezone;
        }

        if (self::isIndonesiaContext()) {
            return 'Asia/Jakarta';
        }

        return 'UTC';
    }

    private static function isIndonesiaContext(): bool
    {
        if (app()->getLocale() === 'id') {
            return true;
        }

        if (auth()->check() && auth()->user()?->language_preference === 'id') {
            return true;
        }

        $acceptLanguage = request()?->header('Accept-Language');

        if (is_string($acceptLanguage) && str_starts_with(strtolower($acceptLanguage), 'id')) {
            return true;
        }

        return false;
    }

    private static function normalizeDateTime(mixed $dateTime): ?CarbonInterface
    {
        if ($dateTime instanceof CarbonInterface) {
            return $dateTime;
        }

        if ($dateTime instanceof DateTimeInterface) {
            return Carbon::instance($dateTime);
        }

        if (is_string($dateTime) && $dateTime !== '') {
            return Carbon::parse($dateTime);
        }

        return null;
    }

    private static function isValidTimezoneIdentifier(string $timezone): bool
    {
        return in_array($timezone, DateTimeZone::listIdentifiers(), true);
    }
}
