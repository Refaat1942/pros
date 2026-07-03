<?php

namespace Tests\Unit;

use App\Support\SpecEditRequestItemDiff;
use PHPUnit\Framework\TestCase;

class SpecEditRequestItemDiffTest extends TestCase
{
    public function test_detects_removed_items_when_proposed_is_empty(): void
    {
        $original = [
            ['stock_item_code' => 'ITM-008', 'name' => 'بطانة Gel', 'qty' => 1],
        ];

        $modified = SpecEditRequestItemDiff::modifiedItems($original, []);

        $this->assertCount(1, $modified);
        $this->assertSame('removed', $modified[0]['change']);
        $this->assertSame('ITM-008', $modified[0]['stock_item_code']);
        $this->assertSame('بطانة Gel', $modified[0]['name']);
        $this->assertStringContainsString('حذف: بطانة Gel', SpecEditRequestItemDiff::summaryLine($modified[0]));
    }

    public function test_detects_added_and_updated_items(): void
    {
        $original = [
            ['stock_item_code' => 'ITM-001', 'name' => 'ركبة', 'qty' => 1],
        ];
        $proposed = [
            ['stock_item_code' => 'ITM-001', 'name' => 'ركبة', 'qty' => 2],
            ['stock_item_code' => 'ITM-008', 'name' => 'بطانة Gel', 'qty' => 1],
        ];

        $modified = SpecEditRequestItemDiff::modifiedItems($original, $proposed);

        $this->assertCount(2, $modified);
        $this->assertSame('updated', $modified[0]['change']);
        $this->assertSame(2, $modified[0]['qty']);
        $this->assertSame(1, $modified[0]['previous_qty']);
        $this->assertSame('added', $modified[1]['change']);
    }
}
