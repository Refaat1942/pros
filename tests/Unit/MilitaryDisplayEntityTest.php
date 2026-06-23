<?php

namespace Tests\Unit;

use App\Models\CaseRecord;
use App\Models\Patient;
use Tests\TestCase;

class MilitaryDisplayEntityTest extends TestCase
{
    public function test_military_patient_display_entity_defaults_to_armed_forces(): void
    {
        $patient = new Patient([
            'patient_type' => Patient::TYPE_MILITARY,
            'sovereign_entity' => null,
        ]);

        $this->assertSame(Patient::MILITARY_SOVEREIGN_ENTITY, $patient->displayEntity());
    }

    public function test_military_case_display_entity_defaults_to_armed_forces(): void
    {
        $case = new CaseRecord([
            'patient_type' => Patient::TYPE_MILITARY,
            'sovereign_entity' => null,
        ]);

        $this->assertSame(Patient::MILITARY_SOVEREIGN_ENTITY, $case->displayEntity());
    }

    public function test_civilian_case_uses_company_name(): void
    {
        $case = new CaseRecord([
            'patient_type' => Patient::TYPE_CIVILIAN,
            'company_name' => 'شركة التأمين',
        ]);

        $this->assertSame('شركة التأمين', $case->displayEntity());
    }

    public function test_military_medical_record_display_entity_defaults_to_armed_forces(): void
    {
        $record = new \App\Models\MedicalRecord([
            'patient_type' => Patient::TYPE_MILITARY,
            'company_name' => null,
        ]);

        $this->assertSame(Patient::MILITARY_SOVEREIGN_ENTITY, $record->displayEntity());
    }
}
