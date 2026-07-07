<?php

namespace Tests\Feature\Reception;

use App\Services\ReceptionAnalyticsService;
use Tests\Support\DashboardQueueAssertions;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class ReceptionStatisticsPageTest extends TestCase
{
    use DashboardQueueAssertions;
    use ProstheticTestHelper;

    public function test_statistics_page_loads_for_reception_user(): void
    {
        $user = $this->userWithRole('reception');
        $this->actingAs($user);

        $this->get(route('reception.statistics'))
            ->assertOk()
            ->assertSee('لوحة إحصائيات الاستقبال')
            ->assertDontSee('تفاصيل عروض الأسعار')
            ->assertSee('receptionStatsRoot', false);
    }

    public function test_statistics_page_blocked_without_permission(): void
    {
        $user = $this->userWithRole('reception');
        $user->role->permissions()->detach();
        $this->actingAs($user->fresh());

        $this->get(route('reception.statistics'))->assertStatus(403);
    }

    public function test_analytics_service_returns_real_counts(): void
    {
        $company = $this->civilianCompany();
        $recep = $this->userWithRole('reception');

        $this->registerCivilianPatientHttp($recep, $company, 'مريض إحصائيات');

        $data = app(ReceptionAnalyticsService::class)->build();

        $this->assertSame('1', $data['stats'][0]['value']);
        $this->assertSame(1, $data['charts'][1]['items'][0]['value']);
        $this->assertNotEmpty($data['charts'][0]['items']);
    }
}
