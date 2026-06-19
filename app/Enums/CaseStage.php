<?php

namespace App\Enums;

/**
 * مراحل مسار الحالة — labels مطابقة لـ cases-workflow.js
 */
enum CaseStage: string
{
    case Reception      = 'reception';
    case Exam            = 'exam';
    case Technical       = 'technical';
    case CostCalc        = 'cost_calc';
    case AdminApproval   = 'admin_approval';
    case Quote           = 'quote';
    case WaitingReturn   = 'waiting_return';
    case Manufacturing   = 'manufacturing';
    case ReadyDelivery    = 'ready_delivery';
    case Delivered       = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::Reception      => 'استقبال',
            self::Exam            => 'كشف',
            self::Technical       => 'توصيف فني',
            self::CostCalc        => 'حساب تكلفة',
            self::AdminApproval   => 'انتظار موافقة الأدمن',
            self::Quote           => 'عرض سعر',
            self::WaitingReturn   => 'انتظار رجوع العميل',
            self::Manufacturing   => 'جاري التصنيع',
            self::ReadyDelivery    => 'جاهز للتسليم',
            self::Delivered       => 'تم التسليم',
        };
    }

    public static function labelFor(?string $key): string
    {
        if (! $key) {
            return '—';
        }

        return self::tryFrom($key)?->label() ?? $key;
    }
}
