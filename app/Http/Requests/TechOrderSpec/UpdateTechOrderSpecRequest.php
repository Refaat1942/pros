<?php

namespace App\Http\Requests\TechOrderSpec;

use App\Http\Requests\BaseRequest;
use App\Services\FormFieldPolicyService;

class UpdateTechOrderSpecRequest extends BaseRequest
{
    public function rules(): array
    {
        $policy = app(FormFieldPolicyService::class);

        return [
            'tech_notes' => $this->notesRules(5000, $policy->isRequired('spec', 'tech_notes')),
            'written_items' => $policy->isRequired('spec', 'written_items')
                // مريض صرف سريع بس (عكاز…) مالوش استمارة طرف.
                ? ['required_without:quick_dispense_items', 'nullable', 'string', 'max:5000']
                : ['nullable', 'string', 'max:5000'],
            // منتجات الصرف السريع بتتكتب كتابة (اسم + كمية) — المعدلات بتحددها من المخزن.
            'quick_dispense_items' => ['sometimes', 'nullable', 'array', 'max:50'],
            'quick_dispense_items.*.name' => ['nullable', 'string', 'max:255'],
            'quick_dispense_items.*.qty' => ['required', 'numeric', 'min:0.001', 'max:9999'],
            'items' => ['sometimes', 'nullable', 'array'],
            'items.*.stock_item_code' => ['required_with:items', 'string', 'max:500'],
            'items.*.name' => ['required_with:items', 'string', 'min:1', 'max:255'],
            'items.*.qty' => ['required_with:items', ...$this->decimalQtyRules()],
            'items.*.group_label' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.qty.min' => 'الكمية يجب أن تكون 0.001 على الأقل لكل بند.',
        ];
    }
}
