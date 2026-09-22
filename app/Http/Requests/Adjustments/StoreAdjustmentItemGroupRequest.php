<?php

namespace App\Http\Requests\Adjustments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdjustmentItemGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('adjustment_item_groups', 'name')],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.stock_item_code' => ['required', 'string', 'max:64'],
            'items.*.qty' => ['required', 'numeric', 'min:0.001'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'اسم المجموعة مستخدم مسبقاً — اختر اسمًا آخر.',
        ];
    }
}
