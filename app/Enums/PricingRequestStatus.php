<?php

namespace App\Enums;

/**
 * حالات طابور التسعير — status_key هو مصدر الحقيقة الوحيد.
 *
 * الدورة الكاملة:
 *   processing → awaiting_admin_approval → sent_to_reception
 *
 * الحالة الاستثنائية:
 *   insufficient — يُضبط عند فشل فحص المخزون أثناء إنشاء BOM.
 *
 * الألوان في الـ Prototype:
 *   processing            → badge-info    / أصفر غامق  (جاري الاحتساب)
 *   awaiting_admin_approval → badge-warning / برتقالي    (بانتظار الاعتماد)
 *   sent_to_reception     → badge-success / أخضر       (تم الإرسال للاستقبال)
 *   insufficient          → badge-danger  / أحمر       (غير كافٍ)
 */
enum PricingRequestStatus: string
{
    case Processing = 'processing';
    case AwaitingAdminApproval = 'awaiting_admin_approval';
    case SentToReception = 'sent_to_reception';
    case Insufficient = 'insufficient';

    public function label(): string
    {
        return match ($this) {
            self::Processing => 'جاري الاحتساب',
            self::AwaitingAdminApproval => 'بانتظار الاعتماد',
            self::SentToReception => 'تم الإرسال للاستقبال',
            self::Insufficient => 'غير كافٍ',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Processing => 'badge-info',
            self::AwaitingAdminApproval => 'badge-warning',
            self::SentToReception => 'badge-success',
            self::Insufficient => 'badge-danger',
        };
    }

    public function step(): int
    {
        return match ($this) {
            self::Processing => 1,
            self::AwaitingAdminApproval => 2,
            self::SentToReception => 3,
            self::Insufficient => 0,
        };
    }

    /** يُستخدم في PricingService::approve() للتحقق من صلاحية الاعتماد */
    public function isApprovable(): bool
    {
        return $this === self::AwaitingAdminApproval;
    }

    public function isProcessing(): bool
    {
        return $this === self::Processing;
    }

    public function isSentToReception(): bool
    {
        return $this === self::SentToReception;
    }

    public function isInsufficient(): bool
    {
        return $this === self::Insufficient;
    }

    /** دعم backward-compat — أي حالة تعني "لم تُعتمد بعد" */
    public function isPending(): bool
    {
        return $this === self::AwaitingAdminApproval;
    }
}
