<?php

namespace App\Http\Requests\Admin;

use App\Models\PathwayStep;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAllPathwaySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'pathways' => ['required', 'array'],
            'pathways.civilian' => ['required', 'array', 'min:1'],
            'pathways.military' => ['required', 'array', 'min:1'],
            'pathways.entity' => ['required', 'array', 'min:1'],
        ];

        foreach ([
            PathwayStep::PATHWAY_CIVILIAN,
            PathwayStep::PATHWAY_MILITARY,
            PathwayStep::PATHWAY_ENTITY,
        ] as $pathway) {
            $rules = array_merge(
                $rules,
                UpdatePathwaySettingsRequest::stepItemRules("pathways.{$pathway}.*"),
            );
        }

        return $rules;
    }
}
