<?php

namespace App\Http\Requests\Stock;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class ReceiveStockRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'stock_item_id' => ['required', 'integer', 'exists:stock_items,id'],
            'qty'           => ['required', 'integer', 'min:1'],
            'unit_price'    => ['required', 'numeric', 'min:0.01'],
            'supplier_id'   => [
                'required',
                'integer',
                Rule::exists('suppliers', 'id')->where('is_active', true),
            ],
            'invoice_no'    => ['required', 'string', 'max:100'],
            'moved_at'      => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'stock_item_id.exists'        => 'الصنف غير موجود.',
            'qty.min'                      => 'الكمية يجب أن تكون 1 على الأقل.',
            'unit_price.min'               => 'سعر الوحدة يجب أن يكون أكبر من الصفر.',
            'supplier_id.exists'           => 'المورد غير موجود أو غير نشط.',
            'moved_at.before_or_equal'     => 'تاريخ الاستلام لا يمكن أن يكون في المستقبل.',
        ];
    }
}
