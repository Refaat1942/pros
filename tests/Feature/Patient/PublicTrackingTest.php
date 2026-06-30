<?php

namespace Tests\Feature\Patient;

use App\Models\CaseRecord;
use App\Models\Patient;
use Illuminate\Support\Str;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class PublicTrackingTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_public_tracking_page_shows_progress_without_sensitive_data(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $patient->update(['tracking_uid' => 'case-test1234']);

        $case = CaseRecord::create([
            'case_no'             => 'C-2026-0001',
            'order_ref'           => 'ORD-0001',
            'tracking_uid'        => $patient->tracking_uid,
            'patient_id'          => $patient->id,
            'contract_company_id' => $company->id,
            'company_name'        => $company->name,
            'patient_type'        => Patient::TYPE_CIVILIAN,
            'path'                => CaseRecord::PATH_STANDARD,
            'stage_key'           => CaseRecord::STAGE_MANUFACTURING,
            'quote_total'         => 50000,
            'total_cost'          => 40000,
        ]);

        $response = $this->get(route('public.track.case', ['uid' => $patient->tracking_uid]));

        $response->assertOk();
        $response->assertSee('متابعة حالة الطلب');
        $response->assertSee('جاري التصنيع بالورشة');
        $response->assertSee('case-test1234');
        $response->assertSee('حالة الطلب');
        $response->assertDontSee('نسبة الإنجاز');
        $response->assertDontSee('← أنت هنا');
        $response->assertDontSee($patient->name);
        $response->assertDontSee('50000');
        $response->assertDontSee('40000');
    }

    public function test_public_tracking_before_case_created_shows_registered_step(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $patient->update(['tracking_uid' => 'case-newreg01']);

        $response = $this->get(route('public.track.case', ['uid' => $patient->tracking_uid]));

        $response->assertOk();
        $response->assertSee('تم التسجيل — في انتظار الكشف الطبي');
        $response->assertSee('حالة الطلب');
        $response->assertSee('تسجيل واستقبال');
        $response->assertDontSee('نسبة الإنجاز');
        $response->assertDontSee('0%');
        $response->assertDontSee('مسار مدني');
    }

    public function test_civilian_operations_shows_approval_step_not_manufacturing(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $patient->update(['tracking_uid' => 'case-civwait1']);

        CaseRecord::create([
            'case_no'             => 'C-2026-0099',
            'order_ref'           => 'ORD-0099',
            'tracking_uid'        => $patient->tracking_uid,
            'patient_id'          => $patient->id,
            'contract_company_id' => $company->id,
            'company_name'        => $company->name,
            'patient_type'        => Patient::TYPE_CIVILIAN,
            'path'                => CaseRecord::PATH_STANDARD,
            'stage_key'           => CaseRecord::STAGE_OPERATIONS,
        ]);

        $response = $this->get(route('public.track.case', ['uid' => $patient->tracking_uid]));

        $response->assertOk();
        $response->assertSee('بمكتب التشغيل — بانتظار الاعتماد');
        $response->assertSee('حالة الطلب');
        $response->assertSee('التسعير واعتماد التشغيل');
        $response->assertDontSee('نسبة الإنجاز');
        $response->assertDontSee('← أنت هنا');
    }

    public function test_civilian_cost_calc_stays_on_preparation_step_not_pricing(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $patient->update(['tracking_uid' => 'case-civcost01']);

        CaseRecord::create([
            'case_no'             => 'C-2026-0100',
            'order_ref'           => 'ORD-0100',
            'tracking_uid'        => $patient->tracking_uid,
            'patient_id'          => $patient->id,
            'contract_company_id' => $company->id,
            'company_name'        => $company->name,
            'patient_type'        => Patient::TYPE_CIVILIAN,
            'path'                => CaseRecord::PATH_STANDARD,
            'stage_key'           => CaseRecord::STAGE_COST_CALC,
        ]);

        $response = $this->get(route('public.track.case', ['uid' => $patient->tracking_uid]));

        $response->assertOk();
        $response->assertSee('جاري احتساب التكاليف');
        $response->assertSee('حالة الطلب');
        $response->assertSee('التوصيف الفني والتحضير');
        $response->assertDontSee('نسبة الإنجاز');
        $response->assertDontSee('← أنت هنا');
    }

    public function test_military_pathway_shows_six_steps_without_pricing_gate(): void
    {
        $company = $this->militaryCompany();
        $patient = $this->militaryPatient($company);
        $patient->update(['tracking_uid' => 'case-milpath1']);

        $response = $this->get(route('public.track.case', ['uid' => $patient->tracking_uid]));

        $response->assertOk();
        $response->assertSee('حالة الطلب');
        $response->assertDontSee('نسبة الإنجاز');
        $response->assertDontSee('التسعير واعتماد التشغيل');
        $response->assertDontSee('مسار عسكري');
    }

    public function test_military_pre_manufacturing_shows_preparation_label(): void
    {
        $company = $this->militaryCompany();
        $patient = $this->militaryPatient($company);
        $patient->update(['tracking_uid' => 'case-milprep01']);

        CaseRecord::create([
            'case_no'             => 'M-2026-0101',
            'order_ref'           => 'ORD-0101',
            'tracking_uid'        => $patient->tracking_uid,
            'patient_id'          => $patient->id,
            'contract_company_id' => $company->id,
            'company_name'        => $company->name,
            'patient_type'        => Patient::TYPE_MILITARY,
            'path'                => CaseRecord::PATH_MILITARY,
            'stage_key'           => CaseRecord::STAGE_COST_CALC,
        ]);

        $response = $this->get(route('public.track.case', ['uid' => $patient->tracking_uid]));

        $response->assertOk();
        $response->assertSee('جاري التحضير للتصنيع');
        $response->assertSee('حالة الطلب');
        $response->assertSee('التوصيف الفني والتحضير');
        $response->assertDontSee('نسبة الإنجاز');
        $response->assertDontSee('← أنت هنا');
        $response->assertDontSee('التسعير واعتماد التشغيل');
    }

    public function test_civilian_issued_quote_at_warehouse_shows_entity_approval_wait(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $patient->update(['tracking_uid' => 'case-civissued1']);

        $case = CaseRecord::create([
            'case_no'              => 'C-2026-0102',
            'order_ref'            => '793536',
            'tracking_uid'         => $patient->tracking_uid,
            'patient_id'           => $patient->id,
            'contract_company_id'  => $company->id,
            'company_name'         => $company->name,
            'patient_type'         => Patient::TYPE_CIVILIAN,
            'path'                 => CaseRecord::PATH_STANDARD,
            'stage_key'            => CaseRecord::STAGE_MANUFACTURING,
            'manufacturing_stage'  => CaseRecord::MFG_WAREHOUSE,
        ]);

        \App\Models\Quote::create([
            'quote_no'     => 'QT-2026-0862',
            'order_ref'    => $case->order_ref,
            'case_id'      => $case->id,
            'patient_name' => $patient->name,
            'company_name' => $company->name,
            'quote_date'   => now()->toDateString(),
            'status'       => \App\Models\Quote::STATUS_ISSUED,
            'status_label' => 'صادر للاستقبال',
            'total'        => 95000,
        ]);

        $response = $this->get(route('public.track.case', ['uid' => $patient->tracking_uid]));

        $response->assertOk();
        $response->assertSee('بانتظار موافقة الجهة');
        $response->assertSee('حالة الطلب');
        $response->assertSee('التسعير واعتماد التشغيل');
        $response->assertDontSee('نسبة الإنجاز');
        $response->assertDontSee('← أنت هنا');
    }

    public function test_invalid_tracking_uid_returns_404(): void
    {
        $this->get(route('public.track.case', ['uid' => 'case-' . Str::random(12)]))
            ->assertNotFound();
    }

    public function test_patient_registration_generates_tracking_uid_and_qr(): void
    {
        $company = $this->civilianCompany();
        $visitType = $this->defaultVisitType();
        $reception = $this->userWithRole('reception');

        $response = $this->actingAs($reception)->postJson('/reception/patients', [
            'name'                 => 'سارة محمد',
            'phone'                => '01012345678',
            'patient_type'         => 'civilian',
            'contract_company_id'  => $company->id,
            'visit_type_id'        => $visitType->id,
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['tracking_uid', 'tracking_url', 'qr_svg']);
        $this->assertStringStartsWith('case-', $response->json('tracking_uid'));
        $this->assertStringContainsString($response->json('tracking_uid'), $response->json('tracking_url'));
        $this->assertStringContainsString('<svg', $response->json('qr_svg'));
    }
}
