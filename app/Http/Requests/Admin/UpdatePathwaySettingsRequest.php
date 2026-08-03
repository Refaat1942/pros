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
        return array_merge([
            'pathway' => ['required', 'string', Rule::in([
                PathwayStep::PATHWAY_CIVILIAN,
                PathwayStep::PATHWAY_MILITARY,
                PathwayStep::PATHWAY_ENTITY,
            ])],
            'steps' => ['required', 'array', 'min:1'],
        ], self::stepItemRules('steps.*'));
    }

    /** @return array<string, mixed> */
    public static function stepItemRules(string $prefix): array
    {
        return [
            "{$prefix}.key" => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            "{$prefix}.label" => ['required', 'string', 'max:120'],
            "{$prefix}.sort" => ['required', 'integer', 'min:1', 'max:99'],
            "{$prefix}.stage_keys" => ['required', 'array', 'min:1'],
            "{$prefix}.stage_keys.*" => ['required', 'string', 'max:64'],
            "{$prefix}.active" => ['sometimes', 'boolean'],
            "{$prefix}.owner_department" => ['nullable', 'string', 'max:32'],
            "{$prefix}.action_summary" => ['nullable', 'string', 'max:500'],
            "{$prefix}.on_complete" => ['nullable', 'string', 'max:255'],
            "{$prefix}.next_step_key" => ['nullable', 'string', 'max:64', 'regex:/^(_completed|[a-z0-9_]+)$/'],
            "{$prefix}.required" => ['sometimes', 'boolean'],
            "{$prefix}.auto_skip" => ['sometimes', 'boolean'],
            "{$prefix}.skip_roles" => ['nullable', 'array'],
            "{$prefix}.skip_roles.*" => ['string', 'max:32'],
            "{$prefix}.handlers" => ['nullable', 'array'],
        ];
    }
}
