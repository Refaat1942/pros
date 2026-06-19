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
            'tech_notes' => ['nullable', 'string', 'max:5000'],
            'items'      => ['required', 'array', 'min:1'],
            'items.*.stock_item_code' => ['required', 'string', 'max:50'],
            'items.*.name'            => ['required', 'string', 'max:255'],
            'items.*.qty'             => ['required', 'integer', 'min:1'],
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
        ];
    }
}
