<?php

namespace Tests\Feature\Notifications;

use App\Models\AppNotification;
use App\Models\CaseRecord;
use App\Models\Role;
use App\Models\WorkshopSection;
use App\Support\Journeys\CaseJourneyRunner;
use Tests\Support\ProstheticTestCase;

/**
 * رسائل التحويل وإشعاراتها عبر المسارات الخمسة — كل قسم يُشعَر عندما تصله الحالة فعلاً، لا قبل ولا بعد.
 */
class TransferNotificationsJourneyTest extends ProstheticTestCase
{
    /** @var array<string, CaseRecord> */
    private array $cases = [];

    /** @var array<string, list<AppNotification>> */
    private array $notes = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->userWithRole('admin');
        $technician = $this->userWithRole(Role::SLUG_WORKSHOP);
        WorkshopSection::create(['name' => 'قسم', 'code' => 'l', 'sort' => 1, 'active' => true])
            ->technicians()->attach($technician->id);
        $knee = $this->stockItem('1101', qty: 30);
        $knee->update(['uom' => 'عدد', 'price' => 2500]);

        $runner = new CaseJourneyRunner([['item' => $knee->fresh(), 'qty' => 1.0]]);
        foreach (CaseJourneyRunner::journeys() as $journey) {
            $before = (int) AppNotification::max('id');
            $this->cases[$journey] = $runner->run($journey, 'مريض '.$journey);
            $this->notes[$journey] = AppNotification::where('id', '>', $before)->orderBy('id')->get()->all();
        }
    }

    /** @return list<string> */
    private function titlesFor(string $journey, string $role): array
    {
        return array_values(array_map(
            fn (AppNotification $n) => $n->title,
            array_filter($this->notes[$journey], fn (AppNotification $n) => $n->role_slug === $role),
        ));
    }

    public function test_entity_operations_notified_for_quote_then_after_approval_letter_not_before(): void
    {
        foreach ([CaseJourneyRunner::JOURNEY_ENTITY_CONTRACTED, CaseJourneyRunner::JOURNEY_ENTITY_NON_CONTRACTED] as $journey) {
            $ops = $this->titlesFor($journey, Role::SLUG_OPERATIONS);

            $this->assertCount(2, $ops, $journey.': '.implode(' / ', $ops));
            $this->assertStringContainsString('إصدار عرض سعر', $ops[0]);
            $this->assertStringContainsString('موافقة الجهة وصلت', $ops[1]);
        }
    }

    public function test_military_auto_skipped_and_auto_approved_desks_get_no_actionable_notification(): void
    {
        foreach ([CaseJourneyRunner::JOURNEY_MILITARY, CaseJourneyRunner::JOURNEY_MILITARY_SERVICES] as $journey) {
            $this->assertSame([], $this->titlesFor($journey, Role::SLUG_ADJUSTMENTS), "{$journey}: adjustments is auto-skipped");

            $unreadOps = AppNotification::forRole(Role::SLUG_OPERATIONS)->unread()
                ->where('case_id', $this->cases[$journey]->id)->count();
            $this->assertSame(0, $unreadOps, "{$journey}: work order is issued automatically");
        }
    }

    public function test_every_department_on_the_route_is_notified(): void
    {
        $expected = [
            CaseJourneyRunner::JOURNEY_CASH => ['doctor', 'spec', 'adjustments', 'costing', 'cashier', 'operations', 'workshop', 'technical', 'reception'],
            CaseJourneyRunner::JOURNEY_ENTITY_CONTRACTED => ['doctor', 'spec', 'adjustments', 'costing', 'operations', 'reception', 'workshop', 'technical'],
            CaseJourneyRunner::JOURNEY_MILITARY => ['doctor', 'spec', 'costing', 'workshop', 'technical', 'reception'],
            CaseJourneyRunner::JOURNEY_MILITARY_SERVICES => ['doctor', 'spec', 'costing', 'admin', 'workshop', 'technical', 'reception'],
        ];

        foreach ($expected as $journey => $roles) {
            $got = array_unique(array_map(fn (AppNotification $n) => $n->role_slug, $this->notes[$journey]));
            foreach ($roles as $role) {
                $this->assertContains($role, $got, "{$journey}: {$role} must be notified");
            }
        }
    }

    public function test_messages_are_clean(): void
    {
        foreach ($this->notes as $journey => $notes) {
            foreach ($notes as $n) {
                $this->assertStringNotContainsString('..', $n->body, "{$journey}: {$n->body}");
                $this->assertDoesNotMatchRegularExpression('/من (.+) إلى \1\./u', $n->body, "{$journey}: {$n->body}");
            }

            $delivered = collect($notes)->first(fn ($n) => str_contains($n->title, 'تم تسليم'));
            $this->assertNotNull($delivered);
            $this->assertStringContainsString('تم تسليم الطرف للمريض وإغلاق الحالة', $delivered->body);
        }
    }
}
