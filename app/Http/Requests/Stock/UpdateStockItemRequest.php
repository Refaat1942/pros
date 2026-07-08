<?php

namespace App\Http\Requests\Stock;

use App\Enums\StockStoreClass;
use App\Enums\StockUom;
use App\Http\Requests\BaseRequest;

class UpdateStockItemRequest extends BaseRequest
{
    public function rules(): array
    {
        $itemId = $this->route('stockItem')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'spec' => ['nullable', 'string', 'max:500'],
            'category_id' => ['nullable', 'integer', 'exists:stock_categories,id'],
            'store_class' => ['nullable', 'string', 'in:'.implode(',', StockStoreClass::values())],
            'is_quick_dispense' => ['nullable', 'boolean'],
            'uom' => ['sometimes', 'required', 'string', 'in:'.implode(',', StockUom::values())],
            // code and barcode are immutable after creation — financial / barcode integrity
        ];
    }
}
