<?php

namespace Tests\Support;

use App\Services\BiReportService;

trait StubsBiReportForOverview
{
    /** @param array<string, mixed> $inventoryBoard */
    /** @param array<string, mixed> $operationsBoard */
    protected function stubBiReportServiceForOverview(
        array $inventoryBoard = ['item_count' => 0, 'low_stock' => 0, 'stagnant_items' => [], 'total_value' => 0],
        array $operationsBoard = ['open_work_orders' => 0, 'awaiting_dispense' => 0, 'in_workshop' => 0, 'ready_for_delivery' => 0],
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
        $mock->shouldReceive('boardEntitiesAndCosts')->andReturn([
            'civilian_cost' => 0,
            'military_cost' => 0,
            'net_debt' => 0,
            'top_debtors' => [],
        ]);
        $mock->shouldReceive('boardPurchasing')->andReturn([
            'supplier_count' => 0,
            'price_compare' => [],
        ]);
    }
}
