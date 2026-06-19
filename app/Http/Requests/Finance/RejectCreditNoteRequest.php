<?php

namespace App\Http\Requests\Finance;

use App\Http\Requests\BaseRequest;

class RejectCreditNoteRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
