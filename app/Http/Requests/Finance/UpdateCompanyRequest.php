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
            'name' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('contract_companies', 'name')->ignore($companyId),
            ],
            'is_military' => ['sometimes', 'boolean'],
            'is_contracted' => ['sometimes', 'boolean'],
            'discount_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'discount_percent.min' => 'نسبة الخصم لا يمكن أن تكون سالبة.',
            'discount_percent.max' => 'نسبة الخصم لا يمكن أن تتجاوز 100%.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_contracted') && ! $this->boolean('is_contracted')) {
            $this->merge(['discount_percent' => 0]);
        }
    }
}
