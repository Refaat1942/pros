<?php

namespace App\Http\Requests\Stock;

class StoreSupplierRequest extends SupplierFieldsRequest
{
    public function rules(): array
    {
        return $this->supplierFieldRules();
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'اسم المورد مستخدم مسبقاً.',
        ];
    }
}
