<?php

namespace Tests\Feature\Assistant;

use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class AssistantSearchTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_search_returns_contextual_results_for_role(): void
    {
        $user = $this->userWithRole('reception');

        $response = $this->actingAs($user)->getJson(
            '/assistant/search?q='.urlencode('عرض السعر').'&dashboard=reception&page=quote'
        );

        $response->assertOk();
        $results = $response->json('results');

        $this->assertNotEmpty($results);
        $this->assertContains('reception', array_column($results, 'dashboard'));
    }

    public function test_general_entries_available_to_any_role(): void
    {
        $user = $this->userWithRole('workshop');

        $response = $this->actingAs($user)->getJson(
            '/assistant/search?q='.urlencode('اطبع')
        );

        $response->assertOk();
        $dashboards = array_column($response->json('results'), 'dashboard');

        $this->assertContains('*', $dashboards);
    }

    public function test_admin_only_finance_help_hidden_from_non_admin(): void
    {
        $user = $this->userWithRole('reception');

        $response = $this->actingAs($user)->getJson(
            '/assistant/search?q='.urlencode('الربحية والتكاليف')
        );

        $response->assertOk();
        $dashboards = array_column($response->json('results'), 'dashboard');

        $this->assertNotContains('admin', $dashboards);
    }

    public function test_admin_sees_finance_help(): void
    {
        $user = $this->userWithRole('admin');

        $response = $this->actingAs($user)->getJson(
            '/assistant/search?q='.urlencode('مراجعة التكاليف والربحية')
        );

        $response->assertOk();
        $dashboards = array_column($response->json('results'), 'dashboard');

        $this->assertContains('admin', $dashboards);
    }

    public function test_suggestions_returned_without_query(): void
    {
        $user = $this->userWithRole('reception');

        $response = $this->actingAs($user)->getJson(
            '/assistant/search?dashboard=reception&page=appointments'
        );

        $response->assertOk();
        $this->assertNotEmpty($response->json('results'));
    }

    public function test_diagram_returned_when_user_asks_for_drawing(): void
    {
        $user = $this->userWithRole('reception');

        $response = $this->actingAs($user)->getJson(
            '/assistant/search?q='.urlencode('ارسم مسار الحالة')
        );

        $response->assertOk();
        $results = $response->json('results');

        $this->assertNotEmpty($results);
        $this->assertNotNull($results[0]['diagram'] ?? null);
        $this->assertNotEmpty($results[0]['diagram']);
    }

    public function test_military_diagram_when_query_mentions_military(): void
    {
        $user = $this->userWithRole('admin');

        $response = $this->actingAs($user)->getJson(
            '/assistant/search?q='.urlencode('مخطط المسار العسكري')
        );

        $response->assertOk();
        $results = $response->json('results');

        $this->assertNotEmpty($results);
        $diagram = $results[0]['diagram'] ?? [];
        $labels = implode(' ', array_column($diagram, 'label'));
        $this->assertStringContainsString('تصديق', $labels);
    }

    public function test_catalog_fills_pages_without_static_knowledge(): void
    {
        $user = $this->userWithRole('admin');

        $response = $this->actingAs($user)->getJson(
            '/assistant/search?q='.urlencode('الموردون')
        );

        $response->assertOk();
        $this->assertNotEmpty($response->json('results'));
    }

    public function test_dept_staff_help_for_operations_manager(): void
    {
        $user = $this->userWithRole('operations');
        $user->update(['access_tier' => \App\Services\UserPageAccessService::TIER_DEPARTMENT_ADMIN]);

        $response = $this->actingAs($user)->getJson(
            '/assistant/search?q='.urlencode('موظفي القسم').'&dashboard=operations&page=staff'
        );

        $response->assertOk();
        $titles = array_column($response->json('results'), 'title');
        $this->assertTrue(
            collect($titles)->contains(fn (string $t) => str_contains($t, 'موظفي القسم')),
            'Expected dept staff help in results: '.implode(', ', $titles)
        );
    }

    public function test_barcode_label_help_available(): void
    {
        $user = $this->userWithRole('admin');

        $response = $this->actingAs($user)->getJson(
            '/assistant/search?q='.urlencode('طباعة ملصق باركود حراري')
        );

        $response->assertOk();
        $answers = array_column($response->json('results'), 'answer');
        $this->assertNotEmpty($answers);
        $this->assertTrue(
            collect($answers)->contains(fn (string $a) => str_contains($a, 'حراري') || str_contains($a, 'باركود')),
        );
    }

    public function test_assistant_knows_price_tier_fifo_and_reports(): void
    {
        $user = $this->userWithRole('admin');

        $fifo = $this->actingAs($user)->getJson(
            '/assistant/search?q='.urlencode('صرف بأسعار متعددة')
        );
        $fifo->assertOk();
        $titles = array_column($fifo->json('results'), 'title');
        $this->assertContains('صرف المخزن بأسعار متعددة (دفعات FIFO)', $titles);

        $reports = $this->actingAs($user)->getJson(
            '/assistant/search?q='.urlencode('أرصدة مستويات السعر').'&dashboard=admin&page=reports'
        );
        $reports->assertOk();
        $this->assertContains(
            'تقارير مستويات السعر والأرصدة',
            array_column($reports->json('results'), 'title')
        );

        $docs = $this->actingAs($user)->getJson(
            '/assistant/search?q='.urlencode('مركز الوثائق').'&dashboard=admin&page=documents-hub'
        );
        $docs->assertOk();
        $this->assertContains(
            'مركز الوثائق والطباعة',
            array_column($docs->json('results'), 'title')
        );
    }

    public function test_assistant_offline_help_entry(): void
    {
        $user = $this->userWithRole('reception');

        $response = $this->actingAs($user)->getJson(
            '/assistant/search?q='.urlencode('المساعد الذكي اوفلاين')
        );

        $response->assertOk();
        $titles = array_column($response->json('results'), 'title');
        $this->assertContains('المساعد الذكي (أونلاين وأوفلاين)', $titles);
    }
}
