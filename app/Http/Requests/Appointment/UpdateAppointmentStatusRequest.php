<?php

namespace App\Http\Requests\Appointment;

use App\Http\Requests\BaseRequest;
use App\Models\Appointment;
use Illuminate\Validation\Rule;

class UpdateAppointmentStatusRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in([
                Appointment::STATUS_IN_CLINIC,
                Appointment::STATUS_DONE,
            ])],
        ];
    }
}
