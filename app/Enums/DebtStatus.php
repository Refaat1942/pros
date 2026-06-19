<?php

namespace App\Enums;

/**
 * حالة مديونية جهة التعاقد — status في contract_company_debts
 */
enum DebtStatus: string
{
    case Pending = 'pending';
    case Partial = 'partial';
    case Paid    = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'لم يُسدَّد',
            self::Partial => 'مسدَّد جزئياً',
            self::Paid    => 'مسدَّد',
        };
    }
}
