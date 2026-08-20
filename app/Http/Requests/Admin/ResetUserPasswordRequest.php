<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;

class ResetUserPasswordRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
            'password.min' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.',
        ];
    }
}
