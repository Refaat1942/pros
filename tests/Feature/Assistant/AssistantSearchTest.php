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
}
