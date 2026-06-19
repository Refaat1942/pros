<?php

namespace App\Http\Requests\FittingTrial;

use App\Http\Requests\BaseRequest;
use App\Models\FittingTrial;
use Illuminate\Validation\Rule;

class StoreFittingTrialRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'case_id'     => ['required', 'integer', 'exists:cases,id'],
            'trial1_date' => ['nullable', 'date', 'before_or_equal:today'],
            'trial2_date' => ['nullable', 'date', 'after_or_equal:trial1_date', 'before_or_equal:today'],
            'notes'       => $this->notesRules(2000),
            'status'      => ['nullable', 'string', Rule::in([
                FittingTrial::STATUS_PENDING,
                FittingTrial::STATUS_TRIAL1,
                FittingTrial::STATUS_COMPLETED,
            ])],
        ];
    }
}
