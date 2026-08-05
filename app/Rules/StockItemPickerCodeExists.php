<?php

namespace App\Rules;

use App\Models\StockItem;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** يتحقق أن الكود يطابق صنفاً في الكتالوج (alt_codes أو code أو catalog_number). */
class StockItemPickerCodeExists implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $code = trim((string) ($value ?? ''));

        if ($code === '' || StockItem::findByOperationalCode($code) === null) {
            $fail('الصنف غير موجود في الكتالوج.');
        }
    }
}
