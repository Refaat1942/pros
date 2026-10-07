<?php

namespace App\Http\Requests\Bom;

use App\Http\Requests\BaseRequest;
use App\Rules\StockItemPickerCodeExists;

class StoreBomRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'case_id' => ['required', 'integer', 'exists:cases,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.stock_item_code' => ['required', 'string', 'max:500', new StockItemPickerCodeExists],
            'items.*.qty' => $this->decimalQtyRules(),
            'items.*.name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'case_id.exists' => 'الحالة غير موجودة.',
            'items.min' => 'يجب إضافة بند واحد على الأقل.',
            'items.*.stock_item_code.exists' => 'كود الصنف غير مسجَّل في المخزون.',
            'items.*.qty.min' => 'الكمية يجب أن تكون أكبر من صفر (مثال: 1 أو 0.5 متر).',
            'items.*.qty.regex' => 'الكمية تقبل حتى 3 أرقام عشرية.',
        ];
    }
}
