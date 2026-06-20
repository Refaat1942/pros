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

    public function badgeClass(): string
    {
        return self::badgeClassFor($this->value);
    }

    public static function badgeClassFor(?string $key): string
    {
        return match ($key) {
            self::Reception->value,
            self::Exam->value       => 'badge-info',
            self::Technical->value  => 'badge-info',
            self::CostCalc->value   => 'badge-warning',
            self::AdminApproval->value => 'badge-warning',
            self::Quote->value,
            self::WaitingReturn->value => 'badge-warning',
            self::Manufacturing->value => 'badge-info',
            self::ReadyDelivery->value => 'badge-success',
            self::Delivered->value  => 'badge-success',
            default                 => 'badge-secondary',
        };
    }

    /** @return array{class: string, bg: string, text: string} */
    public static function specBadgeFor(?string $key): array
    {
        return match ($key) {
            self::CostCalc->value,
            self::AdminApproval->value,
            self::Quote->value,
            self::WaitingReturn->value => [
                'class' => 'bg-amber-100 text-amber-800',
                'bg'    => 'bg-amber-100',
                'text'  => 'text-amber-800',
            ],
            self::Manufacturing->value => [
                'class' => 'bg-cyan-100 text-cyan-800',
                'bg'    => 'bg-cyan-100',
                'text'  => 'text-cyan-800',
            ],
            self::ReadyDelivery->value,
            self::Delivered->value => [
                'class' => 'bg-emerald-100 text-emerald-800',
                'bg'    => 'bg-emerald-100',
                'text'  => 'text-emerald-800',
            ],
            default => [
                'class' => 'bg-slate-100 text-slate-700',
                'bg'    => 'bg-slate-100',
                'text'  => 'text-slate-700',
            ],
        };
    }
}
