<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;

class StoreVisitTypeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100', 'unique:visit_types,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم نوع الزيارة مطلوب.',
            'name.unique' => 'نوع الزيارة موجود مسبقاً.',
        ];
    }
}
