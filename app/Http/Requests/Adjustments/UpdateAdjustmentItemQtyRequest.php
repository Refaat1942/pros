<?php

namespace App\Http\Requests\Adjustments;

use App\Http\Requests\BaseRequest;

class UpdateAdjustmentItemQtyRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'qty' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }

    public function messages(): array
    {
        return [
            'qty.required' => 'يرجى إدخال الكمية.',
            'qty.min' => 'الكمية يجب أن تكون واحداً على الأقل.',
        ];
    }
}
