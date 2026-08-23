<?php

namespace Tests\Feature\Admin;

use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class WorkshopSectionsPageTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_admin_workshop_sections_page_renders_structured_layout(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.workshop-sections'))
            ->assertOk()
            ->assertSee('workshop-sections-page', false)
            ->assertSee('bom-table', false)
            ->assertSee('ws-tabs-wrap', false)
            ->assertSee('ws-tab-label', false)
            ->assertSee('id="wsTabTechnicians" class="ws-tab-panel" role="tabpanel" hidden', false)
            ->assertDontSee('space-y-4', false);
    }
}
