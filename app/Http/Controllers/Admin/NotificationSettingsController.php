<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateNotificationSettingsRequest;
use App\Services\AuditService;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;

class NotificationSettingsController extends Controller
{
    public function __construct(private readonly SettingService $settings) {}

    public function update(UpdateNotificationSettingsRequest $request): JsonResponse
    {
        $before = $this->settings->notificationAlerts();

        $this->settings->updateNotificationAlerts([
            'sound_enabled' => $request->boolean('sound_enabled'),
            'reminder_minutes' => (int) $request->validated('reminder_minutes'),
        ]);

        $after = $this->settings->notificationAlerts();

        AuditService::log(
            action: 'update',
            description: 'تحديث إعدادات التنبيه الصوتي للإشعارات',
            tag: 'admin',
            before: $before,
            after: $after,
        );

        return response()->json([
            'message' => 'تم حفظ إعدادات التنبيه.',
            'notification_alerts' => $after,
        ]);
    }
}
