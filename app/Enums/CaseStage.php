<?php

namespace App\Enums;

/**
 * مراحل مسار الحالة — التسلسل الصارم الجديد:
 *   reception → exam → technical → adjustments → cost_calc
 *             → quote → operations → manufacturing → ready_delivery → delivered
 */
enum CaseStage: string
{
    case Reception     = 'reception';
    case Exam          = 'exam';
    case Technical     = 'technical';
    case Adjustments   = 'adjustments';
    case CostCalc      = 'cost_calc';
    case Quote         = 'quote';
    case Operations    = 'operations';
    case Cashier       = 'cashier';
    case Manufacturing = 'manufacturing';
    case ReadyDelivery = 'ready_delivery';
    case Delivered     = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::Reception     => 'استقبال',
            self::Exam          => 'كشف',
            self::Technical     => 'توصيف فني',
            self::Adjustments   => 'المعدلات',
            self::CostCalc      => 'حساب التكاليف',
            self::Quote         => 'عرض السعر',
            self::Operations    => 'مكتب التشغيل',
            self::Cashier       => 'بانتظار الدفع في الخزنة',
            self::Manufacturing => 'جاري التصنيع',
            self::ReadyDelivery => 'جاهز للتسليم',
            self::Delivered     => 'تم التسليم',
        };
    }

    public static function labelFor(?string $key): string
    {
        if (! $key) {
            return '—';
        }

        return self::tryFrom($key)?->label()
            ?? self::legacyLabelFor($key)
            ?? $key;
    }

    /** مراحل قديمة في بيانات تجريبية/مهاجرة — للعرض فقط. */
    private static function legacyLabelFor(string $key): ?string
    {
        return match ($key) {
            'admin_approval' => 'انتظار موافقة الأدمن',
            'waiting_return' => 'بانتظار رجوع العميل',
            default          => null,
        };
    }

    public function badgeClass(): string
    {
        return self::badgeClassFor($this->value);
    }

    public static function badgeClassFor(?string $key): string
    {
        return match ($key) {
            self::Reception->value,
            self::Exam->value          => 'badge-info',
            self::Technical->value     => 'badge-info',
            self::Adjustments->value   => 'badge-warning',
            self::CostCalc->value,
            'admin_approval'           => 'badge-warning',
            self::Quote->value         => 'badge-warning',
            self::Operations->value,
            'waiting_return'             => 'badge-warning',
            self::Cashier->value       => 'badge-warning',
            self::Manufacturing->value => 'badge-info',
            self::ReadyDelivery->value => 'badge-success',
            self::Delivered->value     => 'badge-success',
            default                    => 'badge-secondary',
        };
    }

    /** @return array{class: string, bg: string, text: string} */
    public static function specBadgeFor(?string $key): array
    {
        return match ($key) {
            self::Adjustments->value,
            self::CostCalc->value,
            self::Quote->value,
            self::Cashier->value,
            self::Operations->value => [
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
