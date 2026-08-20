<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\UserPageAccessService;
use Illuminate\Validation\Rule;

class StoreUserRequest extends BaseRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('username')) {
            $this->merge([
                'username' => strtolower(trim((string) $this->input('username'))),
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'alpha_dash',
                'unique:users,username',
            ],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where(fn ($q) => $q->where('slug', '!=', Role::SLUG_ADMIN)),
            ],
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
        return [
            'username.unique' => 'اسم المستخدم مستخدم مسبقاً.',
            'username.alpha_dash' => 'اسم المستخدم: حروف إنجليزية وأرقام و _ و - فقط.',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
        ];
    }
}
