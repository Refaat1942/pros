<?php

namespace App\Services;

use App\Models\AuditLog;

/**
 * سجل الرقابة الحصين — Append-Only
 *
 * استخدم هذا الكلاس في نهاية كل Service mutation ناجحة.
 * لا تستدعِه داخل Controller أو Blade.
 */
class AuditService
{
    /**
     * تسجيل إجراء في سجل الرقابة.
     *
     * @param  string  $action  فعل: create, update, delete, login, logout, dispense, print_quote …
     * @param  string  $description  ملخص بشري بالعربية
     * @param  string  $tag  وحدة: patients, medical, technical, financial, warehouse, auth
     * @param  mixed  $before  حالة ما قبل التغيير (مصفوفة أو null)
     * @param  mixed  $after  حالة ما بعد التغيير (مصفوفة أو null)
     */
    public static function log(
        string $action,
        string $description,
        string $tag,
        mixed $before = null,
        mixed $after = null,
    ): void {
        AuditLog::create([
            'user_id' => auth()->id(),
            'user_name' => auth()->user()?->name ?? 'system',
            'action' => $action,
            'description' => $description,
            'tag' => $tag,
            'ip_address' => request()->ip(),
            'mac_address' => request()->attributes->get('audit_mac'),
            'payload_before' => $before,
            'payload_after' => $after,
            'logged_at' => now(),
        ]);
    }
}
