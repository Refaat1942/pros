<?php

namespace App\Http\Requests\Patient;

use App\Http\Requests\BaseRequest;
use App\Models\ContractCompany;
use App\Models\Patient;
use Illuminate\Validation\Rule;

class StorePatientRequest extends BaseRequest
{
    public const CLASS_CASH = 'cash';
    public const CLASS_ENTITY = 'entity';
    public const CLASS_MILITARY = 'military';

    protected function prepareForValidation(): void
    {
        if (! $this->filled('patient_classification')) {
            $legacy = $this->input('patient_type');
            if ($legacy === Patient::TYPE_MILITARY) {
                $this->merge(['patient_classification' => self::CLASS_MILITARY]);
            } elseif ($this->filled('contract_company_id')) {
                $this->merge(['patient_classification' => self::CLASS_ENTITY]);
            } else {
                $this->merge(['patient_classification' => self::CLASS_CASH]);
            }
        }

        $class = $this->input('patient_classification');

        if (in_array($class, [self::CLASS_CASH, self::CLASS_ENTITY], true)) {
            $this->merge(['patient_type' => Patient::TYPE_CIVILIAN]);
        } elseif ($class === self::CLASS_MILITARY) {
            $this->merge([
                'patient_type'        => Patient::TYPE_MILITARY,
                'contract_company_id' => null,
                'entity_billing_type' => null,
            ]);
        }

        if ($class === self::CLASS_CASH) {
            $this->merge([
                'contract_company_id' => null,
                'entity_billing_type' => null,
            ]);
        }

        if ($class === self::CLASS_ENTITY
            && ! $this->filled('entity_billing_type')
            && $this->filled('contract_company_id')) {
            $company = ContractCompany::query()->find($this->input('contract_company_id'));

            if ($company && ! $company->is_military) {
                $this->merge([
                    'entity_billing_type' => $company->is_contracted ? 'contracted' : 'non_contracted',
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name'                   => $this->personNameRules(),
            'phone'                  => $this->egyptianMobileRules(required: false),
            'national_id'            => $this->egyptianNationalIdRules(),
            'patient_classification' => ['required', 'string', Rule::in([self::CLASS_CASH, self::CLASS_ENTITY, self::CLASS_MILITARY])],
            'patient_type'           => ['required', 'string', Rule::in([Patient::TYPE_CIVILIAN, Patient::TYPE_MILITARY])],
            'entity_billing_type'    => ['nullable', 'string', Rule::in(['contracted', 'non_contracted'])],
            'military_rank_id'       => ['nullable', 'integer', 'exists:military_ranks,id'],
            'sovereign_entity'       => ['nullable', 'string', 'min:2', 'max:255'],
            'contract_company_id'    => ['nullable', 'integer', 'exists:contract_companies,id'],
            'visit_type_id'          => ['required', 'integer', Rule::exists('visit_types', 'id')],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $class = $this->input('patient_classification');

            if ($class === self::CLASS_MILITARY && ! $this->filled('military_rank_id')) {
                $validator->errors()->add('military_rank_id', 'الرتبة العسكرية مطلوبة للمريض العسكري.');
            }

            if ($class !== self::CLASS_ENTITY) {
                return;
            }

            if (! $this->filled('entity_billing_type')) {
                $validator->errors()->add('entity_billing_type', 'اختر نوع الجهة (متعاقد أو غير متعاقد).');
            }

            if (! $this->filled('contract_company_id')) {
                $validator->errors()->add('contract_company_id', 'جهة التعاقد مطلوبة.');

                return;
            }

            if (! $this->filled('entity_billing_type')) {
                return;
            }

            $company = ContractCompany::query()->find($this->input('contract_company_id'));

            if (! $company || $company->is_military) {
                $validator->errors()->add('contract_company_id', 'الجهة المختارة غير صالحة.');

                return;
            }

            $wantContracted = $this->input('entity_billing_type') === 'contracted';

            if ((bool) $company->is_contracted !== $wantContracted) {
                $validator->errors()->add('contract_company_id', 'الجهة المختارة لا تطابق نوع الفوترة (متعاقد / غير متعاقد).');
            }
        });
    }

    public function messages(): array
    {
        return [
            'patient_classification.in' => 'تصنيف المريض يجب أن يكون مدني أو جهات أو عسكري.',
            'visit_type_id.required'      => 'نوع الزيارة مطلوب.',
            'visit_type_id.exists'        => 'نوع الزيارة غير صالح.',
        ];
    }
}
