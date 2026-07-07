<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;
use App\Models\MilitaryRank;

class ReorderMilitaryRanksRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:military_ranks,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $ids = $this->input('ids', []);
            $total = MilitaryRank::count();

            if (count($ids) !== $total) {
                $validator->errors()->add('ids', 'يجب تضمين جميع الرتب في الترتيب الجديد.');
            }
        });
    }
}
