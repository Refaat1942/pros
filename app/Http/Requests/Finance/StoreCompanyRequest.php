<?php

namespace App\Http\Requests\Finance;

use App\Http\Requests\BaseRequest;

class StoreCompanyRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'min:2', 'max:255', 'unique:contract_companies,name'],
            'is_military' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique'        => 'اسم الجهة مستخدم مسبقاً.',
            'is_military.required' => 'يجب تحديد نوع الجهة (مدنية أو عسكرية).',
        ];
    }
}
