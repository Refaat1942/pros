<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;
use App\Models\VisitType;

class ReorderVisitTypesRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:visit_types,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $ids = $this->input('ids', []);
            $total = VisitType::count();

            if (count($ids) !== $total) {
                $validator->errors()->add('ids', 'يجب تضمين جميع أنواع الزيارات في الترتيب الجديد.');
            }
        });
    }
}
