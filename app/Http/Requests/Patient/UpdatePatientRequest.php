<?php

namespace App\Http\Requests\Patient;

use App\Http\Requests\BaseRequest;

class UpdatePatientRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'phone'               => ['sometimes', ...$this->egyptianMobileRules()],
            'national_id'         => ['sometimes', ...$this->egyptianNationalIdRules()],
            'contract_company_id' => ['sometimes', 'nullable', 'integer', 'exists:contract_companies,id'],
        ];
    }
}
