<?php

namespace App\Http\Requests\Stock;

use App\Http\Requests\BaseRequest;
use App\Models\SupplyRequestLine;

class StoreSupplyRequestLineRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'line_type' => ['required', 'string', 'in:'.SupplyRequestLine::TYPE_CATALOG.','.SupplyRequestLine::TYPE_NON_CATALOG],
            'stock_item_id' => ['required_if:line_type,'.SupplyRequestLine::TYPE_CATALOG, 'nullable', 'integer', 'exists:stock_items,id'],
            'description' => ['required_if:line_type,'.SupplyRequestLine::TYPE_NON_CATALOG, 'nullable', 'string', 'max:500'],
            'qty' => ['required', 'integer', 'min:1'],
            'uom' => ['nullable', 'string', 'max:50'],
            'spec' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'line_type.required' => 'نوع البند مطلوب.',
            'line_type.in' => 'نوع البند غير صالح.',
            'stock_item_id.required_if' => 'يجب اختيار صنف من الكتالوج.',
            'stock_item_id.exists' => 'الصنف المختار غير موجود.',
            'description.required_if' => 'اسم/وصف الصنف مطلوب للأصناف غير المكودة.',
            'qty.min' => 'الكمية يجب أن تكون 1 على الأقل.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('line_type') === SupplyRequestLine::TYPE_CATALOG) {
            $this->merge(['description' => null]);
        }

        if ($this->input('line_type') === SupplyRequestLine::TYPE_NON_CATALOG) {
            $this->merge(['stock_item_id' => null]);
        }
    }
}
