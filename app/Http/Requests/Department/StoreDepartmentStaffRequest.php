<?php

namespace App\Http\Requests\Department;

use App\Http\Requests\BaseRequest;
use App\Models\User;
use App\Services\DepartmentStaffService;
use App\Support\UsernameRules;
use Illuminate\Validation\Rule;

class StoreDepartmentStaffRequest extends BaseRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && app(DepartmentStaffService::class)->isDepartmentManager($user);
    }

    protected function prepareForValidation(): void
    {
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => UsernameRules::rules(),
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])],
            'catalog_list_visibility' => ['nullable', 'array'],
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
