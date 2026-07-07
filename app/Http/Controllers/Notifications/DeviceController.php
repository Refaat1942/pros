<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Services\Notifications\DeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * تسجيل/تحديث جهاز المستخدم (FCM token) من اللوحة — لتجديد التوكن
 * أو تسجيله إن مُنح إذن الإشعارات بعد الدخول.
 */
class DeviceController extends Controller
{
    public function __construct(private readonly DeviceService $deviceService) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'string', 'max:512'],
            'device_type' => ['nullable', 'string', 'in:web,android,ios'],
        ]);

        $device = $this->deviceService->register(
            Auth::user(),
            $validated['device_id'],
            $validated['device_type'] ?? 'web',
        );

        return response()->json(['ok' => $device !== null]);
    }
}
