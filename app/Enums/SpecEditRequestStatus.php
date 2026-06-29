<?php

namespace App\Enums;

enum SpecEditRequestStatus: string
{
    case Pending  = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending  => 'بانتظار الموافقة',
            self::Approved => 'مُعتمد',
            self::Rejected => 'مرفوض',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending  => 'badge-warn',
            self::Approved => 'badge-ok',
            self::Rejected => 'badge-danger',
        };
    }
}
