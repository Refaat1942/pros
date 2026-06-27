<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;
use App\Models\Role;
use App\Models\User;
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
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'alpha_dash',
                'unique:users,username',
            ],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'role_id'  => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where(fn ($q) => $q->where('slug', '!=', Role::SLUG_ADMIN)),
            ],
            'status'   => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])],
        ];
    }

    public function messages(): array
    {
        return [
            'username.unique'    => 'اسم المستخدم مستخدم مسبقاً.',
            'username.alpha_dash'=> 'اسم المستخدم: حروف إنجليزية وأرقام و _ و - فقط.',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
        ];
    }
}
