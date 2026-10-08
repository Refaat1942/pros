<?php

namespace Tests\Feature\Patient;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\VisitType;
use App\Services\OrderRefService;
use App\Services\PricingService;
use App\Support\ReservedNumber;
use Illuminate\Support\Facades\DB;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * لكل مريض رقم واحد فريد — لا يتكرر لمريض آخر ولا يظهر كرقم طلب أو طلب تسعير.
 */
class PatientUniqueNumberTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_returning_patient_with_same_national_id_keeps_one_file_and_number(): void
    {
        $user = $this->userWithRole('reception');
        $visitType = VisitType::create(['name' => 'كشف أولي']);
        $company = $this->civilianCompany();

        $first = $this->actingAs($user)->postJson('/reception/patients', [
            'name' => 'مريض عائد',
            'patient_type' => Patient::TYPE_CIVILIAN,
            'national_id' => '29901011234567',
            'visit_type_id' => $visitType->id,
        ])->assertCreated();

        $second = $this->actingAs($user)->postJson('/reception/patients', [
            'name' => 'مريض عائد',
            'patient_type' => Patient::TYPE_CIVILIAN,
            'national_id' => '29901011234567',
            'contract_company_id' => $company->id,
            'visit_type_id' => $visitType->id,
        ])->assertCreated();

        $this->assertSame(1, Patient::where('national_id', '29901011234567')->count());
        $this->assertSame($first->json('patient_code'), $second->json('patient_code'));
        $this->assertTrue((bool) $second->json('already_registered'));
        $this->assertSame(2, Appointment::where('patient_id', $first->json('id'))->count());
        // بيانات الفوترة الحالية كما أدخلها الاستقبال الآن.
        $this->assertSame($company->id, Patient::find($first->json('id'))->contract_company_id);
    }

    public function test_patient_codes_order_refs_and_pricing_numbers_never_overlap(): void
    {
        // كل الأرقام المرشحة نفس الرقم — الثاني والثالث يجب أن يرفضا ويأخذا رقماً آخر.
        $sequence = ['555555', '555555', '666666', '555555', '666666', '777777'];
        $next = function () use (&$sequence) {
            return array_shift($sequence);
        };

        $a = ReservedNumber::claim(ReservedNumber::KIND_PATIENT, $next);
        $b = ReservedNumber::claim(ReservedNumber::KIND_ORDER_REF, $next);
        $c = ReservedNumber::claim(ReservedNumber::KIND_PRICING_REQUEST, $next);

        $this->assertSame(['555555', '666666', '777777'], [$a, $b, $c]);
        $this->assertSame(3, DB::table('reserved_numbers')->count());
    }

    public function test_generators_reserve_their_numbers_in_shared_registry(): void
    {
        $orderRef = app(OrderRefService::class)->generate();
        $requestNo = app(PricingService::class)->nextRequestNo();

        $this->assertNotSame($orderRef, $requestNo);
        $this->assertDatabaseHas('reserved_numbers', ['number' => $orderRef, 'kind' => ReservedNumber::KIND_ORDER_REF]);
        $this->assertDatabaseHas('reserved_numbers', ['number' => $requestNo, 'kind' => ReservedNumber::KIND_PRICING_REQUEST]);
    }

    public function test_registered_patient_code_is_reserved(): void
    {
        $user = $this->userWithRole('reception');
        $visitType = VisitType::create(['name' => 'كشف أولي']);

        $response = $this->actingAs($user)->postJson('/reception/patients', [
            'name' => 'مريض محجوز الرقم',
            'patient_type' => Patient::TYPE_CIVILIAN,
            'visit_type_id' => $visitType->id,
        ])->assertCreated();

        $this->assertDatabaseHas('reserved_numbers', [
            'number' => $response->json('patient_code'),
            'kind' => ReservedNumber::KIND_PATIENT,
        ]);
    }
}
