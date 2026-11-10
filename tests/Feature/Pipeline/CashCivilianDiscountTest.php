<?php

namespace Tests\Feature\Pipeline;

use App\Enums\WorkflowEvent;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Quote;
use App\Models\User;
use App\Services\AdjustmentsService;
use App\Services\BomService;
use App\Services\InvoiceService;
use App\Services\PermissionCatalogService;
use App\Services\WorkflowService;
use App\Support\InvoicePrintPresenter;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * خصم إضافي عند الاعتماد — كاش / تعاقد / عسكري، بصلاحية، بسبب، ويظهر في العرض والفاتورة والمديونية
 * والخزنة تطلب الصافي.
 */
class CashCivilianDiscountTest extends TestCase
{
    use ProstheticTestHelper;

    private function caseAtCosting(Patient $patient): CaseRecord
    {
        if (! \App\Models\StockItem::findByOperationalCode($this->resolveTestOperationalCode('RM-001'))) {
            $this->stockItem('RM-001', 20, 100.00);
        }
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_TECHNICAL);
        app(BomService::class)->createSpecRaw($case, [[
            'stock_item_code' => $this->resolveTestOperationalCode('RM-001'), 'name' => 'صنف RM-001', 'qty' => 1,
        ]]);
        app(WorkflowService::class)->advance($case, WorkflowEvent::SpecSaved->value);
        $case = $case->fresh();
        if ($case->stage_key === CaseRecord::STAGE_ADJUSTMENTS) {
            $case = app(AdjustmentsService::class)->complete($case);
        }
        $this->assertSame(CaseRecord::STAGE_COST_CALC, $case->fresh()->stage_key);

