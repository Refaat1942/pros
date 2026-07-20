<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;
use App\Services\FormFieldPolicyService;

class UpdateFormFieldSettingsRequest extends BaseRequest
{
    public function rules(): array
    {
        $catalog = app(FormFieldPolicyService::class)->catalogForAdmin();
        $rules = ['fields' => ['required', 'array']];

        foreach ($catalog as $feature => $fields) {
            foreach ($fields as $fieldMeta) {
                $key = $fieldMeta['field'];
                $rules["fields.{$feature}.{$key}"] = ['required', 'boolean'];
            }
        }

        return $rules;
    }
}
