<?php

namespace App\Http\Requests\Delivery;

use App\Http\Requests\BaseRequest;

class ScanDeliveryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'scanned_qr' => $this->qrCodeRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'scanned_qr.required' => 'يجب مسح بطاقة المريض.',
        ];
    }
}
