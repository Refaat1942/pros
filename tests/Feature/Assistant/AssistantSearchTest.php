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
}
