<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;

class StoreMilitaryRankRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name'       => $this->personNameRules(),
            'rank_code'  => ['nullable', 'string', 'min:2', 'max:30', 'regex:/^[A-Za-z0-9_\-]+$/', 'unique:military_ranks,rank_code'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function messages(): array
    {
        return [
            'rank_code.regex'  => 'كود الرتبة يجب أن يحتوي على حروف إنجليزية وأرقام فقط.',
            'rank_code.unique' => 'كود الرتبة مستخدم مسبقاً.',
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if ($this->has('rank_code') && is_string($this->rank_code)) {
            $code = strtoupper(trim($this->rank_code));
            $this->merge(['rank_code' => $code !== '' ? $code : null]);
        }
    }
}
