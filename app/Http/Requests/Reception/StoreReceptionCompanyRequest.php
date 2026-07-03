<?php

namespace App\Http\Requests\Reception;

use App\Http\Requests\BaseRequest;

/**
 * إضافة جهة تعاقد غير متعاقدة من الاستقبال — للاختيار الفوري عند تسجيل المريض.
 */
class StoreReceptionCompanyRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم الجهة مطلوب.',
            'name.min'      => 'اسم الجهة يجب أن يكون حرفين على الأقل.',
        ];
    }
}
