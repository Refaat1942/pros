<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Models\UserDevice;

/**
 * تسجيل أجهزة المستخدمين (FCM tokens) — يُستدعى عند تسجيل الدخول
 * أو عند تحديث التوكن من اللوحة.
 */
class DeviceService
{
    /**
     * يسجّل/يحدّث جهاز المستخدم. يربط التوكن بالمستخدم الحالي (upsert بالتوكن).
     */
    public function register(User $user, ?string $deviceId, ?string $deviceType = null): ?UserDevice
    {
        $deviceId = trim((string) $deviceId);

        if ($deviceId === '') {
            return null;
        }

        return UserDevice::updateOrCreate(
            ['device_id' => $deviceId],
            [
                'user_id' => $user->id,
                'device_type' => $this->normalizeType($deviceType),
                'last_used_at' => now(),
            ],
        );
    }

    private function normalizeType(?string $type): string
    {
        $type = strtolower(trim((string) $type));

        return in_array($type, [UserDevice::TYPE_ANDROID, UserDevice::TYPE_IOS, UserDevice::TYPE_WEB], true)
            ? $type
            : UserDevice::TYPE_WEB;
    }
}
