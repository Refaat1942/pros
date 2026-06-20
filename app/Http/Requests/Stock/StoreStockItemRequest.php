<?php

namespace App\Http\Requests\Stock;

use App\Enums\StockStoreClass;
use App\Enums\StockUom;
use App\Http\Requests\BaseRequest;

class StoreStockItemRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'code'        => ['required', 'string', 'max:50', 'unique:stock_items,code'],
            'name'        => ['required', 'string', 'max:255'],
            'spec'        => ['nullable', 'string', 'max:500'],
            'category_id' => ['nullable', 'integer', 'exists:stock_categories,id'],
            'store_class' => ['nullable', 'string', 'in:' . implode(',', StockStoreClass::values())],
            'uom'         => ['required', 'string', 'in:' . implode(',', StockUom::values())],
            'barcode'     => ['required', 'string', 'max:100', 'unique:stock_items,barcode'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique'     => 'كود الصنف مستخدم مسبقاً.',
            'barcode.unique'  => 'الباركود مستخدم مسبقاً.',
            'uom.in'          => 'وحدة القياس غير مقبولة.',
            'store_class.in'  => 'التصنيف غير مقبول.',
        ];
    }
}
