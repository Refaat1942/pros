<?php

namespace Tests\Feature\Spec;

use App\Models\CaseRecord;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordItem;
use App\Models\TechOrderSpec;
use App\Models\TechOrderSpecItem;
use App\Services\SpecService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class SpecPrintTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_spec_user_can_print_submitted_spec_report(): void
    {
        $this->stockItem('RM-PRINT', qty: 10);

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $doctor  = $this->userWithRole('doctor');
        $specUser = $this->userWithRole('spec');

        $this->actingAs($doctor);

        $record = MedicalRecord::create([
            'patient_id'   => $patient->id,
            'patient_name' => $patient->name,
            'patient_type' => $patient->patient_type,
            'diagnosis'    => 'بتر',
            'doctor_name'  => $doctor->name,
            'record_date'  => now()->toDateString(),
            'status'       => MedicalRecord::STATUS_DRAFT,
            'locked'       => false,
        ]);

        MedicalRecordItem::create([
            'medical_record_id' => $record->id,
            'stock_item_code'   => 'RM-PRINT',
            'name'              => 'صنف للطباعة',
            'qty'               => 2,
        ]);

        app(\App\Services\MedicalRecordService::class)->lock($record);

        $case = CaseRecord::where('patient_id', $patient->id)->firstOrFail();

        $draft = TechOrderSpec::create([
            'order_ref'    => $case->order_ref,
            'case_id'      => $case->id,
            'patient_name' => $patient->name,
            'company_name' => $case->company_name,
            'doctor_name'  => $doctor->name,
            'tech_notes'   => 'ملاحظات طباعة',
            'locked'       => false,
        ]);

        TechOrderSpecItem::create([
            'tech_order_spec_id' => $draft->id,
            'stock_item_code'    => 'RM-PRINT',
            'name'               => 'صنف للطباعة',
            'qty'                => 2,
        ]);

        app(SpecService::class)->submit($draft->fresh('items'));

        $this->actingAs($specUser)
            ->get(route('spec.spec.print', $draft))
            ->assertOk()
            ->assertSee('تقرير التوصيف الفني', false)
            ->assertSee('org-logo.png', false)
            ->assertSee('RM-PRINT', false)
            ->assertSee('ملاحظات طباعة', false)
            ->assertSee('onload="window.print()"', false);
    }

    public function test_draft_spec_cannot_be_printed(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_TECHNICAL);

        $draft = TechOrderSpec::create([
            'order_ref'    => $case->order_ref,
            'case_id'      => $case->id,
            'patient_name' => $patient->name,
            'locked'       => false,
        ]);

        $this->actingAs($this->userWithRole('spec'))
            ->get(route('spec.spec.print', $draft))
            ->assertForbidden();
    }
}
