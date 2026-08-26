<?php

namespace App\Http\Requests\Stock;

use App\Http\Requests\BaseRequest;

class ResolveSupplyRequestLineRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'stock_item_id' => ['required', 'integer', 'exists:stock_items,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'stock_item_id.required' => 'يجب اختيار صنف كتالوج للربط.',
            'stock_item_id.exists' => 'الصنف المختار غير موجود.',
        ];
    }
}
