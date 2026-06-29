<?php

namespace App\Http\Requests\Stock;

class UpdateSupplierRequest extends SupplierFieldsRequest
{
    public function rules(): array
    {
        $supplierId = $this->route('supplier')?->id;

        return $this->supplierFieldRules($supplierId);
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'اسم المورد مستخدم مسبقاً.',
        ];
    }
}
