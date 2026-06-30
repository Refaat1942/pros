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
                SettingService::KEY_TECHNICAL_CHECK         => 30,
                SettingService::KEY_COMPONENTS_INTEGRATION  => 25,
                SettingService::KEY_MACHINERY_DEPRECIATION  => 23,
                SettingService::KEY_REHABILITATION_ASSESSMENT => 22,
            ])
            ->assertOk()
            ->assertJsonPath('rates_sum', 100);

        $this->assertSame('30', Setting::where('key', SettingService::KEY_TECHNICAL_CHECK)->value('value'));
    }

    public function test_update_rejects_rates_not_summing_to_100(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->putJson(route('admin.costing-settings.update'), [
                SettingService::KEY_TECHNICAL_CHECK         => 30,
                SettingService::KEY_COMPONENTS_INTEGRATION  => 25,
                SettingService::KEY_MACHINERY_DEPRECIATION  => 23,
                SettingService::KEY_REHABILITATION_ASSESSMENT => 20,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rates_sum']);
    }
}
