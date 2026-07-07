<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;
use App\Services\SettingService;

class UpdateCostingSettingsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            SettingService::KEY_TECHNICAL_CHECK => ['required', 'numeric', 'min:0', 'max:100'],
            SettingService::KEY_COMPONENTS_INTEGRATION => ['required', 'numeric', 'min:0', 'max:100'],
            SettingService::KEY_MACHINERY_DEPRECIATION => ['required', 'numeric', 'min:0', 'max:100'],
            SettingService::KEY_REHABILITATION_ASSESSMENT => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $sum = round(collect($this->only([
                SettingService::KEY_TECHNICAL_CHECK,
                SettingService::KEY_COMPONENTS_INTEGRATION,
                SettingService::KEY_MACHINERY_DEPRECIATION,
                SettingService::KEY_REHABILITATION_ASSESSMENT,
            ]))->sum(fn ($value) => (float) $value), 2);

            if (abs($sum - 100) > 0.01) {
                $validator->errors()->add('rates_sum', 'مجموع نسب المصاريف الإضافية يجب أن يساوي 100%.');
            }
        });
    }

    public function messages(): array
    {
        return [
            '*.required' => 'يرجى إدخال جميع النسب.',
            '*.min' => 'النسبة لا يمكن أن تكون سالبة.',
            '*.max' => 'النسبة لا يمكن أن تتجاوز 100%.',
        ];
    }
}
