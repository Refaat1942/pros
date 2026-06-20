<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;

class StoreStockCategoryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100', 'unique:stock_categories,name'],
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
