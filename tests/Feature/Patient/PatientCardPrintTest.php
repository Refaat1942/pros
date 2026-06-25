<?php

namespace Tests\Feature\Patient;

use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class PatientCardPrintTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_reception_can_print_patient_card_label(): void
    {
        $user = $this->userWithRole('reception');
        $patient = $this->militaryPatient($this->militaryCompany());

        $response = $this->actingAs($user)->get(route('reception.patients.card.print', $patient));

        $response->assertOk();
        $response->assertSee('مركز الأطراف الصناعية', false);
        $response->assertSee($patient->patient_code, false);
        $response->assertSee('عسكري', false);
        $response->assertSee('امسح الكود لمتابعة', false);
    }

    public function test_patient_show_includes_card_print_url(): void
    {
        $user = $this->userWithRole('reception');
        $patient = $this->civilianPatient($this->civilianCompany());

        $response = $this->actingAs($user)->getJson(route('reception.patients.show', $patient));

        $response->assertOk();
        $response->assertJsonPath(
            'card_print_url',
            route('reception.patients.card.print', $patient)
        );
    }
}
