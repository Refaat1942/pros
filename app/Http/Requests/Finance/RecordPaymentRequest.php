<?php

namespace App\Http\Requests\Finance;

use App\Http\Requests\BaseRequest;

class RecordPaymentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'مبلغ الدفعة يجب أن يكون أكبر من الصفر.',
        ];
    }
}
