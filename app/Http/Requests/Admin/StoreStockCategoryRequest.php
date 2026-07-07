<?php

namespace App\Http\Requests\Admin;

use App\Enums\StockCategoryFieldType;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class StoreStockCategoryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100', 'unique:stock_categories,name'],
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
