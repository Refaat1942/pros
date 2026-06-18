<?php

namespace App\Enums;

/**
 * حالات طابور التسعير — status_key هو مصدر الحقيقة الوحيد.
 * statusLabel في الـ prototype يُشتق للعرض فقط عبر label().
 */
enum PricingRequestStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';

    public function label(): string
    {
        return __("pricing.status.{$this->value}");
    }

    public function step(): int
    {
        return match ($this) {
            self::Pending => 1,
            self::Sent => 2,
        };
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isSent(): bool
    {
        return $this === self::Sent;
    }
}
