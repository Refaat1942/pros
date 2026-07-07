<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;
use App\Models\Role;
use App\Models\User;
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
                'username' => strtolower(trim((string) $this->input('username'))),
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
            'username' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'alpha_dash',
                Rule::unique('users', 'username')->ignore($userId),
            ],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'role_id' => $roleRules,
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])],
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
