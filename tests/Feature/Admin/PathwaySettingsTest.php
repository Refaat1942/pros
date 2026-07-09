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
            ->assertSee('مصمم مسار العمل', false)
            ->assertSee('pathwayDesignerBootstrap', false)
            ->assertSee('pathwayMatrixWrap', false)
            ->assertSee('pathwayStepEditor', false)
            ->assertSee('pathway-designer.js', false)
            ->assertSee('جدول المسارات الثلاثة', false);
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

    public function test_default_pathways_match_official_order(): void
    {
        $service = app(PathwayConfigService::class);

        $service->resetToDefaults(PathwayStep::PATHWAY_CIVILIAN);
        $civilian = $service->steps(PathwayStep::PATHWAY_CIVILIAN);
        $this->assertCount(11, $civilian);
        $this->assertSame('warehouse', $civilian[6]['key']);
        $this->assertSame('delivery', $civilian[10]['key']);

        $service->resetToDefaults(PathwayStep::PATHWAY_MILITARY);
        $military = $service->steps(PathwayStep::PATHWAY_MILITARY);
        $this->assertCount(11, $military);
        $this->assertTrue($military[9]['auto_skip'] ?? false, 'الخزنة تُتخطى في المسار العسكري');

        $service->resetToDefaults(PathwayStep::PATHWAY_ENTITY);
        $entity = $service->steps(PathwayStep::PATHWAY_ENTITY);
        $this->assertCount(13, $entity);
        $this->assertSame('quote', $entity[5]['key']);
        $this->assertSame('entity_return', $entity[6]['key']);
    }

    public function test_admin_can_reset_entity_pathway_to_defaults(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->postJson(route('admin.pathway-settings.reset'), [
                'pathway' => PathwayStep::PATHWAY_ENTITY,
            ])
            ->assertOk()
            ->assertJsonPath('steps.5.key', 'quote');
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
