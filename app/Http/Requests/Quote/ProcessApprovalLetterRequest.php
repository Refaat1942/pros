<?php

namespace App\Http\Requests\Quote;

use App\Http\Requests\BaseRequest;
use App\Rules\CanonicalApprovalAmount;

class ProcessApprovalLetterRequest extends BaseRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if ($this->has('approved_amount') && is_numeric($this->input('approved_amount'))) {
            $this->merge(['approved_amount' => round((float) $this->input('approved_amount'), 2)]);
        }
    }

    public function rules(): array
    {
        return [
            'quote_no' => ['required', 'string', 'max:50'],
            'patient_name' => ['required', 'string', 'max:255'],
            'approved_amount' => ['required', 'numeric', 'min:0', new CanonicalApprovalAmount($this->input('quote_no'))],
            'company_name' => ['required', 'string', 'max:255'],
            'letter_ref' => ['nullable', 'string', 'max:100'],
            'letter_date' => ['nullable', 'string', 'max:50'],
            'letter_path' => ['nullable', 'string', 'max:500', 'regex:#^approval_letters/[^/\\\\]+$#'],
        ];
    }
}
