<?php

namespace Tests\Unit;

use App\Support\ArabicDate;
use Carbon\Carbon;
use Tests\TestCase;

class ArabicDateTest extends TestCase
{
    public function test_relative_uses_clinic_timezone_not_utc(): void
    {
        config(['app.timezone' => 'UTC', 'app.clinic_timezone' => 'Africa/Cairo']);

        Carbon::setTestNow(Carbon::parse('2026-06-26 11:22:00', 'Africa/Cairo'));

        $storedUtc = Carbon::parse('2026-06-26 08:19:00', 'UTC'); // 11:19 Cairo

        $label = ArabicDate::relative($storedUtc);

        $this->assertStringContainsString('3 دقائق', $label);
        $this->assertStringContainsString('11:19', $label);
        $this->assertStringNotContainsString('08:19', $label);

        Carbon::setTestNow();
    }

    public function test_relative_shows_less_than_minute_in_clinic_time(): void
    {
        config(['app.timezone' => 'UTC', 'app.clinic_timezone' => 'Africa/Cairo']);

        Carbon::setTestNow(Carbon::parse('2026-06-26 14:22:30', 'Africa/Cairo'));

        $storedUtc = Carbon::parse('2026-06-26 11:22:15', 'UTC');

        $label = ArabicDate::relative($storedUtc);

        $this->assertStringContainsString('قبل', $label);
        $this->assertStringContainsString('2:22', $label);

        Carbon::setTestNow();
    }
}
