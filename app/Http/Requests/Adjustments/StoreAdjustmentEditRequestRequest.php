<?php

namespace App\Http\Requests\Adjustments;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdjustmentEditRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'items' => ['present', 'array'],
            'items.*.stock_item_code' => ['required', 'string', 'max:64'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ];
    }
}
