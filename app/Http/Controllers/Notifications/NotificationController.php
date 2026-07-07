<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * إدارة قراءة الإشعارات + بثّ خفيف (feed) للوحة للإظهار المنبثق في كل الشاشات.
 */
class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * بثّ إشعارات الدور الحالي — عدد غير المقروء + آخر العناصر.
     * تستهلكه سكربت الـ polling لإظهار Toast وتحديث عدّاد الجرس في كل شاشة.
     */
    public function feed(Request $request): JsonResponse
    {
        $roleSlug = Auth::user()?->role?->slug;

        if (! $roleSlug) {
            return response()->json(['unread_count' => 0, 'items' => []]);
        }

        $unreadCount = AppNotification::forRole($roleSlug)->unread()->count();

        $items = AppNotification::forRole($roleSlug)
            ->unread()
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn (AppNotification $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'body' => $n->body,
                'url' => is_array($n->data) ? ($n->data['url'] ?? null) : null,
                'created_at' => $n->created_at?->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'unread_count' => $unreadCount,
            'items' => $items,
        ]);
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
