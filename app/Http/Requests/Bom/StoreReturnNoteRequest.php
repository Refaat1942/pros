<?php

namespace App\Http\Requests\Bom;

use App\Http\Requests\BaseRequest;

class StoreReturnNoteRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'bom_id' => ['required', 'integer', 'exists:boms,id'],
            'reason' => ['required', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.stock_item_code' => ['required', 'string', 'size:4', 'regex:/^\d{4}$/', 'exists:stock_items,alt_codes'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
            'lines.*.name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'bom_id.exists' => 'BOM غير موجودة.',
            'reason.required' => 'سبب الارتجاع مطلوب.',
            'lines.min' => 'يجب إضافة بند واحد على الأقل.',
        ];
    }
}
