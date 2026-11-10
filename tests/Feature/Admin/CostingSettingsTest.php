<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Services\SettingService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class CostingSettingsTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_admin_can_view_costing_settings_page(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get('/admin/costing-settings')
            ->assertOk()
            ->assertSee('إعدادات التكاليف الإضافية', false);
    }

    public function test_admin_can_update_overhead_rates(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->putJson(route('admin.costing-settings.update'), [
                SettingService::KEY_TECHNICAL_CHECK => 30,
                SettingService::KEY_COMPONENTS_INTEGRATION => 25,
                SettingService::KEY_MACHINERY_DEPRECIATION => 23,
                SettingService::KEY_REHABILITATION_ASSESSMENT => 22,
            ])
            ->assertOk()
            ->assertJsonPath('rates_sum', 100);

        $this->assertSame('30', Setting::where('key', SettingService::KEY_TECHNICAL_CHECK)->value('value'));
    }

    public function test_update_accepts_independent_rates_not_summing_to_100(): void
    {
        $admin = $this->userWithRole('admin');

        // كل بند نسبة مستقلة من المواد (زي حسبة اللائحة: 25% + 24.66% + 15% + 23%).
        $this->actingAs($admin)
            ->putJson(route('admin.costing-settings.update'), [
                SettingService::KEY_TECHNICAL_CHECK => 25,
                SettingService::KEY_COMPONENTS_INTEGRATION => 24.66,
                SettingService::KEY_MACHINERY_DEPRECIATION => 15,
                SettingService::KEY_REHABILITATION_ASSESSMENT => 23,
            ])
            ->assertOk();

        $this->assertSame('24.66', Setting::where('key', SettingService::KEY_COMPONENTS_INTEGRATION)->value('value'));
    }

    public function test_admin_can_save_costing_modes_and_components(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->putJson(route('admin.costing-modes.update'), [
                'modes' => [
                    [
                        'key' => 'prosthetic_limb',
                        'label' => 'طرف صناعي',
                        'profit_rate' => 90,
                        'has_components' => true,
                        'components' => [
                            ['label' => 'فحص فني', 'rate' => 40],
                            ['label' => 'دمج مكونات', 'rate' => 60],
                        ],
                    ],
                    [
                        'key' => 'quick_dispense',
                        'label' => 'الصرف السريع',
                        'profit_rate' => 35,
                        'has_components' => false,
                        'components' => [],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('costing_modes.0.key', 'prosthetic_limb')
            ->assertJsonPath('costing_modes.1.has_components', false);

        $this->assertDatabaseHas('costing_modes', ['key' => 'prosthetic_limb', 'profit_rate' => 90]);
        $this->assertDatabaseHas('costing_components', ['label' => 'فحص فني', 'rate' => 40]);
        $this->assertDatabaseCount('costing_components', 2);
    }

    public function test_costing_modes_rejects_invalid_key(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->putJson(route('admin.costing-modes.update'), [
                'modes' => [
                    [
                        'key' => 'Bad Key!',
                        'label' => 'خطأ',
                        'profit_rate' => 10,
                        'has_components' => false,
                        'components' => [],
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['modes.0.key']);
    }
}
