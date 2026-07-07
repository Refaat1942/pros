<?php

namespace Tests\Feature\Pipeline;

use App\Models\Appointment;
use App\Models\CaseRecord;
use App\Models\MedicalRecord;
use App\Models\TechOrderSpec;
use App\Models\TechOrderSpecItem;
use App\Services\DoctorTransferService;
use Tests\Support\DashboardQueueAssertions;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class DoctorTransferListTest extends TestCase
{
    use DashboardQueueAssertions;
    use ProstheticTestHelper;

    public function test_approved_diagnosis_appears_on_transfer_page_and_api(): void
    {
        $company = $this->civilianCompany();
        $recep = $this->userWithRole('reception');
        $doctor = $this->userWithRole('doctor');

        $patient = $this->registerCivilianPatientHttp($recep, $company, 'مريض التحويل للمخزون');
        $this->transferPatientToClinicHttp($recep, $patient);

        $appointmentId = Appointment::where('patient_id', $patient->id)->value('id');

        $this->actingAs($doctor)->postJson('/doctor/diagnosis', [
            'patient_id' => $patient->id,
            'appointment_id' => $appointmentId,
            'diagnosis' => 'تشخيص يظهر في صفحة التحويل',
            'lock' => true,
        ])->assertCreated();

        $case = CaseRecord::where('patient_id', $patient->id)->first();
        $this->assertNotNull($case);
        $this->assertNotSame(CaseRecord::STAGE_EXAM, $case->stage_key);

        $transferPage = $this->actingAs($doctor)->get('/doctor/transfer');
        $transferPage->assertOk()
            ->assertSee('مريض التحويل للمخزون', false)
            ->assertViewHas('transferred_cases', function ($cases) use ($patient) {
                return $cases->contains(fn ($row) => $row['name'] === $patient->name);
            });

        $this->actingAs($doctor)->getJson('/doctor/transfer/list')
            ->assertOk()
            ->assertJsonFragment([
                'name' => $patient->name,
            ]);
    }

    public function test_transfer_list_shows_spec_items_when_medical_record_has_none(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_CASTING);

        MedicalRecord::create([
            'patient_id' => $patient->id,
            'case_id' => $case->id,
            'patient_name' => $patient->name,
            'national_id' => $patient->national_id,
            'company_name' => $patient->company_name,
            'patient_type' => $patient->patient_type,
            'diagnosis' => 'تشخيص بدون أصناف في التقرير',
            'doctor_name' => 'د. تجريبي',
            'record_date' => now()->toDateString(),
            'status' => MedicalRecord::STATUS_APPROVED,
            'locked' => true,
        ]);

        $spec = TechOrderSpec::create([
            'order_ref' => $case->order_ref,
            'case_id' => $case->id,
            'patient_name' => $patient->name,
            'locked' => true,
            'submitted_at' => now(),
        ]);

        TechOrderSpecItem::create([
            'tech_order_spec_id' => $spec->id,
            'stock_item_code' => 'RM-001',
            'name' => 'ركينة PTB',
            'qty' => 1,
        ]);

        $rows = app(DoctorTransferService::class)->list();

        $row = $rows->firstWhere('name', $patient->name);
        $this->assertNotNull($row);
        $this->assertCount(1, $row['recommendations']);
        $this->assertSame('ركينة PTB', $row['recommendations'][0]['name']);
        $this->assertSame('RM-001', $row['recommendations'][0]['code']);
    }
}
