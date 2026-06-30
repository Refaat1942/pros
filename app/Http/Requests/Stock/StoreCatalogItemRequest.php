<?php

namespace App\Http\Requests\Stock;

use App\Http\Requests\BaseRequest;

/**
 * إنشاء صنف — السمات الأساسية فقط: الكود (اختياري — يُولَّد تلقائياً)،
 * الاسم، الكمية، والسعر (مع دعم أكثر من سعر عبر prices[]).
 */
class StoreCatalogItemRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'code'           => ['nullable', 'string', 'max:100', 'unique:stock_items,code'],
            'name'           => ['required', 'string', 'max:255'],
            'qty'            => ['nullable', 'integer', 'min:0'],
            'price'          => ['nullable', 'numeric', 'min:0'],
            'expiry_date'    => ['nullable', 'date'],

            // أسعار إضافية (صنف بأكثر من سعر) — اختيارية.
            'prices'         => ['nullable', 'array'],
            'prices.*.id'    => ['nullable'],
            'prices.*.label' => ['nullable', 'string', 'max:255'],
            'prices.*.amount'=> ['required_with:prices', 'numeric', 'min:0'],

            // سمات قديمة اختيارية (توافق خلفي — غير مطلوبة في النموذج المبسّط).
            'spec'           => ['nullable', 'string', 'max:500'],
            'category_id'    => ['nullable', 'integer', 'exists:stock_categories,id'],
            'attributes'     => ['nullable', 'array'],
            'supplier_ids'   => ['required', 'array', 'size:1'],
            'supplier_ids.*' => ['integer', 'exists:suppliers,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'يرجى إدخال اسم الصنف.',
            'code.unique'   => 'كود الصنف مستخدم مسبقاً.',
            'supplier_ids.required' => 'يرجى اختيار مورد واحد لهذا الصنف.',
            'supplier_ids.size'     => 'يُسمح بمورد واحد فقط لكل صنف.',
        ];
    }
}
