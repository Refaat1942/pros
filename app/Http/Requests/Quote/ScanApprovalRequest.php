<?php

namespace App\Http\Requests\Quote;

use App\Http\Requests\BaseRequest;

class ScanApprovalRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'scanned_qr' => ['required', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'scanned_qr.required' => 'رمز QR مطلوب.',
        ];
    }
}
