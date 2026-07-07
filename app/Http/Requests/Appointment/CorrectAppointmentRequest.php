<?php

namespace App\Http\Requests\Appointment;

use App\Http\Requests\BaseRequest;
use App\Models\Appointment;
use App\Models\Patient;
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
        });
    }
}
