<?php

namespace Tests\Unit;

use App\Helpers\TimezoneDisplayHelper;
use Carbon\Carbon;
use Tests\TestCase;

class TimezoneDisplayHelperTest extends TestCase
{
    public function test_it_formats_wib_for_indonesian_locale_by_default(): void
    {
        app()->setLocale('id');

        $formatted = TimezoneDisplayHelper::formatWithLabel(
            Carbon::parse('2026-03-25 02:39:00', 'UTC')
        );

        $this->assertSame('25 Mar 2026 09:39 WIB', $formatted);
    }

    public function test_it_formats_utc_for_non_indonesia_locale(): void
    {
        app()->setLocale('en');

        $formatted = TimezoneDisplayHelper::formatWithLabel(
            Carbon::parse('2026-03-25 02:39:00', 'UTC')
        );

        $this->assertSame('25 Mar 2026 02:39 UTC', $formatted);
    }

    public function test_it_uses_cookie_timezone_for_indonesia_wita(): void
    {
        app()->setLocale('en');

        request()->cookies->set('viewer_timezone', 'Asia/Makassar');

        $formatted = TimezoneDisplayHelper::formatWithLabel(
            Carbon::parse('2026-03-25 02:39:00', 'UTC')
        );

        $this->assertSame('25 Mar 2026 10:39 WITA', $formatted);
    }

    public function test_it_uses_cookie_timezone_for_indonesia_wit(): void
    {
        app()->setLocale('en');

        request()->cookies->set('viewer_timezone', 'Asia/Jayapura');

        $formatted = TimezoneDisplayHelper::formatWithLabel(
            Carbon::parse('2026-03-25 02:39:00', 'UTC')
        );

        $this->assertSame('25 Mar 2026 11:39 WIT', $formatted);
    }

    public function test_it_falls_back_to_utc_label_for_non_indonesia_cookie_timezone(): void
    {
        app()->setLocale('en');

        request()->cookies->set('viewer_timezone', 'Europe/Berlin');

        $formatted = TimezoneDisplayHelper::formatWithLabel(
            Carbon::parse('2026-03-25 02:39:00', 'UTC')
        );

        $this->assertSame('25 Mar 2026 02:39 UTC', $formatted);
    }
}
