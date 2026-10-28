<?php

namespace Tests\Feature\Reception;

use App\Models\Role;
use App\Models\WorkshopSection;
use App\Support\Journeys\CaseJourneyRunner;
use Tests\Support\ProstheticTestCase;

/** عرض السعر بدون عمود «البيان / المكونات» للأصناف — مدة التوريد والضمان بعنوانهم. */
class QuoteWithoutComponentsColumnTest extends ProstheticTestCase
{
    public function test_quote_items_span_the_table_without_components_column(): void
    {
        $this->userWithRole('admin');
        $technician = $this->userWithRole(Role::SLUG_WORKSHOP);
        WorkshopSection::create(['name' => 'قسم', 'code' => 'l', 'sort' => 1, 'active' => true])->technicians()->attach($technician->id);
        $knee = $this->stockItem('1101', qty: 30);
        $knee->update(['uom' => 'عدد', 'price' => 2500]);
        $case = (new CaseJourneyRunner([['item' => $knee->fresh(), 'qty' => 1.0]]))->run(CaseJourneyRunner::JOURNEY_CASH, 'مريض عرض');

        $html = $this->actingAs(CaseJourneyRunner::userFor('admin'))->get("/admin/cases/{$case->id}/quote?embed=1")->assertOk()->getContent();

        $this->assertStringNotContainsString('البيان / المكونات', $html);
        $this->assertMatchesRegularExpression('/<th class="spec-head" colspan="2">/u', $html);
        $this->assertMatchesRegularExpression('/<td class="value" colspan="2">[^<]*'.preg_quote($knee->name, '/').'/u', $html);
    }
}
