<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;

class LoginRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'email'       => ['required', 'email'],
            'password'    => ['required', 'string', 'min:6'],
            // بيانات الجهاز للإشعارات (FCM) — اختيارية.
            'device_id'   => ['nullable', 'string', 'max:512'],
            'device_type' => ['nullable', 'string', 'in:web,android,ios'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'    => 'البريد الإلكتروني مطلوب.',
            'email.email'       => 'صيغة البريد الإلكتروني غير صحيحة.',
            'password.required' => 'كلمة المرور مطلوبة.',
            'password.min'      => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.',
        ];
    }
}
