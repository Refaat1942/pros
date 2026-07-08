<?php

namespace Tests\Feature\Finance;

use App\Models\CaseRecord;
use App\Models\ContractCompanyDebt;
use App\Models\MilitaryDebt;
use App\Models\Payment;
use App\Models\StockMovement;
use App\Services\FinancialBalanceService;
use Carbon\Carbon;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class FinancialBalanceServiceTest extends TestCase
{
    use ProstheticTestHelper;

    private Carbon $from;

    private Carbon $to;

    protected function setUp(): void
    {
        parent::setUp();
        $this->from = Carbon::parse('2026-06-01');
        $this->to = Carbon::parse('2026-06-30');
    }

    private function seedFinance(): void
    {
        $before = '2026-05-15 10:00:00';
        $within = '2026-06-10 10:00:00';

        // ── Civilian receivable ──
        $company = $this->civilianCompany();
        $debt = ContractCompanyDebt::where('contract_company_id', $company->id)->firstOrFail();
        $debt->update(['due' => 1000]);
        $debt->collectionEntries()->create([
            'installment_no' => 1, 'amount' => 200,
            'running_collected' => 200, 'remaining_after' => 800,
            'collected_at' => $before,
        ]);
        $debt->collectionEntries()->create([
            'installment_no' => 2, 'amount' => 300,
            'running_collected' => 500, 'remaining_after' => 500,
            'collected_at' => $within,
        ]);

        // ── Military receivable ──
        $mCompany = $this->militaryCompany();
        $mPatient = $this->militaryPatient($mCompany);
        $mCase = $this->caseAtStage($mPatient, CaseRecord::STAGE_DELIVERED);
        $mDebt = MilitaryDebt::create([
            'case_id' => $mCase->id,
            'patient_name' => $mPatient->name,
            'sovereign_entity' => 'القوات المسلحة',
            'total_cost' => 2000,
            'collected' => 0,
            'delivered_at' => '2026-06-05',
            'status' => MilitaryDebt::STATUS_PENDING,
        ]);
        $mDebt->collectionEntries()->create([
            'installment_no' => 1, 'amount' => 500,
            'running_collected' => 500, 'remaining_after' => 1500,
            'collected_at' => '2026-06-12 09:00:00',
        ]);

        // ── Cash payments ──
        $cPatient = $this->civilianPatient($company);
        $cCase = $this->caseAtStage($cPatient, CaseRecord::STAGE_DELIVERED);
        Payment::create([
            'payment_no' => 'PAY-BEFORE', 'case_id' => $cCase->id,
            'amount' => 400, 'method' => 'cash', 'received_at' => $before,
        ]);
        Payment::create([
            'payment_no' => 'PAY-WITHIN', 'case_id' => $cCase->id,
            'amount' => 1000, 'method' => 'cash', 'received_at' => '2026-06-08 12:00:00',
        ]);

        // ── Inventory (wac = 10) ──
        $item = $this->stockItem('RM-INV', qty: 8, wac: 10.00);
        StockMovement::create([
            'stock_item_id' => $item->id, 'movement_type' => StockMovement::TYPE_RECEIVE,
            'quantity' => 5, 'balance_after' => 5, 'moved_at' => '2026-05-20 08:00:00',
        ]);
        StockMovement::create([
            'stock_item_id' => $item->id, 'movement_type' => StockMovement::TYPE_RECEIVE,
            'quantity' => 3, 'balance_after' => 8, 'moved_at' => '2026-06-15 08:00:00',
        ]);
    }

    public function test_cash_opening_movement_closing(): void
    {
        $this->seedFinance();

        $cash = app(FinancialBalanceService::class)->balances($this->from, $this->to)['cash'];

        $this->assertSame(600.0, $cash['opening']);   // 400 payment + 200 civ collection before
        $this->assertSame(1800.0, $cash['movement']);  // 1000 payment + 300 civ + 500 mil within
        $this->assertSame(2400.0, $cash['closing']);
    }

    public function test_civilian_receivable(): void
    {
        $this->seedFinance();

        $civ = app(FinancialBalanceService::class)->balances($this->from, $this->to)['civilian'];

        $this->assertSame(800.0, $civ['opening']);   // 1000 due - 200 collected before
        $this->assertSame(-300.0, $civ['movement']);
        $this->assertSame(500.0, $civ['closing']);
    }

    public function test_military_receivable(): void
    {
        $this->seedFinance();

        $mil = app(FinancialBalanceService::class)->balances($this->from, $this->to)['military'];

        $this->assertSame(0.0, $mil['opening']);
        $this->assertSame(1500.0, $mil['movement']); // 2000 delivered within - 500 collected within
        $this->assertSame(1500.0, $mil['closing']);
    }

    public function test_inventory_value_reconstructed_from_movements(): void
    {
        $this->seedFinance();

        $inv = app(FinancialBalanceService::class)->balances($this->from, $this->to)['inventory'];

        $this->assertSame(50.0, $inv['opening']);   // qty 5 * wac 10
        $this->assertSame(30.0, $inv['movement']);
        $this->assertSame(80.0, $inv['closing']);   // qty 8 * wac 10
    }

    public function test_opening_overrides_are_added(): void
    {
        $this->seedFinance();

        $balances = app(FinancialBalanceService::class)->balances(
            $this->from,
            $this->to,
            [FinancialBalanceService::DOMAIN_CASH => 100.0],
        );

        $this->assertSame(700.0, $balances['cash']['opening']);
        $this->assertSame(2500.0, $balances['cash']['closing']);
    }
}
