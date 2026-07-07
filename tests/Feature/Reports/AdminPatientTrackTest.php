<?php

namespace Tests\Feature\Reports;

use App\Models\Appointment;
use App\Models\ApprovalContract;
use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\Quote;
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
            ->assertSee('تفاصيل المريض', false)
            ->assertSee('id="patientTrackModal"', false)
            ->assertSee('id="patientTrackModalJourney"', false)
            ->assertSee('id="patientDetailsModal"', false)
            ->assertSee('id="patientTracksData"', false)
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

    public function test_civilian_track_includes_journey_events(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, 'assembly');

        $tracks = app(AdminPatientTrackService::class)->list();
        $track = $tracks->firstWhere('id', $patient->id);

        $this->assertNotNull($track);
        $this->assertArrayHasKey('journey', $track);
        $this->assertNotEmpty($track['journey']);

        $categories = array_column($track['journey'], 'category');
        $this->assertContains('reception', $categories);
        $this->assertContains('manufacturing', $categories);

        $first = $track['journey'][0];
        $this->assertArrayHasKey('at_label', $first);
        $this->assertArrayHasKey('category_label', $first);
        $this->assertArrayHasKey('title', $first);
        $this->assertArrayHasKey('lines', $first);
        $this->assertSame('reception', $first['category']);
        $this->assertStringContainsString('تسجيل المريض', $first['title']);
    }

    public function test_journey_operations_events_include_document_preview_buttons(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING);
        $case->update([
            'quote_no'               => 'QT-2026-0400',
            'approval_date'          => now()->toDateString(),
            'approval_confirmed_at'  => now(),
            'work_order_no'          => 'WO-2026-0400',
            'total_cost'             => 1000.00,
        ]);

        $quote = Quote::create([
            'quote_no'     => 'QT-2026-0400',
            'case_id'      => $case->id,
            'order_ref'    => $case->order_ref,
            'patient_name' => $patient->name,
            'company_name' => $company->name,
            'quote_date'   => now()->toDateString(),
            'status'       => Quote::STATUS_APPROVED,
            'total'        => 1000.00,
        ]);

        \Illuminate\Support\Facades\Storage::disk('public')->put('approval_letters/track-test.png', 'fake-image');

        ApprovalContract::create([
            'contract_no'     => 'CNT-2026-0400',
            'case_id'         => $case->id,
            'quote_id'        => $quote->id,
            'patient_name'    => $patient->name,
            'company_name'    => $company->name,
            'approved_amount' => 1000.00,
            'approval_date'   => now()->toDateString(),
            'work_order_no'   => 'WO-2026-0400',
            'letter_path'     => 'approval_letters/track-test.png',
        ]);

        $tracks = app(AdminPatientTrackService::class)->list();
        $track = $tracks->firstWhere('id', $patient->id);

        $this->assertNotNull($track);

        $opsEvent = collect($track['journey'])->first(
            fn (array $event) => ($event['title'] ?? '') === 'اعتماد مكتب التشغيل'
        );
        $approvalEvent = collect($track['journey'])->first(
            fn (array $event) => ($event['title'] ?? '') === 'موافقة جهة التعاقد / التأمين'
        );

        $this->assertNotNull($opsEvent);
        $this->assertSame('quote', $opsEvent['preview']['type'] ?? null);
        $this->assertStringContainsString('embed=1', $opsEvent['preview']['url'] ?? '');

        $this->assertNotNull($approvalEvent);
        $this->assertSame('approval_letter', $approvalEvent['preview']['type'] ?? null);
        // الوصول للخطاب عبر مسار مُصادَق عليه (وليس رابط /storage عام).
        $this->assertStringEndsWith('/letter', $approvalEvent['preview']['url'] ?? '');
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
            ->assertJsonStructure([
                '0' => [
                    'steps',
                    'stage_label',
                    'progress_percent',
                    'stage_key',
                    'journey' => [
                        '*' => [
                            'at_label',
                            'category',
                            'category_label',
                            'title',
                            'lines',
                        ],
                    ],
                    'patient_details' => [
                        'patient_code',
                        'name',
                        'phone',
                        'national_id',
                        'display_entity',
                        'cases',
                    ],
                ],
            ]);
    }

    public function test_delivered_military_patient_remains_in_patient_tracks(): void
    {
        $patient = $this->militaryPatient($this->militaryCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $case->update(['delivered_at' => now()->toDateString()]);

        $tracks = app(AdminPatientTrackService::class)->list();
        $track = $tracks->firstWhere('id', $patient->id);

        $this->assertNotNull($track, 'المريض العسكري المُسلَّم يجب أن يبقى ظاهراً في مسار المرضى');
        $this->assertSame('military', $track['pathway']);
        $this->assertSame(CaseRecord::STAGE_DELIVERED, $track['stage_key']);
        $this->assertSame('تم التسليم', $track['stage_label']);
    }

    public function test_patient_tracks_filters_by_stage_and_type(): void
    {
        $civilian = $this->civilianPatient($this->civilianCompany());
        $military = $this->militaryPatient($this->militaryCompany());
        $this->caseAtStage($civilian, CaseRecord::STAGE_TECHNICAL);
        $this->caseAtStage($military, CaseRecord::STAGE_MANUFACTURING);

        $service = app(AdminPatientTrackService::class);

        $this->assertNotNull($service->list(stage: CaseRecord::STAGE_TECHNICAL)->firstWhere('id', $civilian->id));
        $this->assertNull($service->list(stage: CaseRecord::STAGE_TECHNICAL)->firstWhere('id', $military->id));

        $this->assertNotNull($service->list(patientType: 'military')->firstWhere('id', $military->id));
        $this->assertNull($service->list(patientType: 'military')->firstWhere('id', $civilian->id));
    }

    public function test_patient_tracks_filters_by_visit_type(): void
    {
        $examVisit = $this->defaultVisitType('كشف أولي');
        $followupVisit = $this->defaultVisitType('متابعة');
        $company = $this->civilianCompany();

        $patientWithExam = Patient::create([
            'patient_code'        => '100101',
            'patient_qr'          => 'QR-100101',
            'tracking_uid'        => 'case-test0101',
            'name'                => 'أحمد حسن',
            'phone'               => '01000000001',
            'national_id'         => '29901010100101',
            'patient_type'        => Patient::TYPE_CIVILIAN,
            'contract_company_id' => $company->id,
            'company_name'        => $company->name,
            'registered_at'       => now()->toDateString(),
            'status'              => Patient::STATUS_ACTIVE,
        ]);

        $patientWithFollowup = Patient::create([
            'patient_code'        => '100102',
            'patient_qr'          => 'QR-100102',
            'tracking_uid'        => 'case-test0102',
            'name'                => 'سارة علي',
            'phone'               => '01000000002',
            'national_id'         => '29901010100102',
            'patient_type'        => Patient::TYPE_CIVILIAN,
            'contract_company_id' => $company->id,
            'company_name'        => $company->name,
            'registered_at'       => now()->toDateString(),
            'status'              => Patient::STATUS_ACTIVE,
        ]);

        Appointment::create([
            'patient_id'        => $patientWithExam->id,
            'appointment_date'  => now()->toDateString(),
            'appointment_time'  => '09:00',
            'visit_type_id'     => $examVisit->id,
            'visit_type'        => $examVisit->name,
            'patient_name'      => $patientWithExam->name,
            'phone'             => $patientWithExam->phone,
            'company_name'      => $patientWithExam->company_name,
            'patient_type'      => $patientWithExam->patient_type,
            'status'            => Appointment::STATUS_WAITING,
        ]);

        Appointment::create([
            'patient_id'        => $patientWithFollowup->id,
            'appointment_date'  => now()->toDateString(),
            'appointment_time'  => '10:00',
            'visit_type_id'     => $followupVisit->id,
            'visit_type'        => $followupVisit->name,
            'patient_name'      => $patientWithFollowup->name,
            'phone'             => $patientWithFollowup->phone,
            'company_name'      => $patientWithFollowup->company_name,
            'patient_type'      => $patientWithFollowup->patient_type,
            'status'            => Appointment::STATUS_IN_CLINIC,
        ]);

        $service = app(AdminPatientTrackService::class);

        $examTracks = $service->list(visitType: (string) $examVisit->id);
        $followupTracks = $service->list(visitType: (string) $followupVisit->id);

        $this->assertNotNull($examTracks->firstWhere('id', $patientWithExam->id));
        $this->assertNull($examTracks->firstWhere('id', $patientWithFollowup->id));

        $this->assertNotNull($followupTracks->firstWhere('id', $patientWithFollowup->id));
        $this->assertNull($followupTracks->firstWhere('id', $patientWithExam->id));

        $this->mockBiBoards();

        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get('/admin/patient-tracks?visit_type=' . $examVisit->id)
            ->assertOk()
            ->assertSee('id="patientTrackVisitFilter"', false)
            ->assertSee($patientWithExam->name, false)
            ->assertDontSee($patientWithFollowup->name, false);
    }

    public function test_patient_tracks_filters_by_visit_type_for_delivered_patients(): void
    {
        $examVisit = $this->defaultVisitType('كشف أولي');
        $patient = $this->civilianPatient($this->civilianCompany());
        $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);

        Appointment::create([
            'patient_id'        => $patient->id,
            'appointment_date'  => now()->subDays(3)->toDateString(),
            'appointment_time'  => '09:00',
            'visit_type_id'     => $examVisit->id,
            'visit_type'        => $examVisit->name,
            'patient_name'      => $patient->name,
            'phone'             => $patient->phone,
            'company_name'      => $patient->company_name,
            'patient_type'      => $patient->patient_type,
            'status'            => Appointment::STATUS_DONE,
        ]);

        $service = app(AdminPatientTrackService::class);

        $track = $service->list(visitType: (string) $examVisit->id)->firstWhere('id', $patient->id);

        $this->assertNotNull($track, 'المريض المُسلَّم يجب أن يظهر عند فلترة نوع زيارته');
        $this->assertSame($examVisit->id, $track['visit_type_id']);
    }
}
