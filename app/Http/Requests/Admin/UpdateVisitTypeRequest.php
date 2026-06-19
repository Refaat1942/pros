<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;
use App\Models\VisitType;
use Illuminate\Validation\Rule;

class UpdateVisitTypeRequest extends BaseRequest
{
    public function rules(): array
    {
        /** @var VisitType|null $visitType */
        $visitType = $this->route('visitType');

        return [
            'name' => [
                'sometimes',
                'string',
                'min:2',
                'max:100',
                Rule::unique('visit_types', 'name')->ignore($visitType?->id),
            ],
        ];
    }
}
