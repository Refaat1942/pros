<?php

namespace App\Http\Requests\Appointment;

use App\Http\Requests\BaseRequest;
use App\Models\Appointment;
use App\Models\Patient;
use App\Support\MilitaryWeapons;
use Illuminate\Validation\Rule;

class CorrectAppointmentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => $this->personNameRules(),
            'phone' => $this->egyptianMobileRules(required: false),
            'national_id' => $this->egyptianNationalIdRules(),
            'visit_type_id' => ['required', 'integer', Rule::exists('visit_types', 'id')],
            'contract_company_id' => ['nullable', 'integer', 'exists:contract_companies,id'],
            'military_rank_id' => ['nullable', 'integer', 'exists:military_ranks,id'],
            'military_number' => ['nullable', 'string', 'max:30'],
            'seniority_number' => ['nullable', 'string', 'max:30'],
            'military_weapon' => MilitaryWeapons::optionalValidationRule(),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var Appointment|null $appointment */
            $appointment = $this->route('appointment');

            if (! $appointment instanceof Appointment) {
                return;
            }

            $type = $appointment->patient_type ?? $appointment->patient?->patient_type;

            if ($type === Patient::TYPE_MILITARY && ! $this->filled('military_rank_id')) {
                $validator->errors()->add('military_rank_id', 'الرتبة العسكرية مطلوبة.');
            }

            if ($type === Patient::TYPE_MILITARY && ! $this->filled('military_number')) {
                $validator->errors()->add('military_number', 'الرقم العسكري مطلوب.');
            }

            if ($type === Patient::TYPE_MILITARY && ! $this->filled('seniority_number')) {
                $validator->errors()->add('seniority_number', 'رقم الأقدمية مطلوب.');
            }

            if ($type === Patient::TYPE_MILITARY && ! $this->filled('military_weapon')) {
                $validator->errors()->add('military_weapon', 'السلاح / الفرع مطلوب.');
            }
        });
    }
}
