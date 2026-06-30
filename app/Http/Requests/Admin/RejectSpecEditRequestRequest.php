<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RejectSpecEditRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'rejection_reason_key' => [
                'nullable',
                'string',
                Rule::in(array_keys(config('spec_edit.rejection_reasons', []))),
            ],
            'rejection_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'rejection_reason_key.in' => 'سبب الرفض غير صالح.',
        ];
    }
}
