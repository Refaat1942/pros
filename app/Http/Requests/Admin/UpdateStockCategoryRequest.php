<?php

namespace App\Http\Requests\Admin;

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
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم الفئة مطلوب.',
            'name.unique'   => 'الفئة موجودة مسبقاً.',
        ];
    }
}
