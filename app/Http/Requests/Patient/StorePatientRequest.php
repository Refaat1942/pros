<?php

namespace App\Http\Requests\Patient;

use App\Http\Requests\BaseRequest;
use App\Models\Patient;
use App\Models\VisitType;
use Illuminate\Validation\Rule;

class StorePatientRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name'                => $this->personNameRules(),
            'phone'               => $this->egyptianMobileRules(),
            'national_id'         => $this->egyptianNationalIdRules(),
            'patient_type'        => ['required', 'string', Rule::in([Patient::TYPE_CIVILIAN, Patient::TYPE_MILITARY])],
            'military_rank_id'    => ['nullable', 'integer', 'exists:military_ranks,id'],
            'sovereign_entity'    => ['nullable', 'string', 'min:2', 'max:255'],
            'contract_company_id' => ['nullable', 'integer', 'exists:contract_companies,id'],
            'visit_type_id'       => ['required', 'integer', Rule::exists('visit_types', 'id')],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('patient_type');

            if ($type === Patient::TYPE_CIVILIAN && ! $this->filled('contract_company_id')) {
                $validator->errors()->add('contract_company_id', 'جهة التعاقد مطلوبة للمريض المدني.');
            }

            if ($type === Patient::TYPE_MILITARY && ! $this->filled('military_rank_id')) {
                $validator->errors()->add('military_rank_id', 'الرتبة العسكرية مطلوبة للمريض العسكري.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'patient_type.in' => 'نوع المريض يجب أن يكون مدني أو عسكري.',
            'visit_type_id.required' => 'نوع الزيارة مطلوب.',
            'visit_type_id.exists'   => 'نوع الزيارة غير صالح.',
        ];
    }
}
