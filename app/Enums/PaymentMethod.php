<?php

namespace App\Enums;

/**
 * وسائل تحصيل الدفع النقدي في الخزنة — للمرضى على نفقتهم الشخصية (كاش).
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Instapay = 'instapay';
    case VodafoneCash = 'vodafone_cash';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'نقدي',
            self::Instapay => 'إنستا باي',
            self::VodafoneCash => 'فودافون كاش',
        };
    }

    public static function labelFor(?string $value): string
    {
        if (! $value) {
            return '—';
        }

        return self::tryFrom($value)?->label() ?? $value;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $m) => $m->value, self::cases());
    }
}
