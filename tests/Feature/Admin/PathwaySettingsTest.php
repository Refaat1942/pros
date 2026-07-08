<?php

namespace Tests\Feature\Admin;

use App\Models\PathwayStep;
use App\Services\PathwayConfigService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class PathwaySettingsTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_admin_can_view_pathway_settings_page(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get('/admin/pathway-settings')
            ->assertOk()
            ->assertSee('ترقيم مسار العمل', false)
            ->assertSee('pathwaySettingsBootstrap', false);
    }

    public function test_admin_can_save_civilian_pathway_steps(): void
    {
        $admin = $this->userWithRole('admin');

        $payload = [
            'pathway' => PathwayStep::PATHWAY_CIVILIAN,
            'steps' => [
                [
                    'key' => 'reception',
                    'label' => 'استقبال مخصص',
                    'sort' => 1,
                    'stage_keys' => ['reception'],
                    'active' => true,
                    'description' => 'تسجيل',
                ],
                [
                    'key' => 'exam',
                    'label' => 'كشف',
                    'sort' => 2,
                    'stage_keys' => ['exam'],
                    'active' => true,
                ],
            ],
        ];

        $this->actingAs($admin)
            ->putJson(route('admin.pathway-settings.update'), $payload)
            ->assertOk()
            ->assertJsonPath('steps.0.label', 'استقبال مخصص');

        $this->assertDatabaseHas('pathway_steps', [
            'pathway' => PathwayStep::PATHWAY_CIVILIAN,
            'key' => 'reception',
            'label' => 'استقبال مخصص',
            'sort' => 1,
        ]);

        $steps = app(PathwayConfigService::class)->displaySteps(isMilitary: false);
        $this->assertSame('استقبال مخصص', $steps[0]['label']);
    }

    public function test_admin_can_reset_pathway_to_defaults(): void
    {
        $admin = $this->userWithRole('admin');
        $service = app(PathwayConfigService::class);

        $service->saveSteps(PathwayStep::PATHWAY_MILITARY, [
            [
                'key' => 'only',
                'label' => 'خطوة واحدة',
                'sort' => 1,
                'stage_keys' => ['reception'],
                'active' => true,
            ],
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.pathway-settings.reset'), [
                'pathway' => PathwayStep::PATHWAY_MILITARY,
            ])
            ->assertOk()
            ->assertJsonPath('steps.0.key', 'reception');

        $this->assertDatabaseMissing('pathway_steps', [
            'pathway' => PathwayStep::PATHWAY_MILITARY,
            'key' => 'only',
        ]);
    }

    public function test_pathway_update_rejects_invalid_key(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->putJson(route('admin.pathway-settings.update'), [
                'pathway' => PathwayStep::PATHWAY_CIVILIAN,
                'steps' => [
                    [
                        'key' => 'Bad Key!',
                        'label' => 'خطأ',
                        'sort' => 1,
                        'stage_keys' => ['reception'],
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['steps.0.key']);
    }
}
