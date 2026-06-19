<?php

namespace App\Http\Requests\Appointment;

use App\Http\Requests\BaseRequest;
use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'patient_id'       => ['nullable', 'integer', 'exists:patients,id'],
            'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'appointment_time' => ['nullable', 'string', 'max:10'],
            'visit_type'       => ['nullable', 'string', Rule::in([
                Appointment::VISIT_EXAM,
                Appointment::VISIT_FOLLOWUP,
                Appointment::VISIT_FITTING,
                Appointment::VISIT_DELIVERY,
                Appointment::VISIT_REVIEW,
            ])],
            'patient_name'     => ['required_without:patient_id', 'nullable', 'string', 'max:255'],
            'phone'            => ['nullable', 'string', 'max:20'],
            'company_name'     => ['nullable', 'string', 'max:255'],
            'patient_type'     => ['nullable', 'string', Rule::in([Patient::TYPE_CIVILIAN, Patient::TYPE_MILITARY])],
        ];
    }

    public function messages(): array
    {
        return [
            'patient_name.required_without' => 'اسم المريض مطلوب عند عدم ربط ملف موجود.',
            'appointment_date.after_or_equal' => 'تاريخ الموعد لا يمكن أن يكون في الماضي.',
        ];
    }
}
