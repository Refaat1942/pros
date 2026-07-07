<?php

namespace App\Enums;

/**
 * وسائل تحصيل الدفع النقدي في الخزنة — للمرضى على نفقتهم الشخصية (كاش).
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case BankCheque = 'bank_cheque';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'كاش',
            self::BankTransfer => 'تحويل على الحساب',
            self::BankCheque => 'شيك مصرفي',
        };
    }

    /** يتطلب هذا الأسلوب رقم مرجعي (رقم الشيك / مرجع التحويل)؟ */
    public function requiresReference(): bool
    {
        return $this !== self::Cash;
    }

    /** تسمية حقل المرجع حسب الأسلوب. */
    public function referenceLabel(): string
    {
        return match ($this) {
            self::Cash => 'رقم العملية (اختياري)',
            self::BankTransfer => 'رقم/مرجع التحويل',
            self::BankCheque => 'رقم الشيك المصرفي',
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
