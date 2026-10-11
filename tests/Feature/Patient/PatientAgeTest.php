<?php

namespace Tests\Feature\Patient;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Role;
use App\Models\VisitType;
use App\Models\WorkshopSection;
use App\Support\Journeys\CaseJourneyRunner;
use Tests\Support\ProstheticTestCase;

/**
 * سن المريض يُدخله الاستقبال ويظهر في عرض السعر والفاتورة وأمر الشغل وتفاصيل الحالة.
 */
class PatientAgeTest extends ProstheticTestCase
{
    public function test_reception_can_enter_age_and_national_id_takes_priority(): void
    {
        $user = $this->userWithRole('reception');
        $visit = VisitType::create(['name' => 'كشف أولي']);

        $noId = $this->actingAs($user)->postJson('/reception/patients', [
            'name' => 'مريض بلا رقم قومي', 'patient_type' => Patient::TYPE_CIVILIAN,
            'age' => 42, 'visit_type_id' => $visit->id,
        ])->assertCreated();
        $this->assertSame(42, Patient::find($noId->json('id'))->ageYears());

        // الرقم القومي (مواليد 1999) يغلب السن المكتوب.
        $withId = $this->actingAs($user)->postJson('/reception/patients', [
            'name' => 'مريض برقم قومي', 'patient_type' => Patient::TYPE_CIVILIAN,
            'national_id' => '29901011234567', 'age' => 5, 'visit_type_id' => $visit->id,
        ])->assertCreated();
        $this->assertSame(
            \App\Support\QuotePrintPresenter::ageFromNationalId('29901011234567'),
            Patient::find($withId->json('id'))->ageYears(),
        );
    }

    public function test_age_is_validated(): void
    {
        $user = $this->userWithRole('reception');
        $visit = VisitType::create(['name' => 'كشف أولي']);

        $this->actingAs($user)->postJson('/reception/patients', [
            'name' => 'مريض', 'patient_type' => Patient::TYPE_CIVILIAN, 'age' => 150, 'visit_type_id' => $visit->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('age');
    }

    public function test_age_can_be_corrected_from_the_appointment(): void
    {
        $user = $this->userWithRole('reception');
        $visit = VisitType::create(['name' => 'كشف أولي']);
        $res = $this->actingAs($user)->postJson('/reception/patients', [
            'name' => 'مريض للتصحيح', 'patient_type' => Patient::TYPE_CIVILIAN, 'visit_type_id' => $visit->id,
        ])->assertCreated();
        $appointment = Appointment::where('patient_id', $res->json('id'))->firstOrFail();

        $this->actingAs($user)->patchJson("/reception/appointments/{$appointment->id}/correct", [
            'name' => 'مريض للتصحيح', 'phone' => '01099998888', 'age' => 33, 'visit_type_id' => $visit->id,
        ])->assertOk();

        $this->assertSame(33, Patient::find($res->json('id'))->age);
    }

    public function test_entered_age_prints_on_quote_invoice_work_order_and_case_detail(): void
    {
        $this->userWithRole('admin');
        $technician = $this->userWithRole(Role::SLUG_WORKSHOP);
        WorkshopSection::create(['name' => 'قسم', 'code' => 'l', 'sort' => 1, 'active' => true])->technicians()->attach($technician->id);
        $knee = $this->stockItem('1101', qty: 30);
        $knee->update(['uom' => 'عدد', 'price' => 2500]);

        $case = (new CaseJourneyRunner([['item' => $knee->fresh(), 'qty' => 1.0]]))
            ->run(CaseJourneyRunner::JOURNEY_CASH, 'مريض بسن مكتوب');
        // بدون رقم قومي صالح — يظهر السن الذي أدخله الاستقبال.
        $case->patient->update(['national_id' => null, 'age' => 61]);

        $admin = CaseJourneyRunner::userFor('admin');
        $quote = \App\Models\Quote::where('case_id', $case->id)->latest('id')->firstOrFail();

        $this->actingAs($admin)->get("/admin/cases/{$case->id}/quote?embed=1")->assertOk()->assertSee('61 عام', false);
        $this->actingAs($admin)->get("/admin/cases/{$case->id}/invoice?embed=1")->assertOk()->assertSee('61 عام', false);
        $this->actingAs($admin)->get("/operations/case/{$case->id}/print-work-order")->assertOk()->assertSee('61 عام', false);
        $this->actingAs($admin)->getJson("/admin/cases/{$case->id}/detail")->assertOk()->assertJsonPath('patient.age', 61);
        $this->assertNotNull($quote);
    }
}
