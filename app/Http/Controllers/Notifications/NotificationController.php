<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * إدارة قراءة الإشعارات من صفحة الأرشيف (بلا polling).
 */
class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    /**
     * تعليم إشعار واحد كمقروء — للدور الحالي فقط.
     */
    public function markRead(AppNotification $notification): RedirectResponse
    {
        $this->authorizeForRole($notification);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return back()->with('status', 'تم تعليم الإشعار كمقروء.');
    }

    /**
     * تعليم كل إشعارات الدور الحالي كمقروءة.
     */
    public function markAllRead(Request $request): RedirectResponse
    {
        $roleSlug = Auth::user()?->role?->slug;

        $updated = $this->notifications->markAllReadForRole($roleSlug);

        return back()->with('status', $updated > 0
            ? "تم تعليم {$updated} إشعاراً كمقروء."
            : 'لا توجد إشعارات غير مقروءة.');
    }

    private function authorizeForRole(AppNotification $notification): void
    {
        $roleSlug = Auth::user()?->role?->slug;

        abort_unless($roleSlug && $notification->role_slug === $roleSlug, 403);
    }
}
