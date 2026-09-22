<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;

class UpdateStockUomSettingsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'custom_profiles' => ['required', 'array'],
            'custom_profiles.*.key' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/'],
            'custom_profiles.*.label' => ['required', 'string', 'max:120'],
            'custom_profiles.*.supply_uom' => ['nullable', 'string', 'max:50'],
            'custom_profiles.*.units_per_supply_unit' => ['required', 'numeric', 'min:0.000001'],
            'custom_profiles.*.base_uom_hint' => ['nullable', 'string', 'max:50'],
            'supply_unit_suggestions' => ['nullable', 'array'],
            'supply_unit_suggestions.*' => ['string', 'max:50'],
        ];
    }
}
