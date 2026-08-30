<?php

namespace Tests\Support;

use App\Services\BiReportService;

trait StubsBiReportForOverview
{
    /** @param array<string, mixed> $inventoryBoard */
    /** @param array<string, mixed> $operationsBoard */
    protected function stubBiReportServiceForOverview(
        array $inventoryBoard = ['item_count' => 0, 'low_stock' => 0, 'stagnant_items' => [], 'total_value' => 0],
        array $operationsBoard = ['open_work_orders' => 0, 'awaiting_assignment' => 0, 'awaiting_dispense' => 0, 'ready_for_dispense' => 0, 'in_workshop' => 0, 'ready_for_delivery' => 0],
    ): void {
        $mock = $this->mock(BiReportService::class);

        $mock->shouldReceive('boardPatients')->andReturn([
            'total_cases' => 0,
            'civilian_count' => 0,
            'military_count' => 0,
            'open_count' => 0,
            'sla_breached' => 0,
            'sla_breached_cases' => [],
        ]);
        $mock->shouldReceive('boardInventory')->andReturn($inventoryBoard);
        $mock->shouldReceive('boardOperations')->andReturn($operationsBoard);
        $mock->shouldReceive('boardFinanceCash')->andReturn([
            'cash_collected_total' => 0,
            'cash_awaiting_payment' => 0,
        ]);
        $mock->shouldReceive('boardFinanceCivilianDebt')->andReturn([
            'net_debts' => 0,
            'company_debts' => [],
        ]);
        $mock->shouldReceive('boardFinanceRevenueCost')->andReturn([
            'civilian_cumulative_cost' => 0,
            'civilian_delivered_wac_cost' => 0,
        ]);
        $mock->shouldReceive('boardFinanceMilitary')->andReturn([
            'military_aggregated_cost' => 0,
            'military_delivered_wac_cost' => 0,
            'military_debt_pending' => 0,
            'military_debt_collected' => 0,
        ]);
        $mock->shouldReceive('boardFinanceContractsCompanies')->andReturn([
            'contracted_companies' => 0,
            'companies_total' => 0,
        ]);
        $mock->shouldReceive('boardEntitiesAndCosts')->andReturn([
            'cash_collected_total' => 0,
            'cash_awaiting_payment' => 0,
            'net_debts' => 0,
            'civilian_cumulative_cost' => 0,
            'military_aggregated_cost' => 0,
            'military_debt_pending' => 0,
            'military_debt_collected' => 0,
        ]);
        $mock->shouldReceive('boardPurchasing')->andReturn([
            'supplier_count' => 0,
            'price_comparison' => [],
        ]);
    }
}
