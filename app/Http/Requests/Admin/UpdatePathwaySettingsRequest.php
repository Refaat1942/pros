<?php

namespace App\Http\Requests\Admin;

use App\Models\PathwayStep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePathwaySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'pathway' => ['required', 'string', Rule::in([
                PathwayStep::PATHWAY_CIVILIAN,
                PathwayStep::PATHWAY_MILITARY,
            ])],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'steps.*.label' => ['required', 'string', 'max:120'],
            'steps.*.sort' => ['required', 'integer', 'min:1', 'max:99'],
            'steps.*.stage_keys' => ['required', 'array', 'min:1'],
            'steps.*.stage_keys.*' => ['required', 'string', 'max:64'],
            'steps.*.active' => ['sometimes', 'boolean'],
            'steps.*.owner_department' => ['nullable', 'string', 'max:32'],
            'steps.*.action_summary' => ['nullable', 'string', 'max:500'],
            'steps.*.on_complete' => ['nullable', 'string', 'max:255'],
            'steps.*.required' => ['sometimes', 'boolean'],
            'steps.*.auto_skip' => ['sometimes', 'boolean'],
            'steps.*.skip_roles' => ['nullable', 'array'],
            'steps.*.skip_roles.*' => ['string', 'max:32'],
            'steps.*.handlers' => ['nullable', 'array'],
        ];
    }
}
