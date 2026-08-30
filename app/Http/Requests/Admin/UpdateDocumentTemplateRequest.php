<?php

namespace App\Http\Requests\Admin;

use App\Services\DocumentTemplateService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() || $this->user()?->hasPermission('manage-permissions');
    }

    public function rules(): array
    {
        $document = (string) $this->route('document');
        if (! app(DocumentTemplateService::class)->exists($document)) {
            return [];
        }

        $def = app(DocumentTemplateService::class)->definition($document);
        $rules = [];

        foreach ($def['fields'] as $field) {
            $key = $field['key'];
            if ($field['type'] === 'bool') {
                $rules[$key] = ['sometimes', 'boolean'];
            } else {
                $rules[$key] = ['nullable', 'string', 'max:2000'];
            }
        }

        $rules['font_scale'] = ['nullable', 'in:compact,normal'];

        return $rules;
    }
}
