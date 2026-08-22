<?php

namespace App\Http\Requests\Department;

use App\Http\Requests\BaseRequest;
use App\Models\User;
use App\Services\DepartmentStaffService;

class ResetDepartmentStaffPasswordRequest extends BaseRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $target = $this->route('user');

        return $user instanceof User
            && $target instanceof User
            && app(DepartmentStaffService::class)->canManage($user, $target);
    }

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
        ];
    }
}
