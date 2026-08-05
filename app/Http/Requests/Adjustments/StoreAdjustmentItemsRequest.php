<?php

namespace App\Http\Requests\Adjustments;

use App\Http\Requests\BaseRequest;

class StoreAdjustmentItemsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.stock_item_code' => ['required', 'string', 'max:500', 'exists:stock_items,alt_codes'],
            'items.*.name' => ['nullable', 'string', 'min:1', 'max:255'],
            'items.*.qty' => $this->positiveQtyRules(),
            'items.*.group_label' => ['nullable', 'string', 'max:255'],
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
