<?php

namespace App\Http\Requests\Stock;

use App\Http\Requests\BaseRequest;

/**
 * تعديل صنف — السمات الأساسية فقط (الكود غير قابل للتعديل بعد الإنشاء).
 */
class UpdateCatalogItemRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:255'],
            'qty'            => ['nullable', 'integer', 'min:0'],
            'price'          => ['nullable', 'numeric', 'min:0'],
            'expiry_date'    => ['nullable', 'date'],

            // أسعار إضافية (صنف بأكثر من سعر) — اختيارية.
            'prices'         => ['nullable', 'array'],
            'prices.*.id'    => ['nullable'],
            'prices.*.label' => ['nullable', 'string', 'max:255'],
            'prices.*.amount'=> ['required_with:prices', 'numeric', 'min:0'],

            // سمات قديمة اختيارية (توافق خلفي).
            'spec'           => ['nullable', 'string', 'max:500'],
            'category_id'    => ['nullable', 'integer', 'exists:stock_categories,id'],
            'attributes'     => ['nullable', 'array'],
            'supplier_ids'   => ['required', 'array', 'min:1'],
            'supplier_ids.*' => ['integer', 'exists:suppliers,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'يرجى إدخال اسم الصنف.',
            'supplier_ids.required' => 'يرجى اختيار مورد واحد على الأقل.',
            'supplier_ids.min'      => 'يرجى اختيار مورد واحد على الأقل.',
        ];
    }
}
