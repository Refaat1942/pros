<?php

namespace App\Http\Requests\TechOrderSpec;

use App\Http\Requests\BaseRequest;

class UpdateTechOrderSpecRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'tech_notes' => ['nullable', 'string', 'max:5000'],
            'items'      => ['sometimes', 'array', 'min:1'],
            'items.*.stock_item_code' => ['required_with:items', 'string', 'max:50'],
            'items.*.name'            => ['required_with:items', 'string', 'max:255'],
            'items.*.qty'             => ['required_with:items', 'integer', 'min:1'],
        ];
    }
}
