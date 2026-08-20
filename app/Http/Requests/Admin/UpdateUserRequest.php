<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\UserPageAccessService;
use App\Support\UsernameRules;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends BaseRequest
{
    protected function prepareForValidation(): void
    {
        /** @var User|null $user */
        $user = $this->route('user');
        $user?->loadMissing('role:id,slug');

        if ($user?->role?->slug === Role::SLUG_ADMIN) {
            $this->merge([
                'role_id' => $user->role_id,
                'status' => User::STATUS_ACTIVE,
            ]);
        }

        if ($this->has('username')) {
            $this->merge([
                'username' => UsernameRules::normalize((string) $this->input('username')),
            ]);
        }

        if ($this->has('catalog_list_visibility') && is_string($this->input('catalog_list_visibility'))) {
            $decoded = json_decode($this->input('catalog_list_visibility'), true);
            $this->merge([
                'catalog_list_visibility' => is_array($decoded) ? $decoded : null,
            ]);
        }

        if ($this->has('allowed_pages') && is_string($this->input('allowed_pages'))) {
            $decoded = json_decode($this->input('allowed_pages'), true);
            $this->merge([
                'allowed_pages' => is_array($decoded) ? $decoded : null,
            ]);
        }
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;
        /** @var User|null $user */
        $user = $this->route('user');
        $user?->loadMissing('role:id,slug');
        $isAdmin = $user?->role?->slug === Role::SLUG_ADMIN;

        $roleRules = $isAdmin
            ? ['required', 'integer', Rule::in([$user->role_id])]
            : [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where(fn ($q) => $q->where('slug', '!=', Role::SLUG_ADMIN)),
            ];

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => UsernameRules::rules($userId),
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'role_id' => $roleRules,
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])],
            'catalog_list_visibility' => ['nullable', 'array'],
            'access_tier' => ['nullable', Rule::in([
                UserPageAccessService::TIER_DEPARTMENT_ADMIN,
                UserPageAccessService::TIER_DEPARTMENT_STAFF,
            ])],
            'allowed_pages' => ['nullable', 'array'],
            'allowed_pages.*' => ['string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return array_merge([
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
        ], UsernameRules::messageAttributes());
    }
}
