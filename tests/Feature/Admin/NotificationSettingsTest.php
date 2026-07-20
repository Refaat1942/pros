<?php

namespace Tests\Feature\Admin;

use App\Services\SettingService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class NotificationSettingsTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_super_admin_can_update_notification_alert_settings(): void
    {
        $super = $this->userWithRole('super_admin');

        $this->actingAs($super)
            ->putJson('/admin/notification-settings', [
                'sound_enabled' => true,
                'reminder_minutes' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('notification_alerts.reminder_minutes', 3)
            ->assertJsonPath('notification_alerts.sound_enabled', true);

        $alerts = app(SettingService::class)->notificationAlerts();
        $this->assertSame(3, $alerts['reminder_minutes']);
        $this->assertTrue($alerts['sound_enabled']);
    }

    public function test_non_super_admin_cannot_update_notification_alert_settings(): void
    {
        $reception = $this->userWithRole('reception');

        $this->actingAs($reception)
            ->putJson('/admin/notification-settings', [
                'sound_enabled' => false,
                'reminder_minutes' => 5,
            ])
            ->assertForbidden();
    }

    public function test_notification_feed_includes_alert_settings(): void
    {
        app(SettingService::class)->updateNotificationAlerts([
            'sound_enabled' => true,
            'reminder_minutes' => 2,
        ]);

        $user = $this->userWithRole('reception');

        $this->actingAs($user)
            ->getJson('/notifications/feed?dashboard=reception')
            ->assertOk()
            ->assertJsonPath('reminder_minutes', 2)
            ->assertJsonPath('sound_enabled', true);
    }

    public function test_super_admin_feed_uses_dashboard_role_for_all_transfers(): void
    {
        $super = $this->userWithRole('super_admin');

        \App\Models\AppNotification::query()->create([
            'role_slug' => 'spec',
            'title' => '🔧 حالة جديدة — التوصيف',
            'body' => 'المريض تجريبي — تم التحويل من الطبيب إلى التوصيف.',
        ]);

        \App\Models\AppNotification::query()->create([
            'role_slug' => 'operations',
            'title' => '🎯 حالة بانتظار التشغيل',
            'body' => 'حالة أخرى.',
        ]);

        $this->actingAs($super)
            ->getJson('/notifications/feed?dashboard=spec')
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        $this->actingAs($super)
            ->getJson('/notifications/feed?dashboard=operations')
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        $this->actingAs($super)
            ->getJson('/notifications/feed')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }
}
