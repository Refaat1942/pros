<?php

namespace App\Http\Requests\Finance;

use App\Http\Requests\BaseRequest;

class StoreCompanyRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'min:2', 'max:255', 'unique:contract_companies,name'],
            'is_military'   => ['required', 'boolean'],
            'is_contracted' => ['sometimes', 'boolean'],
            'discount_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique'        => 'اسم الجهة مستخدم مسبقاً.',
            'is_military.required' => 'يجب تحديد نوع الجهة (مدنية أو عسكرية).',
            'discount_percent.min' => 'نسبة الخصم لا يمكن أن تكون سالبة.',
            'discount_percent.max' => 'نسبة الخصم لا يمكن أن تتجاوز 100%.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->boolean('is_contracted')) {
            $this->merge(['discount_percent' => 0]);
        }
    }
}
