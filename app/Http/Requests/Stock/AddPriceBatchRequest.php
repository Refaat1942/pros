<?php

namespace App\Http\Requests\Stock;

use App\Http\Requests\BaseRequest;

class AddPriceBatchRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'qty'         => ['required', 'integer', 'min:1'],
            'unit_price'  => ['required', 'numeric', 'min:0.01'],
            'invoice_no'  => ['required', 'string', 'max:100'],
            'received_at' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.exists'         => 'المورد غير موجود.',
            'qty.min'                     => 'الكمية يجب أن تكون 1 على الأقل.',
            'unit_price.min'              => 'السعر يجب أن يكون أكبر من الصفر.',
            'received_at.before_or_equal' => 'تاريخ الاستلام لا يمكن أن يكون في المستقبل.',
        ];
    }
}
