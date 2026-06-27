<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;

class LoginRequest extends BaseRequest
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
            'username'    => ['required', 'string', 'max:50'],
            'password'    => ['required', 'string', 'min:6'],
            'device_id'   => ['nullable', 'string', 'max:512'],
            'device_type' => ['nullable', 'string', 'in:web,android,ios'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'اسم المستخدم مطلوب.',
            'password.required' => 'كلمة المرور مطلوبة.',
            'password.min'      => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.',
        ];
    }
}
