<?php

namespace Tests\Feature\Pipeline;

use App\Models\Appointment;
use App\Models\CaseRecord;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Services\CaseService;
use App\Services\MedicalRecordService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class DoctorCaseInitiationTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_case_numbers_increment_after_legacy_three_digit_seed_format(): void
    {
        $year = now()->year;
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);

        CaseRecord::create([
            'case_no'             => "CASE-{$year}-011",
            'order_ref'           => "ORD-{$year}-011",
            'patient_id'          => $patient->id,
            'contract_company_id' => $company->id,
            'company_name'        => $company->name,
            'patient_type'        => Patient::TYPE_CIVILIAN,
            'path'                => CaseRecord::PATH_STANDARD,
            'stage_key'           => CaseRecord::STAGE_RECEPTION,
        ]);

        CaseRecord::create([
            'case_no'             => "CASE-{$year}-0012",
            'order_ref'           => "ORD-{$year}-0012",
            'patient_id'          => $patient->id,
            'contract_company_id' => $company->id,
            'company_name'        => $company->name,
            'patient_type'        => Patient::TYPE_CIVILIAN,
            'path'                => CaseRecord::PATH_STANDARD,
            'stage_key'           => CaseRecord::STAGE_RECEPTION,
        ]);

        $record = MedicalRecord::create([
            'patient_id'   => $patient->id,
            'patient_name' => $patient->name,
            'patient_type' => $patient->patient_type,
            'diagnosis'    => 'تشخيص تجريبي',
            'doctor_name'  => 'د. اختبار',
            'record_date'  => now()->toDateString(),
            'status'       => MedicalRecord::STATUS_DRAFT,
            'locked'       => false,
        ]);

        $case = app(CaseService::class)->initiate($patient, $record);

        $this->assertSame("CASE-{$year}-0013", $case->case_no);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $case->order_ref);
        $this->assertNotSame("ORD-{$year}-0013", $case->order_ref);
    }

    public function test_lock_reuses_draft_for_same_appointment_instead_of_duplicate_case(): void
    {
        $year = now()->year;
        $company = $this->civilianCompany();
        $visitType = $this->defaultVisitType();
        $doctor = $this->userWithRole('doctor');
        $this->actingAs($doctor);

        CaseRecord::create([
            'case_no'             => "CASE-{$year}-011",
            'order_ref'           => "ORD-{$year}-011",
            'patient_id'          => $this->civilianPatient($company)->id,
            'contract_company_id' => $company->id,
            'company_name'        => $company->name,
            'patient_type'        => Patient::TYPE_CIVILIAN,
            'path'                => CaseRecord::PATH_STANDARD,
            'stage_key'           => CaseRecord::STAGE_RECEPTION,
        ]);

        $patient = Patient::create([
            'patient_code'        => '100099',
            'patient_qr'          => 'QR-100099',
            'tracking_uid'        => 'case-doctest1',
            'name'                => 'مريض اختبار',
            'patient_type'        => Patient::TYPE_CIVILIAN,
            'contract_company_id' => $company->id,
            'company_name'        => $company->name,
            'registered_at'       => now()->toDateString(),
            'status'              => Patient::STATUS_ACTIVE,
        ]);

        $appointment = app(\App\Services\AppointmentService::class)->book([
            'patient_id'       => $patient->id,
            'appointment_date' => now()->toDateString(),
            'visit_type_id'    => $visitType->id,
        ]);

        app(\App\Services\AppointmentService::class)->advanceStatus(
            $appointment,
            Appointment::STATUS_IN_CLINIC
        );

        $service = app(MedicalRecordService::class);

        $first = $service->saveDraft([
            'patient_id'     => $patient->id,
            'appointment_id' => $appointment->id,
            'diagnosis'      => 'تشخيص أول',
        ]);
        $locked = $service->lock($first);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->saveDraft([
            'patient_id'     => $patient->id,
            'appointment_id' => $appointment->id,
            'diagnosis'      => 'تشخيص محاولة ثانية',
        ]);

        $this->assertTrue($locked->fresh()->locked);
        $this->assertSame(1, CaseRecord::where('patient_id', $patient->id)->count());
    }
}
