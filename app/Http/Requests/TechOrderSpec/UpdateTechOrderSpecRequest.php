<?php

namespace App\Http\Requests\TechOrderSpec;

use App\Http\Requests\BaseRequest;

class UpdateTechOrderSpecRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'tech_notes' => $this->notesRules(5000),
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.stock_item_code' => ['required_with:items', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-_]+$/'],
            'items.*.name' => ['required_with:items', 'string', 'min:1', 'max:255'],
            'items.*.qty' => ['required_with:items', ...$this->positiveQtyRules()],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.qty.min' => 'الكمية يجب أن تكون 1 على الأقل لكل بند.',
        ];
    }
}
