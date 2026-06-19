<?php

namespace App\Http\Requests\Manufacturing;

use App\Http\Requests\BaseRequest;
use App\Models\CaseRecord;
use Illuminate\Validation\Rule;

class AdvanceManufacturingStageRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'manufacturing_stage' => [
                'required',
                'string',
                Rule::in([
                    CaseRecord::MFG_ISSUE,
                    CaseRecord::MFG_GENERATION,
                    CaseRecord::MFG_ASSEMBLY,
                    CaseRecord::MFG_CASTING,
                    CaseRecord::MFG_FINISHING,
                ]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'manufacturing_stage.in' => 'مرحلة التصنيع غير صالحة.',
        ];
    }
}
