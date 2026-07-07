<?php

namespace Tests\Feature\Pipeline;

use App\Models\ApprovalContract;
use App\Models\CaseRecord;
use App\Models\MilitaryRank;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Quote;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class AdminCaseDetailTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_admin_can_fetch_civilian_case_detail_with_quote_and_letter(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $admin   = $this->userWithRole('admin');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $case->update([
            'quote_no'      => 'QT-2026-0099',
            'quote_total'   => 2000.00,
            'work_order_no' => 'WO-2026-0099',
            'delivered_at'  => now()->toDateString(),
        ]);

        $quote = Quote::create([
            'quote_no'     => 'QT-2026-0099',
            'case_id'      => $case->id,
            'order_ref'    => $case->order_ref,
            'patient_name' => $patient->name,
            'company_name' => $company->name,
            'quote_date'   => now()->toDateString(),
            'status'       => Quote::STATUS_APPROVED,
            'total'        => 2000.00,
        ]);

        ApprovalContract::create([
            'contract_no'     => 'CTR-2026-0001',
            'case_id'         => $case->id,
            'quote_id'        => $quote->id,
            'patient_name'    => $patient->name,
            'company_name'    => $company->name,
            'approved_amount' => 2000.00,
            'approval_date'   => now()->toDateString(),
            'work_order_no'   => 'WO-2026-0099',
            'letter_path'     => 'approval_letters/test-letter.png',
            'letter_ref'      => 'LTR-001',
        ]);

        $response = $this->actingAs($admin)->getJson('/admin/cases/' . $case->id . '/detail');

        $response->assertOk();
        $response->assertJsonPath('patient.name', $patient->name);
        $response->assertJsonPath('quote.quote_no', 'QT-2026-0099');
        $response->assertJsonPath('approval.contract_no', 'CTR-2026-0001');
        $response->assertJsonPath('approval.letter_ref', 'LTR-001');
        $response->assertJsonPath('approval.letter_ext', 'png');
        $this->assertStringContainsString('/admin/cases/' . $case->id . '/quote', $response->json('quote.print_url'));
    }

    public function test_admin_case_detail_exposes_jfif_letter_extension_for_image_preview(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $admin   = $this->userWithRole('admin');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING);
        $case->update(['work_order_no' => 'WO-2026-0822']);

        $quote = Quote::create([
            'quote_no'     => 'QT-2026-0822',
            'case_id'      => $case->id,
            'order_ref'    => $case->order_ref,
            'patient_name' => $patient->name,
            'company_name' => $company->name,
            'quote_date'   => now()->toDateString(),
            'status'       => Quote::STATUS_APPROVED,
            'total'        => 10000.00,
        ]);

        ApprovalContract::create([
            'contract_no'     => 'CNT-2026-0001',
            'case_id'         => $case->id,
            'quote_id'        => $quote->id,
            'patient_name'    => $patient->name,
            'company_name'    => $company->name,
            'approved_amount' => 10000.00,
            'approval_date'   => now()->toDateString(),
            'work_order_no'   => 'WO-2026-0822',
            'letter_path'     => 'approval_letters/sample.jfif',
        ]);

        \Illuminate\Support\Facades\Storage::disk('public')->put('approval_letters/sample.jfif', 'fake-image');

        $response = $this->actingAs($admin)->getJson('/admin/cases/' . $case->id . '/detail');

        $response->assertOk();
        $response->assertJsonPath('approval.has_letter', true);
        $response->assertJsonPath('approval.letter_ext', 'jfif');
        // الوصول للخطاب عبر مسار مُصادَق عليه (وليس رابط /storage عام).
        $this->assertStringContainsString('contracts/', $response->json('approval.letter_url'));
        $this->assertStringEndsWith('/letter', $response->json('approval.letter_url'));
    }

    public function test_admin_case_detail_marks_quote_items_added_by_adjustments(): void
    {
        $this->stockItem('ITM-001', qty: 10);

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $admin   = $this->userWithRole('admin');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_COST_CALC);

        $bom = app(\App\Services\BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'ITM-001', 'qty' => 1],
        ]);
        \App\Models\BomItem::create([
            'bom_id'          => $bom->id,
            'stock_item_code' => 'ITM-001',
            'name'            => 'ركبة هيدروليكية',
            'source'          => \App\Models\BomItem::SOURCE_ADJUSTMENT,
            'qty'             => 2,
            'unit_cost'       => 0,
            'issued_qty'      => 0,
            'returned_qty'    => 0,
        ]);

        $quote = Quote::create([
            'quote_no'     => 'QT-2026-ADJ1',
            'case_id'      => $case->id,
            'order_ref'    => $case->order_ref,
            'patient_name' => $patient->name,
            'company_name' => $company->name,
            'quote_date'   => now()->toDateString(),
            'status'       => Quote::STATUS_APPROVED,
            'total'        => 30000.00,
        ]);

        \App\Models\QuoteItem::create([
            'quote_id'        => $quote->id,
            'name'            => 'ركبة هيدروليكية',
            'source'          => \App\Models\BomItem::SOURCE_SPEC,
            'stock_item_code' => 'ITM-001',
            'qty'             => 1,
            'amount'          => 10000.00,
        ]);
        \App\Models\QuoteItem::create([
            'quote_id'        => $quote->id,
            'name'            => 'ركبة هيدروليكية',
            'source'          => \App\Models\BomItem::SOURCE_ADJUSTMENT,
            'stock_item_code' => 'ITM-001',
            'qty'             => 2,
            'amount'          => 20000.00,
        ]);

        $case->update(['quote_no' => 'QT-2026-ADJ1']);

        $response = $this->actingAs($admin)->getJson('/admin/cases/' . $case->id . '/detail');

        $response->assertOk();
        $response->assertJsonPath('quote.items.0.from_adjustments', false);
        $response->assertJsonPath('quote.items.0.source_label', null);
        $response->assertJsonPath('quote.items.1.from_adjustments', true);
        $response->assertJsonPath('quote.items.1.source_label', 'المعدلات');
    }

    public function test_admin_military_case_detail_always_shows_armed_forces_sovereign(): void
    {
        $company = $this->civilianCompany();
        $rank    = MilitaryRank::create(['name' => 'نقيب', 'rank_code' => 'CAP', 'sort_order' => 1]);
        $patient = Patient::create([
            'patient_code'     => '642291',
            'patient_qr'       => 'QR-642291',
            'name'             => 'عرفه العسكري',
            'phone'            => '01062204741',
            'patient_type'     => Patient::TYPE_MILITARY,
            'military_rank_id' => $rank->id,
            'rank'             => 'نقيب',
            'registered_at'    => now()->toDateString(),
            'status'           => Patient::STATUS_ACTIVE,
        ]);
        $admin = $this->userWithRole('admin');
        $case  = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $case->update([
            'path'          => CaseRecord::PATH_MILITARY,
            'work_order_no' => 'WO-2026-0002',
            'delivered_at'  => now()->toDateString(),
        ]);

        $response = $this->actingAs($admin)->getJson('/admin/cases/' . $case->id . '/detail');

        $response->assertOk();
        $response->assertJsonPath('is_military', true);
        $response->assertJsonPath('patient.sovereign', Patient::MILITARY_SOVEREIGN_ENTITY);
        $response->assertJsonPath('patient.company', null);
        $response->assertJsonPath('patient.rank', 'نقيب');
    }

    public function test_admin_case_detail_shows_paid_cash_quote_status_not_awaiting_cashier(): void
    {
        $patient = $this->cashPatient();
        $admin   = $this->userWithRole('admin');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $case->update([
            'quote_no'      => 'QT-2026-CASH1',
            'quote_total'   => 1000.00,
            'paid'          => 1000.00,
            'work_order_no' => 'WO-2026-0001',
            'delivered_at'  => now(),
        ]);

        Quote::create([
            'quote_no'     => 'QT-2026-CASH1',
            'case_id'      => $case->id,
            'order_ref'    => $case->order_ref,
            'patient_name' => $patient->name,
            'company_name' => null,
            'quote_date'   => now()->toDateString(),
            'status'       => Quote::STATUS_ISSUED,
            'status_label' => 'بانتظار الدفع في الخزنة',
            'total'        => 1000.00,
        ]);

        $quote = Quote::where('case_id', $case->id)->firstOrFail();

        Payment::create([
            'payment_no'    => 'PAY-2026-0001',
            'case_id'       => $case->id,
            'quote_id'      => $quote->id,
            'patient_id'    => $patient->id,
            'patient_name'  => $patient->name,
            'amount'        => 1000.00,
            'method'        => 'vodafone_cash',
            'reference'     => 'VF-998877',
            'received_by'   => 'موظف الخزنة',
            'received_at'   => now(),
        ]);

        $this->actingAs($admin)
            ->getJson('/admin/cases/' . $case->id . '/detail')
            ->assertOk()
            ->assertJsonPath('quote.status_label', 'تم الدفع في الخزنة')
            ->assertJsonPath('payment.method', 'vodafone_cash')
            ->assertJsonPath('payment.method_label', 'فودافون كاش');
    }

    public function test_admin_case_quote_print_returns_print_view(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $admin   = $this->userWithRole('admin');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);
        $case->update(['quote_no' => 'QT-2026-0100']);

        Quote::create([
            'quote_no'     => 'QT-2026-0100',
            'case_id'      => $case->id,
            'order_ref'    => $case->order_ref,
            'patient_name' => $patient->name,
            'company_name' => $company->name,
            'quote_date'   => now()->toDateString(),
            'status'       => Quote::STATUS_ISSUED,
            'total'        => 500.00,
        ]);

        $this->actingAs($admin)
            ->get('/admin/cases/' . $case->id . '/quote')
            ->assertOk()
            ->assertSee('QT-2026-0100');
    }
}
