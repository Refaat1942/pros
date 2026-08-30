<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;
use App\Models\Role;
use App\Services\CatalogListVisibilityService;

class UpdateCatalogListSettingsRequest extends BaseRequest
{
    public function rules(): array
    {
        $visibility = app(CatalogListVisibilityService::class);
        $profiles = array_keys($visibility->profiles());
        $sections = array_keys($visibility->sections());
        $rules = [
            'roles' => ['required', 'array'],
            'sections' => ['nullable', 'array'],
        ];

        foreach ($sections as $section) {
            $rules["sections.{$section}"] = ['array'];
            $rules["sections.{$section}.roles"] = ['array'];
            foreach (Role::query()->pluck('slug') as $slug) {
                $rules["sections.{$section}.roles.{$slug}"] = ['array'];
                $rules["sections.{$section}.roles.{$slug}.enabled"] = ['nullable', 'boolean'];
            }
        }

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
