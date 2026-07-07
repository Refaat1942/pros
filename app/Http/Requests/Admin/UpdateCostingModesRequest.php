<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;

class UpdateCostingModesRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'modes' => ['required', 'array', 'min:1'],
            'modes.*.key' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/'],
            'modes.*.label' => ['required', 'string', 'max:120'],
            'modes.*.profit_rate' => ['required', 'numeric', 'min:0', 'max:1000'],
            'modes.*.has_components' => ['required', 'boolean'],
            'modes.*.components' => ['nullable', 'array'],
            'modes.*.components.*.label' => ['required_with:modes.*.components', 'string', 'max:150'],
            'modes.*.components.*.rate' => ['required_with:modes.*.components', 'numeric', 'min:0', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'modes.required' => 'يجب تعريف نمط واحد على الأقل.',
            'modes.*.key.regex' => 'مفتاح النمط يجب أن يكون حروفاً إنجليزية صغيرة/أرقام/شرطة سفلية.',
            'modes.*.label.required' => 'يرجى إدخال اسم النمط.',
            'modes.*.profit_rate.required' => 'يرجى إدخال نسبة الربح.',
        ];
    }
}
