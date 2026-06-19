<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * رقم موبايل مصري — 11 رقماً يبدأ بـ 010 / 011 / 012 / 015.
 */
class EgyptianMobile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $raw = trim((string) $value);

        if ($raw !== '' && preg_match('/\D/u', $raw)) {
            $fail('رقم الهاتف يجب أن يحتوي على أرقام فقط (11 رقماً).');

            return;
        }

        $digits = preg_replace('/\D+/', '', $raw);

        if (! preg_match('/^01[0125]\d{8}$/', $digits)) {
            $fail('رقم الهاتف يجب أن يكون 11 رقماً ويبدأ بـ 010 أو 011 أو 012 أو 015.');
        }
    }
}
