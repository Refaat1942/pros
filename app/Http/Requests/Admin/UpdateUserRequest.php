<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;
use App\Models\User;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends BaseRequest
{
    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role_id'  => ['required', 'integer', 'exists:roles,id'],
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