        return $case->fresh();
    }

    private function costingUser(bool $canDiscount): User
    {
        $user = $this->userWithRole('costing');
        app(PermissionCatalogService::class)->seedRoleDefaults(true);
        if ($canDiscount) {
            $user->role->permissions()->syncWithoutDetaching(Permission::where('slug', 'grant-cash-discount')->pluck('id'));
        }

        return $user->fresh(['role']);
    }

    public function test_cash_discount_flows_to_quote_cashier_and_invoice(): void
    {
        $case = $this->caseAtCosting($this->cashPatient());
        $costing = $this->costingUser(true);

        $show = $this->actingAs($costing)->getJson("/costing/queue/{$case->id}")->assertOk();
        $show->assertJsonPath('case.is_cash_civilian', true)->assertJsonPath('case.can_grant_cash_discount', true);

        $this->actingAs($costing)->postJson("/costing/queue/{$case->id}/confirm", [
            'discount_type' => 'percent', 'discount_value' => 10, 'discount_reason' => 'حالة إنسانية', 'discount_category' => 'administrative',
        ])->assertOk();

        $quote = Quote::where('case_id', $case->id)->firstOrFail();
        $gross = (float) $quote->gross_total;
        $this->assertGreaterThan(0, $gross);
        $net = round($gross - round($gross * 0.10, 2), 2);
        $this->assertEquals($net, (float) $quote->total);
        $this->assertEquals(round($gross * 0.10, 2), (float) $quote->discount_amount);
        $this->assertEquals($net, (float) $case->fresh()->quote_total);
        $this->assertSame(CaseRecord::STAGE_CASHIER, $case->fresh()->stage_key);
        $this->assertTrue(AuditLog::where('action', 'discount')->exists());

        // الخزنة تطلب الصافي وتقبل دفعه كامل.
        $cashier = $this->userWithRole('cashier');
        $row = collect($this->actingAs($cashier)->getJson('/cashier/payments/list')->assertOk()->json('data'))
            ->firstWhere('id', $case->id);
        $this->assertEquals($net, (float) $row['amount_due']);
        $this->actingAs($cashier)->postJson("/cashier/payments/{$case->id}/confirm", ['method' => 'cash', 'amount' => $net])->assertOk();
        $this->assertEquals($net, (float) Payment::where('case_id', $case->id)->sum('amount'));
        $this->assertSame(CaseRecord::STAGE_OPERATIONS, $case->fresh()->stage_key);

        // عرض السعر المطبوع.
        $html = $this->actingAs($cashier)->get("/cashier/quote/{$quote->id}/print?embed=1")->assertOk()->getContent();
        $this->assertStringContainsString('إجمالي السعر قبل الخصم', $html);
        $this->assertStringContainsString('نسبة الخصم (10%)', $html);
        $this->assertStringContainsString('إجمالي السعر بعد الخصم', $html);

        // الفاتورة النهائية بالصافي ويظهر فيها الخصم.
        $invoice = app(InvoiceService::class)->issueFinalInvoice($case->fresh());
        $this->assertEquals($net, (float) $invoice['invoice_total']);
        $doc = InvoicePrintPresenter::document($case->fresh());
        $this->assertTrue($doc['totals']['has_discount']);
        $this->assertEquals($gross, (float) $doc['totals']['gross_total']);
        $this->assertEquals($net, (float) $doc['totals']['display_total']);
        $this->assertEquals(0.0, (float) $doc['remaining']);
    }

    public function test_fixed_amount_discount_prints_as_discount_amount(): void
    {
        $case = $this->caseAtCosting($this->cashPatient());
        $this->actingAs($this->costingUser(true))->postJson("/costing/queue/{$case->id}/confirm", [
            'discount_type' => 'amount', 'discount_value' => 5, 'discount_reason' => 'موافقة المدير', 'discount_category' => 'administrative',
        ])->assertOk();

        $quote = Quote::where('case_id', $case->id)->firstOrFail();
        $this->assertEquals(round((float) $quote->gross_total - 5, 2), (float) $quote->total);
        $this->assertNull($quote->discount_percent);

        $html = $this->actingAs($this->userWithRole('cashier'))->get("/cashier/quote/{$quote->id}/print?embed=1")->getContent();
        $this->assertMatchesRegularExpression('/<td class="t-label">الخصم<\/td>/u', $html);
    }

    public function test_contracted_discount_reduces_quote_before_contract_split(): void
    {
        $costing = $this->costingUser(true);
        $entity = $this->caseAtCosting($this->civilianPatient($this->civilianCompany()));
        $this->actingAs($costing)->getJson("/costing/queue/{$entity->id}")->assertJsonPath('case.can_grant_cash_discount', true);

        $this->actingAs($costing)->postJson("/costing/queue/{$entity->id}/confirm", [
            'discount_type' => 'percent', 'discount_value' => 25, 'discount_reason' => 'قرار الإدارة', 'discount_category' => 'administrative',
        ])->assertOk();

        $quote = Quote::where('case_id', $entity->id)->firstOrFail();
        $this->assertGreaterThan(0, (float) $quote->gross_total);
        $this->assertEqualsWithDelta((float) $quote->gross_total * 0.25, (float) $quote->discount_amount, 0.01);
        $this->assertEqualsWithDelta((float) $quote->gross_total - (float) $quote->discount_amount, (float) $quote->total, 0.01);
        // تقسيم الجهة/المريض بيتحسب على الصافي بعد الخصم.
        $this->assertEqualsWithDelta((float) $quote->total, (float) $entity->fresh()->quote_total, 0.01);
    }

    public function test_military_debt_is_sheet_price_after_discount(): void
    {
        $costing = $this->costingUser(true);
        $military = $this->caseAtCosting($this->militaryPatient($this->militaryCompany()));
        $gross = (float) $this->actingAs($costing)->getJson("/costing/queue/{$military->id}")->assertOk()->json('costing.selling_price');
        $this->assertGreaterThan(0, $gross);

        $this->actingAs($costing)->postJson("/costing/queue/{$military->id}/confirm", [
            'discount_type' => 'percent', 'discount_value' => 25, 'discount_reason' => 'قرار الجهة', 'discount_category' => 'administrative',
        ])->assertOk();

        $case = $military->fresh();
        $net = round($gross - round($gross * 0.25, 2), 2);
        $this->assertEqualsWithDelta($gross, (float) $case->costing_gross_price, 0.01);
        $this->assertEqualsWithDelta($net, (float) $case->military_selling_price, 0.01);

        if ($case->stage_key === CaseRecord::STAGE_OPERATIONS) {
            $case = app(\App\Services\OperationsService::class)->approve($case, 'اختبار');
        }
        // المديونية العسكرية = سعر الحسبة بعد الخصم (مش WAC).
        $this->assertEqualsWithDelta($net, (float) $case->fresh()->total_cost, 0.01);
    }

    public function test_discount_needs_permission_reason_and_valid_amount(): void
    {
        $costing = $this->costingUser(true);

        $cash = $this->caseAtCosting($this->cashPatient());
        $this->actingAs($costing)->postJson("/costing/queue/{$cash->id}/confirm", [
            'discount_type' => 'percent', 'discount_value' => 10, // من غير نوع خصم معتمد
        ])->assertStatus(422);
        $this->actingAs($costing)->postJson("/costing/queue/{$cash->id}/confirm", [
            'discount_type' => 'percent', 'discount_value' => 100, 'discount_reason' => 'x', 'discount_category' => 'administrative',
        ])->assertStatus(422);
        $this->actingAs($costing)->postJson("/costing/queue/{$cash->id}/confirm", [
            'discount_type' => 'amount', 'discount_value' => 999999, 'discount_reason' => 'x', 'discount_category' => 'administrative',
        ])->assertStatus(422);

        $noPerm = User::create(['name' => 'c2', 'username' => 'costing2', 'password' => 'secret123', 'role_id' => $costing->role_id, 'status' => 'active']);
        $costing->role->permissions()->detach(Permission::where('slug', 'grant-cash-discount')->pluck('id'));
        $this->actingAs($noPerm->fresh(['role']))->getJson("/costing/queue/{$cash->id}")->assertJsonPath('case.can_grant_cash_discount', false);
        $this->actingAs($noPerm->fresh(['role']))->postJson("/costing/queue/{$cash->id}/confirm", [
            'discount_type' => 'percent', 'discount_value' => 10, 'discount_reason' => 'x', 'discount_category' => 'administrative',
        ])->assertForbidden();

        // بدون خصم: يمشي عادي والعرض بالإجمالي.
        $this->actingAs($noPerm->fresh(['role']))->postJson("/costing/queue/{$cash->id}/confirm")->assertOk();
        $quote = Quote::where('case_id', $cash->id)->firstOrFail();
        $this->assertNull($quote->gross_total);
        $this->assertEquals(0.0, (float) $quote->discount_amount);
    }

    public function test_grant_discount_permission_is_off_by_default_and_listed_under_costing(): void
    {
        $costing = $this->costingUser(false);
        $this->assertFalse($costing->can('grant-cash-discount'));

        $actions = app(PermissionCatalogService::class)->matrixPageData()['dashboards']['costing']['actions'];
        $this->assertContains('grant-cash-discount', $actions->pluck('slug')->all());
    }

    public function test_super_admin_discount_policy_controls_pathways_types_and_limits(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->putJson('/admin/discount-policy', [
            'pathways' => ['cash' => true, 'contracted' => false, 'military' => true],
            'types' => [
                ['label' => 'حالة إنسانية', 'allow_percent' => true, 'allow_amount' => false, 'max_percent' => 30],
                ['label' => 'خصم إداري', 'allow_percent' => true, 'allow_amount' => true, 'max_percent' => null],
            ],
        ])->assertOk();

        $policy = app(\App\Services\DiscountPolicyService::class)->policy();
        $humanitarian = $policy['types'][0]['key'];
        $costing = $this->costingUser(true);

        // الجهات المتعاقدة مقفولة في السياسة → مفيش خصم ليها.
        $entity = $this->caseAtCosting($this->civilianPatient($this->civilianCompany()));
        $this->actingAs($costing)->getJson("/costing/queue/{$entity->id}")->assertJsonPath('case.can_grant_cash_discount', false);
        $this->actingAs($costing)->postJson("/costing/queue/{$entity->id}/confirm", [
            'discount_type' => 'percent', 'discount_value' => 10, 'discount_category' => $humanitarian,
        ])->assertStatus(422);

        $cash = $this->caseAtCosting($this->cashPatient());
        $show = $this->actingAs($costing)->getJson("/costing/queue/{$cash->id}")->assertOk();
        $this->assertCount(2, $show->json('case.discount_types'));
        // فوق الحد الأقصى (30%) / بطريقة مش مسموحة (مبلغ) → مرفوض.
        $this->actingAs($costing)->postJson("/costing/queue/{$cash->id}/confirm", [
            'discount_type' => 'percent', 'discount_value' => 35, 'discount_category' => $humanitarian,
        ])->assertStatus(422);
        $this->actingAs($costing)->postJson("/costing/queue/{$cash->id}/confirm", [
            'discount_type' => 'amount', 'discount_value' => 5, 'discount_category' => $humanitarian,
        ])->assertStatus(422);
        // داخل الحد ومن غير ملاحظة → يمشي، والسبب = اسم النوع.
        $this->actingAs($costing)->postJson("/costing/queue/{$cash->id}/confirm", [
            'discount_type' => 'percent', 'discount_value' => 30, 'discount_category' => $humanitarian,
        ])->assertOk();
        $this->assertSame('حالة إنسانية', Quote::where('case_id', $cash->id)->value('discount_reason'));
    }
}
