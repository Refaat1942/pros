<?php

namespace Tests\Unit;

use App\Models\BomItem;
use App\Support\BomItemAggregator;
use Tests\TestCase;

class BomItemAggregatorTest extends TestCase
{
    public function test_merges_duplicate_stock_codes(): void
    {
        $items = [
            new BomItem([
                'stock_item_code' => 'ITM-010',
                'name'            => 'مفصل كوع',
                'qty'             => 1,
                'issued_qty'      => 0,
                'returned_qty'    => 0,
            ]),
            new BomItem([
                'stock_item_code' => 'ITM-010',
                'name'            => 'مفصل كوع',
                'qty'             => 1,
                'issued_qty'      => 0,
                'returned_qty'    => 0,
            ]),
        ];

        $merged = BomItemAggregator::byStockCode($items);

        $this->assertCount(1, $merged);
        $this->assertSame('ITM-010', $merged[0]['stock_item_code']);
        $this->assertSame(2, $merged[0]['qty']);
    }
}
