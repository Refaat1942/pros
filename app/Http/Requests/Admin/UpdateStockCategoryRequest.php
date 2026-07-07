<?php

namespace App\Http\Requests\Admin;

use App\Enums\StockCategoryFieldType;
use App\Http\Requests\BaseRequest;
use App\Models\StockCategory;
use Illuminate\Validation\Rule;

class UpdateStockCategoryRequest extends BaseRequest
{
    public function rules(): array
    {
        /** @var StockCategory|null $category */
        $category = $this->route('stockCategory');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:100',
                Rule::unique('stock_categories', 'name')->ignore($category?->id),
            ],
            'fields' => ['nullable', 'array'],
            'fields.*.id' => ['nullable', 'integer'],
            'fields.*.field_key' => ['nullable', 'string', 'max:64'],
            'fields.*.label' => ['required_with:fields', 'string', 'max:100'],
            'fields.*.type' => ['required_with:fields', Rule::in(StockCategoryFieldType::values())],
            'fields.*.required' => ['nullable', 'boolean'],
            'fields.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'fields.*.options' => ['nullable', 'array'],
            'fields.*.config' => ['nullable', 'array'],
        ];
    }
}
