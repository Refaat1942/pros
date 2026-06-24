<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Models\TechOrderSpec;

/**
 * رقم الطلب — 6 أرقام عشوائية فريدة (مثال: 084729).
 */
class OrderRefService
{
    private const LENGTH = 6;

    public function generate(): string
    {
        do {
            $ref = str_pad((string) random_int(0, 999_999), self::LENGTH, '0', STR_PAD_LEFT);
        } while ($this->exists($ref));

        return $ref;
    }

    private function exists(string $ref): bool
    {
        return CaseRecord::where('order_ref', $ref)->exists()
            || TechOrderSpec::where('order_ref', $ref)->exists();
    }
}
