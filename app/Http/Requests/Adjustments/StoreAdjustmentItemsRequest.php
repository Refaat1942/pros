<?php

namespace App\Http\Requests\Adjustments;

use App\Http\Requests\BaseRequest;

class StoreAdjustmentItemsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.stock_item_code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-_]+$/', 'exists:stock_items,code'],
            'items.*.name' => ['nullable', 'string', 'min:1', 'max:255'],
            'items.*.qty' => $this->positiveQtyRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'يجب إضافة بند واحد على الأقل.',
            'items.min' => 'يجب إضافة بند واحد على الأقل.',
            'items.*.stock_item_code.exists' => 'الصنف المختار غير موجود في المخزون.',
        ];
    }
}
