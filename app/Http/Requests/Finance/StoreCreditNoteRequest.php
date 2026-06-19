<?php

namespace App\Http\Requests\Finance;

use App\Http\Requests\BaseRequest;
use App\Models\CreditNote;
use Illuminate\Validation\Rule;

class StoreCreditNoteRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'case_id' => ['required', 'integer', 'exists:cases,id'],
            'type'    => ['required', 'string', Rule::in([
                CreditNote::TYPE_PARTIAL,
                CreditNote::TYPE_FULL,
            ])],
            'amount'  => ['required', 'numeric', 'min:0.01'],
            'reason'  => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'case_id.exists' => 'الحالة غير موجودة.',
            'reason.required' => 'سبب الإشعار مطلوب.',
        ];
    }
}
