<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'group_label' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'body_html' => ['nullable', 'string', 'max:50000'],
            'reference' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }
}
