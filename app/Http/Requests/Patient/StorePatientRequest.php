<?php

namespace App\Http\Requests\Patient;

use App\Http\Requests\BaseRequest;
use App\Models\Patient;
use Illuminate\Validation\Rule;

class StorePatientRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name'                => ['required', 'string', 'max:255'],
            'phone'               => ['nullable', 'string', 'max:20'],
            'national_id'         => ['nullable', 'string', 'max:20'],
            'patient_type'        => ['required', 'string', Rule::in([Patient::TYPE_CIVILIAN, Patient::TYPE_MILITARY])],
            'military_rank_id'    => ['nullable', 'integer', 'exists:military_ranks,id'],
            'sovereign_entity'    => ['nullable', 'string', 'max:255'],
            'contract_company_id' => ['nullable', 'integer', 'exists:contract_companies,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('patient_type');

            if ($type === Patient::TYPE_CIVILIAN && ! $this->filled('contract_company_id')) {
                $validator->errors()->add('contract_company_id', 'جهة التعاقد مطلوبة للمريض المدني.');
            }

            if ($type === Patient::TYPE_MILITARY) {
                if (! $this->filled('military_rank_id')) {
                    $validator->errors()->add('military_rank_id', 'الرتبة العسكرية مطلوبة للمريض العسكري.');
                }
                if (! $this->filled('sovereign_entity')) {
                    $validator->errors()->add('sovereign_entity', 'الجهة السيادية مطلوبة للمريض العسكري.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'patient_type.in' => 'نوع المريض يجب أن يكون مدني أو عسكري.',
        ];
    }
}
