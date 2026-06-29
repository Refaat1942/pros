<?php

namespace Tests\Unit;

use App\Enums\StockWarehouseType;
use App\Models\Bom;
use Tests\TestCase;

class StockWarehouseTypeTest extends TestCase
{
    public function test_bom_stages_map_to_three_warehouse_types(): void
    {
        $this->assertSame(StockWarehouseType::Raw, StockWarehouseType::fromBomStage(Bom::STAGE_RAW));
        $this->assertSame(StockWarehouseType::Production, StockWarehouseType::fromBomStage(Bom::STAGE_WIP));
        $this->assertSame(StockWarehouseType::Delivery, StockWarehouseType::fromBomStage(Bom::STAGE_FINISHED));
    }

    public function test_labels_match_client_terminology(): void
    {
        $this->assertSame(StockWarehouseType::Raw->label(), StockWarehouseType::labelForBomStage(Bom::STAGE_RAW));
        $this->assertStringContainsString('خام', StockWarehouseType::Raw->label());
        $this->assertStringContainsString('إنتاج', StockWarehouseType::Production->label());
        $this->assertStringContainsString('تسليم', StockWarehouseType::Delivery->label());
    }
}
