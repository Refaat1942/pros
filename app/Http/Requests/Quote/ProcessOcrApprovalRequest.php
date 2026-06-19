<?php

namespace App\Http\Requests\Quote;

use App\Http\Requests\BaseRequest;

class ProcessOcrApprovalRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'quote_no'         => ['required', 'string', 'max:50'],
            'patient_name'     => ['required', 'string', 'max:255'],
            'approved_amount'  => ['required', 'numeric', 'min:0'],
            'company_name'     => ['required', 'string', 'max:255'],
            'letter_ref'       => ['nullable', 'string', 'max:100'],
            'letter_date'      => ['nullable', 'string', 'max:50'],
        ];
    }
}
