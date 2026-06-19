<?php

namespace App\Http\Requests\Bom;

use App\Http\Requests\BaseRequest;

class DispenseBomRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'scanned_barcodes'   => ['required', 'array', 'min:1'],
            'scanned_barcodes.*' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'scanned_barcodes.required' => 'يجب مسح باركود واحد على الأقل.',
        ];
    }
}
