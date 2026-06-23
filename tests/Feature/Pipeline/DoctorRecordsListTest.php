<?php

namespace Tests\Feature\Pipeline;

use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Models\MilitaryRank;
use App\Models\Patient;
use Tests\Support\DashboardQueueAssertions;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class DoctorRecordsListTest extends TestCase
{
    use DashboardQueueAssertions;
    use ProstheticTestHelper;

    public function test_locked_diagnosis_appears_on_records_page_and_api(): void
    {
        $company = $this->civilianCompany();
        $recep   = $this->userWithRole('reception');
        $doctor  = $this->userWithRole('doctor');

        $patient = $this->registerCivilianPatientHttp($recep, $company, 'مريض السجل الطبي');
        $this->transferPatientToClinicHttp($recep, $patient);

        $appointmentId = Appointment::where('patient_id', $patient->id)->value('id');

        $this->actingAs($doctor)->postJson('/doctor/diagnosis', [
            'patient_id'     => $patient->id,
            'appointment_id' => $appointmentId,
            'diagnosis'      => 'تشخيص يظهر في السجل الطبي',
            'lock'           => true,
        ])->assertCreated();

        $this->assertDatabaseHas('medical_records', [
            'patient_id' => $patient->id,
            'diagnosis'  => 'تشخيص يظهر في السجل الطبي',
            'locked'     => true,
        ]);

        $recordsPage = $this->actingAs($doctor)->get('/doctor/records');
        $recordsPage->assertOk()
            ->assertSee('مريض السجل الطبي', false)
            ->assertSee('تشخيص يظهر في السجل الطبي', false)
            ->assertViewHas('medical_records', function ($records) use ($patient) {
                return $records->contains(fn ($record) => $record['patient_name'] === $patient->name
                    && $record['diagnosis'] === 'تشخيص يظهر في السجل الطبي');
            });

        $this->actingAs($doctor)->getJson('/doctor/records/list')
            ->assertOk()
            ->assertJsonFragment([
                'patient_name' => $patient->name,
                'diagnosis'    => 'تشخيص يظهر في السجل الطبي',
                'locked'       => true,
            ]);
    }

    public function test_draft_diagnosis_is_excluded_from_records_list(): void
    {
        $company = $this->civilianCompany();
        $doctor  = $this->userWithRole('doctor');
        $patient = $this->civilianPatient($company);

        MedicalRecord::create([
            'patient_id'   => $patient->id,
            'patient_name' => $patient->name,
            'patient_type' => $patient->patient_type,
            'diagnosis'    => 'مسودة غير معتمدة',
            'doctor_name'  => $doctor->name,
            'record_date'  => now()->toDateString(),
            'status'       => MedicalRecord::STATUS_DRAFT,
            'locked'       => false,
        ]);

        $this->actingAs($doctor)->getJson('/doctor/records/list')
            ->assertOk()
            ->assertJsonMissing(['diagnosis' => 'مسودة غير معتمدة']);
    }

    public function test_military_locked_record_includes_display_entity(): void
    {
        $recep   = $this->userWithRole('reception');
        $doctor  = $this->userWithRole('doctor');
        $company = $this->militaryCompany();
        $rank    = MilitaryRank::create(['name' => 'نقيب', 'rank_code' => 'CAP', 'sort_order' => 1]);
        $patient = $this->registerMilitaryPatientHttp($recep, $company, $rank, 'مريض عسكري السجل');

        $this->transferPatientToClinicHttp($recep, $patient);

        $appointmentId = Appointment::where('patient_id', $patient->id)->value('id');

        $this->actingAs($doctor)->postJson('/doctor/diagnosis', [
            'patient_id'     => $patient->id,
            'appointment_id' => $appointmentId,
            'diagnosis'      => 'تشخيص عسكري',
            'lock'           => true,
        ])->assertCreated();

        $this->actingAs($doctor)->getJson('/doctor/records/list')
            ->assertOk()
            ->assertJsonFragment([
                'patient_name'   => 'مريض عسكري السجل',
                'patient_type'   => 'military',
                'display_entity' => Patient::MILITARY_SOVEREIGN_ENTITY,
            ]);
    }
}
