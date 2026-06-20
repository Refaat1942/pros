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
                'status'  => User::STATUS_ACTIVE,
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
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'role_id'  => $roleRules,
            'status'   => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'       => 'البريد الإلكتروني مستخدم مسبقاً.',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
        ];
    }
}
