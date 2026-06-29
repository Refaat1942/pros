<?php

namespace App\Http\Requests\Finance;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends BaseRequest
{
    public function rules(): array
    {
        $companyId = $this->route('company')?->id;

        return [
            'name'        => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('contract_companies', 'name')->ignore($companyId),
            ],
            'is_military'   => ['sometimes', 'boolean'],
            'is_contracted' => ['sometimes', 'boolean'],
            'discount_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
