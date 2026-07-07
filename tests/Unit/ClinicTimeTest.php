<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Support\ClinicTime;
use Carbon\Carbon;
use Tests\TestCase;

class ClinicTimeTest extends TestCase
{
    public function test_today_date_string_uses_clinic_calendar_not_utc(): void
    {
        config(['app.timezone' => 'UTC', 'app.clinic_timezone' => 'Africa/Cairo']);

        // ٢٧ يونيو ٠٠:١١ بتوقيت القاهرة = ٢٦ يونيو ٢١:١١ UTC
        Carbon::setTestNow('2026-06-26 21:11:00');

        $this->assertSame('2026-06-27', ClinicTime::todayDateString());

        Carbon::setTestNow();
    }

    public function test_formats_utc_timestamp_in_clinic_timezone(): void
    {
        config(['app.timezone' => 'UTC', 'app.clinic_timezone' => 'Africa/Cairo']);

        $utc = Carbon::parse('2026-06-26 09:23:00', 'UTC');

        $this->assertSame(
            $utc->copy()->timezone('Africa/Cairo')->format('d/m/Y H:i'),
            ClinicTime::format($utc)
        );
    }

    public function test_parse_date_range_returns_null_when_dates_omitted(): void
    {
        config(['app.timezone' => 'UTC', 'app.clinic_timezone' => 'Africa/Cairo']);

        $range = ClinicTime::parseDateRange(null, null);

        $this->assertNull($range['from']);
        $this->assertNull($range['to']);
    }

    public function test_parse_date_range_uses_clinic_calendar_not_utc(): void
    {
        config(['app.timezone' => 'UTC', 'app.clinic_timezone' => 'Africa/Cairo']);

        $range = ClinicTime::parseDateRange('2026-07-01', '2026-07-03');

        $this->assertSame('2026-07-01', $range['from']->toDateString());
        $this->assertSame('2026-07-03', $range['to']->toDateString());
        $this->assertSame(
            '01/07/2026 — 03/07/2026',
            ClinicTime::format($range['from'], 'd/m/Y').' — '.ClinicTime::format($range['to'], 'd/m/Y'),
        );
    }

    public function test_parse_date_range_swaps_inverted_bounds(): void
    {
        config(['app.timezone' => 'UTC', 'app.clinic_timezone' => 'Africa/Cairo']);

        $range = ClinicTime::parseDateRange('2026-07-10', '2026-07-01');

        $this->assertSame('2026-07-01', $range['from']->toDateString());
        $this->assertSame('2026-07-10', $range['to']->toDateString());
    }

    public function test_appointment_transfer_time_uses_clinic_timezone(): void
    {
        config(['app.timezone' => 'UTC', 'app.clinic_timezone' => 'Africa/Cairo']);

        $appointment = new Appointment([
            'transferred_to_clinic' => true,
            'transferred_to_clinic_at' => '2026-06-26 09:23:00',
        ]);

        $formatted = $appointment->transferredAtFormatted();

        $this->assertStringContainsString('26/06/2026', $formatted);
        $this->assertStringContainsString(
            Carbon::parse('2026-06-26 09:23:00', 'UTC')->timezone('Africa/Cairo')->format('H:i'),
            $formatted
        );
        $this->assertNotSame('09:23', substr($formatted, -5));
    }
}
