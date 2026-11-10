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

    // كل بند نسبة مستقلة من تكلفة المواد الخام — مش لازم المجموع يساوي 100%.

    public function messages(): array
    {
        return [
            '*.required' => 'يرجى إدخال جميع النسب.',
            '*.min' => 'النسبة لا يمكن أن تكون سالبة.',
            '*.max' => 'النسبة لا يمكن أن تتجاوز 100%.',
        ];
    }
}
