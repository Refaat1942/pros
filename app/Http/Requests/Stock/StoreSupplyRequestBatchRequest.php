<?php

namespace App\Http\Requests\Stock;

use App\Http\Requests\BaseRequest;
use App\Models\SupplyRequestLine;

/** طلب توريد واحد بأكثر من صنف — نفس قواعد البند الواحد لكل سطر. */
class StoreSupplyRequestBatchRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.line_type' => ['required', 'string', 'in:'.SupplyRequestLine::TYPE_CATALOG.','.SupplyRequestLine::TYPE_NON_CATALOG],
            'lines.*.stock_item_id' => ['required_if:lines.*.line_type,'.SupplyRequestLine::TYPE_CATALOG, 'nullable', 'integer', 'exists:stock_items,id'],
            'lines.*.description' => ['required_if:lines.*.line_type,'.SupplyRequestLine::TYPE_NON_CATALOG, 'nullable', 'string', 'max:500'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
            'lines.*.uom' => ['nullable', 'string', 'max:50'],
            'lines.*.spec' => ['nullable', 'string', 'max:2000'],
            'lines.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.required' => 'أضف صنفاً واحداً على الأقل للطلب.',
            'lines.min' => 'أضف صنفاً واحداً على الأقل للطلب.',
            'lines.max' => 'الحد الأقصى 50 صنفاً في الطلب الواحد.',
            'lines.*.stock_item_id.required_if' => 'يجب اختيار صنف من الكتالوج لكل بند مكود.',
            'lines.*.stock_item_id.exists' => 'صنف مختار غير موجود.',
            'lines.*.description.required_if' => 'اسم/وصف الصنف مطلوب للأصناف غير المكودة.',
            'lines.*.qty.min' => 'الكمية يجب أن تكون 1 على الأقل لكل بند.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $lines = [];
        foreach ((array) $this->input('lines', []) as $line) {
            if (! is_array($line)) {
                continue;
            }
            if (($line['line_type'] ?? null) === SupplyRequestLine::TYPE_CATALOG) {
                $line['description'] = null;
            }
            if (($line['line_type'] ?? null) === SupplyRequestLine::TYPE_NON_CATALOG) {
                $line['stock_item_id'] = null;
            }
            $lines[] = $line;
        }
        $this->merge(['lines' => $lines]);
    }
}
