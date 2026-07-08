<?php

namespace App\Http\Requests\Admin;

use App\Models\WorkflowStagePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkflowPoliciesRequest extends FormRequest
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
                WorkflowStagePolicy::PATHWAY_CIVILIAN,
                WorkflowStagePolicy::PATHWAY_MILITARY,
            ])],
            'policies' => ['required', 'array', 'min:1'],
            'policies.*.stage_key' => ['required', 'string', 'max:64'],
            'policies.*.label' => ['required', 'string', 'max:120'],
            'policies.*.sort' => ['required', 'integer', 'min:1', 'max:99'],
            'policies.*.required' => ['sometimes', 'boolean'],
            'policies.*.auto_skip' => ['sometimes', 'boolean'],
            'policies.*.skip_roles' => ['nullable', 'array'],
            'policies.*.skip_roles.*' => ['string', 'max:32'],
            'policies.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
