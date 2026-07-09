<?php

namespace Tests\Feature\Admin;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class BrandingSettingsTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_admin_can_view_branding_settings_page(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get('/admin/branding-settings')
            ->assertOk()
            ->assertSee('الهوية البصرية', false);
    }

    public function test_admin_can_update_branding_settings(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');

        $logo = UploadedFile::fake()->image('logo.png', 200, 200);

        $this->actingAs($admin)
            ->put('/admin/branding-settings', [
                'center_name' => 'مركز تجريبي',
                'header_lines' => "سطر 1\nسطر 2",
                'logo' => $logo,
            ])
            ->assertOk()
            ->assertJsonPath('branding.center_name', 'مركز تجريبي');

        $this->assertTrue(Storage::disk('public')->exists('branding/logo.png'));

        $this->assertDatabaseHas('settings', [
            'key' => 'org_center_name',
            'value' => 'مركز تجريبي',
        ]);
    }
}
