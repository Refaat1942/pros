<?php

namespace App\Http\Requests\Patient;

use App\Http\Requests\BaseRequest;
use App\Models\Patient;

class UpdatePatientRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'phone'               => ['sometimes', 'nullable', 'string', 'max:20'],
            'contract_company_id' => ['sometimes', 'nullable', 'integer', 'exists:contract_companies,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var Patient|null $patient */
            $patient = $this->route('patient');

            if (! $patient) {
                return;
            }

            if ($patient->patient_type === Patient::TYPE_CIVILIAN && $this->has('contract_company_id') && ! $this->filled('contract_company_id')) {
                $validator->errors()->add('contract_company_id', 'جهة التعاقد مطلوبة للمريض المدني.');
            }
        });
    }
}
