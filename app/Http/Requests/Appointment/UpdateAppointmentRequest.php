<?php

namespace App\Http\Requests\Appointment;

use App\Http\Requests\BaseRequest;
use App\Models\Appointment;
use Illuminate\Validation\Rule;

class UpdateAppointmentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'appointment_date' => ['sometimes', 'required', 'date'],
            'appointment_time' => ['nullable', 'string', 'max:10'],
            'visit_type' => ['sometimes', 'required', 'string', Rule::in([
                Appointment::VISIT_EXAM,
                Appointment::VISIT_FOLLOWUP,
                Appointment::VISIT_FITTING,
                Appointment::VISIT_DELIVERY,
                Appointment::VISIT_REVIEW,
            ])],
        ];
    }
}
