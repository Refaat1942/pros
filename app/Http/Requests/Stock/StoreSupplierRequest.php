<?php

namespace App\Http\Requests\Stock;

use App\Http\Requests\BaseRequest;

class StoreSupplierRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'min:2', 'max:255', 'unique:suppliers,name'],
            'phone'   => $this->egyptianMobileRules(),
            'email'   => ['nullable', 'email', 'max:191'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes'   => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'اسم المورد مستخدم مسبقاً.',
        ];
    }
}
