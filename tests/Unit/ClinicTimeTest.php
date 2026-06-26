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
        \Carbon\Carbon::setTestNow('2026-06-26 21:11:00');

        $this->assertSame('2026-06-27', ClinicTime::todayDateString());

        \Carbon\Carbon::setTestNow();
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

    public function test_appointment_transfer_time_uses_clinic_timezone(): void
    {
        config(['app.timezone' => 'UTC', 'app.clinic_timezone' => 'Africa/Cairo']);

        $appointment = new Appointment([
            'transferred_to_clinic'    => true,
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
