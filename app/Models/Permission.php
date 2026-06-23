<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * صلاحية تفصيلية — تتحكم في ميزة/زر داخل لوحة الدور.
 *
 * تُمنح للأدوار عبر جدول role_permission. الأدمن (السوبر أدمن) يمتلك كل الصلاحيات
 * ضمنياً عبر Gate::before في AuthServiceProvider.
 */
class Permission extends Model
{
    // ── سجل الصلاحيات الكامل (slug => [label_ar, group]) ──────────────────────
    public const CATALOG = [
        // المالية والتكاليف
        'view-costs'           => ['التكلفة الداخلية (WAC) وهامش الربح', 'financial'],
        'view-military-profit' => ['نِسَب الربحية العسكرية', 'financial'],
        'approve-pricing'      => ['اعتماد التسعير في مكتب التشغيل', 'financial'],
        // المخزون
        'manage-inventory'     => ['إدارة كتالوج الأصناف (إضافة/تعديل/حذف)', 'inventory'],
        'import-inventory'     => ['الرفع الجماعي للأصناف (Excel/CSV)', 'inventory'],
        'view-inventory-overview' => ['لوحة المخزون التفصيلية للأدمن', 'inventory'],
        // الطباعة
        'print-barcode'        => ['طباعة باركود الأصناف الحراري', 'printing'],
        'print-quote'          => ['طباعة عرض السعر / الفاتورة', 'printing'],
        // الإكلينيكي
        'skip-diagnosis'       => ['تخطّي الكشف الطبي (الدفع المباشر للتوصيف)', 'clinical'],
        // الإدارة
        'manage-permissions'   => ['إدارة مصفوفة الصلاحيات', 'admin'],
    ];

    protected $fillable = [
        'slug',
        'label_ar',
        'group',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }
}
