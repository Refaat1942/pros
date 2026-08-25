<?php

namespace App\Rules;

use App\Models\Quote;
use App\Support\QuotePrintPresenter;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * المبلغ المعتمد في خطاب الموافقة يجب أن يطابق مبلغ عرض السعر المطبوع (صافٍ بعد خصم التعاقد).
 */
class CanonicalApprovalAmount implements ValidationRule
{
    public function __construct(private readonly ?string $quoteNo) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->quoteNo === null || trim($this->quoteNo) === '') {
            return;
        }

        if (! is_numeric($value)) {
            return;
        }

        $quote = Quote::with(['caseRecord.contractCompany', 'items'])
            ->where('quote_no', $this->quoteNo)
            ->first();

        if (! $quote) {
            return;
        }

        $expected = round(QuotePrintPresenter::approvedAmount($quote), 2);
        $submitted = round((float) $value, 2);

        if ($submitted !== $expected) {
            $fail('المبلغ المعتمد لا يطابق مبلغ عرض السعر المطبوع ('.number_format($expected, 2).' ج.م).');
        }
    }
}
