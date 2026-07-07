<?php

namespace App\Support;

/**
 * تسميات عربية لسجل الرقابة — action/tag تُخزَّن بالإنجليزية في DB.
 */
final class AuditLogLabel
{
    /** @var array<string, string> */
    private const ACTIONS = [
        'login' => 'تسجيل دخول',
        'logout' => 'تسجيل خروج',
        'create' => 'إنشاء',
        'update' => 'تحديث',
        'delete' => 'حذف',
        'lock' => 'اعتماد',
        'view' => 'عرض',
        'deliver' => 'تسليم',
        'dispense' => 'صرف',
        'receive' => 'استلام',
        'scan' => 'مسح QR',
        'issue' => 'إصدار',
        'approve' => 'اعتماد',
        'auto_approve' => 'اعتماد تلقائي',
        'calculate' => 'احتساب',
        'payment' => 'دفع',
        'debt' => 'مديونية',
        'post' => 'ترحيل',
        'credit_note' => 'إشعار دائن',
        'reject' => 'رفض',
        'insufficient' => 'غير كاف',
        'finish' => 'إغلاق',
        'stage' => 'تقدم مرحلة',
        'blocked' => 'محظور',
        'ocr' => 'قراءة OCR',
        'invoice' => 'فاتورة',
        'archive' => 'أرشفة',
        'return' => 'مرتجع',
    ];

    /** @var array<string, string> */
    private const TAGS = [
        'auth' => 'مصادقة',
        'admin' => 'إدارة',
        'medical' => 'طبي',
        'patients' => 'مرضى',
        'financial' => 'مالي',
        'warehouse' => 'مخزن',
        'pricing' => 'تسعير',
        'spec' => 'توصيف',
        'quotes' => 'عروض أسعار',
        'contracts' => 'عقود',
        'delivery' => 'تسليم',
        'operations' => 'تشغيل',
        'system' => 'نظام',
    ];

    public static function action(?string $key): string
    {
        if (! $key) {
            return '—';
        }

        return self::ACTIONS[$key] ?? $key;
    }

    public static function tag(?string $key): string
    {
        if (! $key) {
            return '—';
        }

        return self::TAGS[$key] ?? $key;
    }

    public static function badge(?string $action, ?string $tag): string
    {
        // إجراءات المصادقة (دخول/خروج) واضحة بذاتها — لا نُلحق وسم "مصادقة".
        if ($tag === 'auth') {
            return self::action($action);
        }

        return self::action($action).' · '.self::tag($tag);
    }
}
