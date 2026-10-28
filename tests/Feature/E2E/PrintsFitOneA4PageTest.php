<?php

namespace Tests\Feature\E2E;

use App\Models\Role;
use App\Models\WorkshopSection;
use App\Support\Journeys\CaseJourneyRunner;
use Tests\Support\ProstheticTestCase;

/**
 * كل وثيقة تُطبع على ورقة A4 واحدة: كل صفحات الطباعة تحمّل print-fit.js (يصغّر المحتوى الطويل)
 * وحد الارتفاع = المساحة الفعلية بعد هوامش @page (270مم) — اتقاس في Chrome: أمر الشغل وإذن الصرف كانوا ورقتين.
 */
class PrintsFitOneA4PageTest extends ProstheticTestCase
{
    public function test_every_print_document_loads_the_one_page_fit(): void
    {
        $this->userWithRole('admin');
        $tech = $this->userWithRole(Role::SLUG_WORKSHOP);
        WorkshopSection::create(['name' => 'قسم', 'code' => 'l', 'sort' => 1, 'active' => true])->technicians()->attach($tech->id);
        $item = $this->stockItem('1101', qty: 30);
        $item->update(['uom' => 'عدد', 'price' => 2500]);

        $runner = new CaseJourneyRunner([['item' => $item->fresh(), 'qty' => 1.0]]);
        $case = $runner->run(CaseJourneyRunner::JOURNEY_CASH, 'مريض طباعة');

        $docs = $runner->printDocuments($case);
        $this->assertGreaterThanOrEqual(6, count($docs));
        $admin = CaseJourneyRunner::userFor('admin');
        foreach ($docs as $doc) {
            $html = $this->actingAs($admin)->get($doc['path'])->getContent();
            $this->assertStringContainsString('assets/js/shared/print-fit.js', $html, $doc['label']);
        }

        $quote = $this->actingAs($admin)->get("/admin/cases/{$case->id}/quote")->getContent();
        $this->assertStringContainsString('data-fit-mm="294"', $quote);
        $this->assertStringContainsString('max-height: 270mm', file_get_contents(public_path('assets/css/print-a4.css')));
    }
}
