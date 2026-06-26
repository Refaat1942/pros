<?php

namespace Tests\Feature\Reports;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\VisitType;
use App\Services\AdminVisitLeaderboardService;
use App\Services\BiReportService;
use Database\Seeders\RolesAndAdminSeeder;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class AdminOverviewVisitLeaderboardTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_visit_leaderboard_groups_top_patients_by_visit_type(): void
    {
        $exam = VisitType::create(['name' => 'كشف']);
        $follow = VisitType::create(['name' => 'متابعة']);

        $company = $this->civilianCompany();
        $alice = Patient::create([
            'patient_code'        => '100101',
            'patient_qr'          => 'QR-100101',
            'tracking_uid'        => 'case-test0101',
            'name'                => 'أحمد علي',
            'phone'               => '01011111111',
            'national_id'         => '29901010100101',
            'patient_type'        => Patient::TYPE_CIVILIAN,
            'contract_company_id' => $company->id,
            'company_name'        => $company->name,
            'registered_at'       => now()->toDateString(),
            'status'              => Patient::STATUS_ACTIVE,
        ]);
        $bob = Patient::create([
            'patient_code'        => '100102',
            'patient_qr'          => 'QR-100102',
            'tracking_uid'        => 'case-test0102',
            'name'                => 'سارة محمد',
            'phone'               => '01022222222',
            'national_id'         => '29901010100102',
            'patient_type'        => Patient::TYPE_CIVILIAN,
            'contract_company_id' => $company->id,
            'company_name'        => $company->name,
            'registered_at'       => now()->toDateString(),
            'status'              => Patient::STATUS_ACTIVE,
        ]);

        foreach (range(1, 3) as $i) {
            Appointment::create([
                'patient_id'        => $alice->id,
                'visit_type_id'     => $exam->id,
                'visit_type'        => 'exam',
                'patient_name'      => $alice->name,
                'phone'             => $alice->phone,
                'patient_type'      => Patient::TYPE_CIVILIAN,
                'appointment_date'  => now()->toDateString(),
                'appointment_time'  => '10:00',
                'status'            => Appointment::STATUS_DONE,
            ]);
        }

        Appointment::create([
            'patient_id'        => $bob->id,
            'visit_type_id'     => $exam->id,
            'visit_type'        => 'exam',
            'patient_name'      => $bob->name,
            'phone'             => $bob->phone,
            'patient_type'      => Patient::TYPE_CIVILIAN,
            'appointment_date'  => now()->toDateString(),
            'appointment_time'  => '11:00',
            'status'            => Appointment::STATUS_DONE,
        ]);

        Appointment::create([
            'patient_id'        => $alice->id,
            'visit_type_id'     => $follow->id,
            'visit_type'        => 'followup',
            'patient_name'      => $alice->name,
            'phone'             => $alice->phone,
            'patient_type'      => Patient::TYPE_CIVILIAN,
            'appointment_date'  => now()->toDateString(),
            'appointment_time'  => '12:00',
            'status'            => Appointment::STATUS_DONE,
        ]);

        $boards = app(AdminVisitLeaderboardService::class)->topPatientsByVisitType();

        $examBoard = collect($boards)->firstWhere('visit_type', 'كشف');
        $this->assertNotNull($examBoard);
        $this->assertSame(4, $examBoard['total_visits']);
        $this->assertSame('أحمد علي', $examBoard['patients'][0]['name']);
        $this->assertSame(3, $examBoard['patients'][0]['visit_count']);
    }

    public function test_overview_page_shows_visit_leaderboard_panel(): void
    {
        $bi = \Mockery::mock(BiReportService::class);
        $bi->shouldReceive('boardPatients')->once()->andReturn([
            'open_count' => 0, 'sla_breached' => 0, 'sla_breached_cases' => [],
        ]);
        $bi->shouldReceive('boardInventory')->once()->andReturn([
            'item_count' => 0, 'low_stock' => 0,
        ]);
        $this->app->instance(BiReportService::class, $bi);

        $this->seed(RolesAndAdminSeeder::class);
        $admin = $this->userWithRole('admin');

        $exam = VisitType::create(['name' => 'كشف']);
        $patient = $this->civilianPatient($this->civilianCompany());
        Appointment::create([
            'patient_id'        => $patient->id,
            'visit_type_id'     => $exam->id,
            'visit_type'        => 'exam',
            'patient_name'      => $patient->name,
            'phone'             => $patient->phone,
            'patient_type'      => Patient::TYPE_CIVILIAN,
            'appointment_date'  => now()->toDateString(),
            'appointment_time'  => '09:00',
            'status'            => Appointment::STATUS_DONE,
        ]);

        $this->actingAs($admin)
            ->get('/admin/overview')
            ->assertOk()
            ->assertSee('أكثر المرضى زيارة')
            ->assertSee('كشف')
            ->assertSee($patient->name);
    }
}
