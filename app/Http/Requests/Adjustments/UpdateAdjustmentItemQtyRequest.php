<?php

namespace App\Http\Requests\Adjustments;

use App\Http\Requests\BaseRequest;

class UpdateAdjustmentItemQtyRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'qty' => $this->decimalQtyRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'qty.required' => 'يرجى إدخال الكمية.',
            'qty.min' => 'الكمية يجب أن تكون 0.001 على الأقل.',
        ];
    }
}
