<?php

namespace Tests\Feature\Console;

use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\Role;
use App\Models\WorkshopSection;
use App\Support\Journeys\CaseJourneyRunner;
use Tests\Support\ProstheticTestCase;

class DemoJourneysCommandTest extends ProstheticTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // بعد prosthetics:purge-users يبقى مدير النظام فقط — الأمر يستخدمه لكل الأقسام.
        $this->userWithRole('admin');
        $technician = $this->userWithRole(Role::SLUG_WORKSHOP);
        WorkshopSection::create(['name' => 'قسم الأطراف', 'code' => 'limbs', 'sort' => 1, 'active' => true])
            ->technicians()->attach($technician->id);

        $knee = $this->stockItem('1101', qty: 30);
        $knee->update(['uom' => 'عدد', 'price' => 2500]);
        $tape = $this->stockItem('1102', qty: 30);
        $tape->update(['uom' => 'متر', 'price' => 40]);
    }

    public function test_dry_run_reaches_delivery_and_leaves_nothing_behind(): void
    {
        $this->artisan('prosthetics:demo-journeys', ['--dry-run' => true])
            ->expectsOutputToContain('5 من 5 حالات وصلت للتسليم')
            ->assertSuccessful();

        $this->assertSame(0, Patient::query()->count());
        $this->assertSame(0, CaseRecord::query()->count());
    }

    public function test_run_keeps_one_delivered_case_per_pathway(): void
    {
        $this->artisan('prosthetics:demo-journeys', ['--items' => '1101:1,1102:0.5'])->assertSuccessful();

        $this->assertSame(
            count(CaseJourneyRunner::journeys()),
            CaseRecord::query()->where('stage_key', CaseRecord::STAGE_DELIVERED)->count(),
        );
    }
}
