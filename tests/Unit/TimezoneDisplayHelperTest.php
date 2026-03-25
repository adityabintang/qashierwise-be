<?php

namespace Tests\Unit;

use App\Helpers\TimezoneDisplayHelper;
use Carbon\Carbon;
use Tests\TestCase;

class TimezoneDisplayHelperTest extends TestCase
{
    public function test_it_returns_indonesia_timezones_for_id_locale(): void
    {
        app()->setLocale('id');

        $formatted = TimezoneDisplayHelper::formatForDisplay(
            Carbon::parse('2026-03-25 02:39:00', 'UTC')
        );

        $this->assertSame('25 Mar 2026 09:39', $formatted['WIB']);
        $this->assertSame('25 Mar 2026 10:39', $formatted['WITA']);
        $this->assertSame('25 Mar 2026 11:39', $formatted['WIT']);
    }

    public function test_it_returns_utc_for_non_indonesia_locale(): void
    {
        app()->setLocale('en');

        $formatted = TimezoneDisplayHelper::formatForDisplay(
            Carbon::parse('2026-03-25 02:39:00', 'UTC')
        );

        $this->assertArrayHasKey('UTC', $formatted);
        $this->assertSame('25 Mar 2026 02:39', $formatted['UTC']);
        $this->assertArrayNotHasKey('WIB', $formatted);
    }
}
