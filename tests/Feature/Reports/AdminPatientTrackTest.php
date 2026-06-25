<?php

namespace Tests\Feature\Reports;

use App\Models\CaseRecord;
use App\Services\AdminPatientTrackService;
use App\Services\BiReportService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class AdminPatientTrackTest extends TestCase
{
    use ProstheticTestHelper;

    private function mockBiBoards(): void
    {
        $mock = $this->mock(BiReportService::class);
        $mock->shouldReceive('boardPatients')->andReturn([
            'open_count'         => 0,
            'sla_breached'       => 0,
            'sla_breached_cases' => [],
        ]);
        $mock->shouldReceive('boardInventory')->andReturn([
            'item_count' => 0,
            'low_stock'  => 0,
        ]);
    }

    public function test_overview_renders_patient_track_table_with_view_path_button(): void
    {
        $this->mockBiBoards();

        $patient = $this->civilianPatient($this->civilianCompany());
        $this->caseAtStage($patient, CaseRecord::STAGE_TECHNICAL);

        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get('/admin/patient-tracks')
            ->assertOk()
            ->assertSee('id="patientTrackTableBody"', false)
            ->assertSee('عرض المسار', false)
            ->assertSee('id="patientTrackModal"', false)
            ->assertSee($patient->name, false);
    }

    public function test_civilian_track_has_seven_steps(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());
        $this->caseAtStage($patient, CaseRecord::STAGE_TECHNICAL);

        $tracks = app(AdminPatientTrackService::class)->list();
        $track = $tracks->firstWhere('id', $patient->id);

        $this->assertNotNull($track);
        $this->assertSame('civilian', $track['pathway']);
        $this->assertCount(7, $track['steps']);
        $this->assertSame('التسعير واعتماد التشغيل', $track['steps'][3]['label']);
    }

    public function test_military_track_has_six_steps_without_approval(): void
    {
        $patient = $this->militaryPatient($this->militaryCompany());
        $this->caseAtStage($patient, CaseRecord::STAGE_TECHNICAL);

        $tracks = app(AdminPatientTrackService::class)->list();
        $track = $tracks->firstWhere('id', $patient->id);

        $this->assertNotNull($track);
        $this->assertSame('military', $track['pathway']);
        $this->assertCount(6, $track['steps']);
        $this->assertSame('التصنيع بالورشة', $track['steps'][3]['label']);
    }

    public function test_patient_tracks_api_returns_track_payload(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());
        $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->getJson('/admin/patient-tracks/list')
            ->assertOk()
            ->assertJsonPath('0.id', $patient->id)
            ->assertJsonPath('0.pathway', 'civilian')
            ->assertJsonStructure(['0' => ['steps', 'stage_label', 'progress_percent']]);
    }
}
