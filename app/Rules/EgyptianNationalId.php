<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * الرقم القومي المصري — 14 رقماً (يبدأ بـ 2 أو 3 للقرن).
 */
class EgyptianNationalId implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $raw = trim((string) $value);

        if ($raw !== '' && preg_match('/\D/u', $raw)) {
            $fail('الرقم القومي يجب أن يحتوي على أرقام فقط (14 رقماً).');

            return;
        }

        $digits = preg_replace('/\D+/', '', $raw);

        if (! preg_match('/^[23]\d{13}$/', $digits)) {
            $fail('الرقم القومي يجب أن يكون 14 رقماً ويبدأ بـ 2 أو 3.');
        }
    }
}
