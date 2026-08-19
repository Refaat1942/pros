<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;
use App\Models\Role;
use App\Services\CatalogListVisibilityService;

class UpdateCatalogListSettingsRequest extends BaseRequest
{
    public function rules(): array
    {
        $profiles = array_keys(app(CatalogListVisibilityService::class)->profiles());
        $rules = [
            'roles' => ['required', 'array'],
        ];

        foreach (Role::query()->pluck('slug') as $slug) {
            $rules["roles.{$slug}"] = ['array'];
            foreach ($profiles as $profile) {
                $rules["roles.{$slug}.{$profile}.enabled"] = ['nullable', 'boolean'];
                $rules["roles.{$slug}.{$profile}.columns"] = ['nullable', 'array'];
                $rules["roles.{$slug}.{$profile}.columns.*"] = ['string', 'max:80'];
            }
        }

        return $rules;
    }
}
