<?php

namespace Tests\Feature\Inventory;

use App\Models\StockItem;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestLine;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/** طلب توريد واحد (رقم واحد) بأكثر من صنف — والطريقة القديمة (بند واحد) زي ما هي. */
class SupplyRequestMultiItemTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_one_request_holds_several_items_with_one_number(): void
    {
        $a = $this->stockItem('RM-001', qty: 2);
        $b = $this->stockItem('RM-002', qty: 0);
        $qtyBefore = [(float) $a->qty, (float) $b->qty];

        $res = $this->actingAs($this->userWithRole('technical'))->postJson('/technical/supply/requests/batch', [
            'notes' => 'مطلوب خلال أسبوع',
            'lines' => [
                ['line_type' => 'catalog', 'stock_item_id' => $a->id, 'qty' => 5, 'uom' => 'قطعة'],
                ['line_type' => 'catalog', 'stock_item_id' => $b->id, 'qty' => 2, 'spec' => 'مقاس 27'],
                ['line_type' => 'non_catalog', 'description' => 'كرسي متحرك موديل X', 'qty' => 1],
            ],
        ])->assertCreated()->assertJsonCount(3, 'lines');

        $this->assertSame(1, SupplyRequest::count());
        $request = SupplyRequest::firstOrFail();
        $this->assertSame($res->json('request_no'), $request->request_no);
        $this->assertSame('مطلوب خلال أسبوع', $request->notes);
        $this->assertSame(3, $request->lines()->count());
        $this->assertSame(3, SupplyRequestLine::where('status', SupplyRequestLine::STATUS_PENDING)->count());
        // طلب التوريد مايغيرش الرصيد.
        $this->assertSame($qtyBefore, [(float) $a->fresh()->qty, (float) $b->fresh()->qty]);
    }

    public function test_batch_validation(): void
    {
        $user = $this->userWithRole('technical');

        $this->actingAs($user)->postJson('/technical/supply/requests/batch', ['lines' => []])
            ->assertStatus(422)->assertJsonValidationErrors('lines');
        $this->actingAs($user)->postJson('/technical/supply/requests/batch', ['lines' => [['line_type' => 'catalog', 'qty' => 1]]])
            ->assertStatus(422)->assertJsonValidationErrors('lines.0.stock_item_id');
        $this->assertSame(0, SupplyRequest::count());
    }

    public function test_single_item_route_still_creates_one_request_per_call(): void
    {
        $a = $this->stockItem('RM-001', qty: 2);
        $user = $this->userWithRole('technical');

        $this->actingAs($user)->postJson('/technical/supply/requests', ['line_type' => 'catalog', 'stock_item_id' => $a->id, 'qty' => 1])->assertCreated();
        $this->actingAs($user)->postJson('/technical/supply/requests', ['line_type' => 'catalog', 'stock_item_id' => $a->id, 'qty' => 2])->assertCreated();

        $this->assertSame(2, SupplyRequest::count());
        $this->assertSame(1, SupplyRequest::first()->lines()->count());
    }
}
