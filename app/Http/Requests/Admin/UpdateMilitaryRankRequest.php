<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;
use App\Models\MilitaryRank;
use Illuminate\Validation\Rule;

class UpdateMilitaryRankRequest extends BaseRequest
{
    public function rules(): array
    {
        /** @var MilitaryRank|null $rank */
        $rank = $this->route('militaryRank');

        return [
            'name'       => ['sometimes', 'string', 'min:2', 'max:100'],
            'rank_code'  => [
                'nullable',
                'string',
                'min:2',
                'max:30',
                'regex:/^[A-Za-z0-9_\-]+$/',
                Rule::unique('military_ranks', 'rank_code')->ignore($rank?->id),
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
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
