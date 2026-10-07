<?php

namespace App\Http\Requests\Spec;

use Illuminate\Foundation\Http\FormRequest;

class StoreSpecEditRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tech_notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.stock_item_code' => ['required', 'string', 'max:64'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['required', 'numeric', 'min:0.001', 'max:999999', 'regex:/^\d+(\.\d{1,3})?$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.required' => 'يجب إضافة بند واحد على الأقل.',
            'items.*.qty.min' => 'الكمية يجب أن تكون أكبر من صفر (مثال: 1 أو 0.5 متر).',
            'items.*.qty.regex' => 'الكمية تقبل حتى 3 أرقام عشرية.',
        ];
    }
}
