<?php

namespace App\Http\Requests\Adjustments;

use App\Models\AdjustmentItemGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdjustmentItemGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $group = $this->route('adjustmentItemGroup');
        $groupId = $group instanceof AdjustmentItemGroup ? $group->id : $group;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:120',
                Rule::unique('adjustment_item_groups', 'name')->ignore($groupId),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['sometimes', 'required', 'array', 'min:1'],
            'items.*.stock_item_code' => ['required_with:items', 'string', 'max:64'],
            'items.*.qty' => ['required_with:items', 'numeric', 'min:0.001'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'اسم المجموعة مستخدم مسبقاً — اختر اسمًا آخر.',
        ];
    }
}
