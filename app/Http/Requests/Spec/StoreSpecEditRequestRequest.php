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
            'tech_notes'                  => ['nullable', 'string', 'max:2000'],
            'items'                       => ['required', 'array', 'min:1'],
            'items.*.stock_item_code'     => ['required', 'string', 'max:64'],
            'items.*.name'                => ['required', 'string', 'max:255'],
            'items.*.qty'                 => ['required', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.required'       => 'يجب إضافة بند واحد على الأقل.',
            'items.*.qty.min'      => 'الكمية يجب أن تكون 1 على الأقل لكل بند.',
        ];
    }
}
