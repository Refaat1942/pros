<?php

namespace Tests\Feature\E2E;

use App\Models\CaseRecord;
use App\Models\Role;
use App\Models\WorkshopSection;
use App\Support\Journeys\CaseJourneyRunner;
use Tests\Support\ProstheticTestCase;

/**
 * الفاتورة الختامية: كل حالة مسلَّمة (كل المسارات) لها رقم فاتورة فريد،
 * و«عرض» في متابعة الحالات يفتح الفاتورة لا عرض السعر.
 */
class FinalInvoicePrintTest extends ProstheticTestCase
{
    /** @var array<string, CaseRecord> */
    private array $cases = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->userWithRole('admin');
        $technician = $this->userWithRole(Role::SLUG_WORKSHOP);
        WorkshopSection::create(['name' => 'قسم الأطراف', 'code' => 'limbs', 'sort' => 1, 'active' => true])
            ->technicians()->attach($technician->id);

        $knee = $this->stockItem('1101', qty: 30);
        $knee->update(['uom' => 'عدد', 'price' => 2500]);

        $runner = new CaseJourneyRunner([['item' => $knee->fresh(), 'qty' => 1.0]]);
        foreach (CaseJourneyRunner::journeys() as $journey) {
            $this->cases[$journey] = $runner->run($journey, 'فاتورة — '.CaseJourneyRunner::label($journey));
        }
    }

    public function test_every_delivered_pathway_has_a_unique_invoice_number(): void
    {
        $numbers = collect($this->cases)->map(fn (CaseRecord $c) => $c->fresh()->invoice_no);

        $this->assertNotContains(null, $numbers->all());
        $this->assertSame($numbers->count(), $numbers->unique()->count());
        foreach ($numbers as $no) {
            $this->assertMatchesRegularExpression('/^INV-\d{4}-\d{4}$/', $no);
        }
    }

    public function test_invoice_print_shows_invoice_not_quote_for_all_pathways(): void
    {
        $admin = CaseJourneyRunner::userFor('admin');

        foreach ($this->cases as $journey => $case) {
            $case = $case->fresh('patient');

            $this->actingAs($admin)
                ->get("/admin/cases/{$case->id}/invoice?embed=1")
                ->assertOk()
                ->assertSee('<title>فاتورة', false)
                ->assertSee('رقم الفاتورة', false)
                ->assertSee($case->invoice_no, false)
                ->assertSee((string) $case->patient->patient_code, false)
                ->assertSee('تاريخ الفاتورة (التسليم)', false)
                ->assertDontSee('مدة سريان عرض السعر', false);
        }
    }

    public function test_cash_invoice_shows_paid_and_zero_remaining(): void
    {
        $case = $this->cases[CaseJourneyRunner::JOURNEY_CASH]->fresh();

        $this->actingAs(CaseJourneyRunner::userFor('admin'))
            ->get("/admin/cases/{$case->id}/invoice?embed=1")
            ->assertOk()
            ->assertSee('المدفوع بالخزنة', false)
            ->assertSee('المتبقي', false)
            ->assertSee('المريض — نقداً بالخزنة', false);
    }

    public function test_military_invoice_is_charged_to_sovereign_entity(): void
    {
        $case = $this->cases[CaseJourneyRunner::JOURNEY_MILITARY]->fresh();

        $this->actingAs(CaseJourneyRunner::userFor('admin'))
            ->get("/admin/cases/{$case->id}/invoice?embed=1")
            ->assertOk()
            ->assertSee('بدون تحصيل من المريض', false)
            ->assertDontSee('المدفوع بالخزنة', false);
    }

    public function test_case_detail_points_delivered_cases_to_the_invoice(): void
    {
        $case = $this->cases[CaseJourneyRunner::JOURNEY_ENTITY_CONTRACTED]->fresh();

        $this->actingAs(CaseJourneyRunner::userFor('admin'))
            ->getJson("/admin/cases/{$case->id}/detail")
            ->assertOk()
            ->assertJsonPath('invoice.invoice_no', $case->invoice_no)
            ->assertJsonPath('invoice.print_url', route('admin.cases.invoice', $case));
    }

    public function test_invoice_is_not_available_before_delivery(): void
    {
        $case = $this->cases[CaseJourneyRunner::JOURNEY_CASH];
        CaseRecord::whereKey($case->id)->update(['stage_key' => CaseRecord::STAGE_MANUFACTURING]);

        $this->actingAs(CaseJourneyRunner::userFor('admin'))
            ->get("/admin/cases/{$case->id}/invoice")
            ->assertNotFound();
    }
}
