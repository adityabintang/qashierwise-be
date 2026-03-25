<?php

namespace App\Helpers;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;

class TimezoneDisplayHelper
{
    /**
     * @return array<string, string>
     */
    public static function getDisplayTimezones(): array
    {
        if (self::isIndonesiaContext()) {
            return [
                'WIB' => 'Asia/Jakarta',
                'WITA' => 'Asia/Makassar',
                'WIT' => 'Asia/Jayapura',
            ];
        }

        return [
            'UTC' => 'UTC',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function formatForDisplay(mixed $dateTime, string $format = 'd M Y H:i'): array
    {
        $normalizedDateTime = self::normalizeDateTime($dateTime);

        if ($normalizedDateTime === null) {
            return [];
        }

        $formatted = [];

        foreach (self::getDisplayTimezones() as $label => $timezone) {
            $formatted[$label] = $normalizedDateTime->copy()->timezone($timezone)->format($format);
        }

        return $formatted;
    }

    public static function formatHtml(mixed $dateTime, string $format = 'd M Y H:i'): string
    {
        $formatted = self::formatForDisplay($dateTime, $format);

        if ($formatted === []) {
            return '—';
        }

        return collect($formatted)
            ->map(fn (string $value, string $label): string => "{$label}: {$value}")
            ->implode('<br>');
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
}
