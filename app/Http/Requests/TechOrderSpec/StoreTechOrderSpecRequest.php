<?php

namespace App\Http\Requests\TechOrderSpec;

use App\Http\Requests\BaseRequest;
use App\Models\CaseRecord;

class StoreTechOrderSpecRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'case_id'    => ['required', 'integer', 'exists:cases,id'],
            'tech_notes' => $this->notesRules(5000),
            'items'      => ['required', 'array', 'min:1'],
            'items.*.stock_item_code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-_]+$/'],
            'items.*.name'            => ['required', 'string', 'min:1', 'max:255'],
            'items.*.qty'             => $this->positiveQtyRules(),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $caseId = $this->input('case_id');

            if (! $caseId) {
                return;
            }

            $case = CaseRecord::find($caseId);

            if ($case && $case->stage_key !== CaseRecord::STAGE_TECHNICAL) {
                $validator->errors()->add('case_id', 'الحالة ليست في مرحلة التوصيف الفني.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'items.required' => 'يجب إضافة بند واحد على الأقل.',
            'items.min'      => 'يجب إضافة بند واحد على الأقل.',
            'items.*.qty.min' => 'الكمية يجب أن تكون 1 على الأقل لكل بند.',
        ];
    }
}
