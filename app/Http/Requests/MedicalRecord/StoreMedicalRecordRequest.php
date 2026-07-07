<?php

namespace App\Http\Requests\MedicalRecord;

use App\Http\Requests\BaseRequest;

class StoreMedicalRecordRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'medical_record_id' => ['nullable', 'integer', 'exists:medical_records,id'],
            'patient_id' => ['required_without:medical_record_id', 'nullable', 'integer', 'exists:patients,id'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'diagnosis' => ['required', 'string', 'min:3', 'max:5000'],
            'prescription' => $this->notesRules(5000),
            'items' => ['nullable', 'array'],
            'items.*.stock_item_code' => ['required_with:items', 'string', 'max:50'],
            'items.*.name' => ['required_with:items', 'string', 'max:255'],
            'items.*.qty' => $this->positiveQtyRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'diagnosis.required' => 'التشخيص مطلوب.',
            'items.*.qty.min' => 'الكمية يجب أن تكون 1 على الأقل.',
        ];
    }
}
