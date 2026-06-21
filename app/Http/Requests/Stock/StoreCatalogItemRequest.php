<?php

namespace App\Http\Requests\Stock;

use App\Http\Requests\BaseRequest;

class StoreCatalogItemRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name'                        => ['required', 'string', 'max:255'],
            'spec'                        => ['nullable', 'string', 'max:500'],
            'category_id'                 => ['required', 'integer', 'exists:stock_categories,id'],
            'qty'                         => ['nullable', 'integer', 'min:0'],
            'prices'                      => ['required', 'array', 'min:1'],
            'prices.*.label'              => ['required', 'string', 'max:255'],
            'prices.*.supplier_id'        => ['required', 'integer', 'exists:suppliers,id'],
            'prices.*.supplier_item_code' => ['nullable', 'string', 'max:100'],
            'prices.*.amount'             => ['required', 'numeric', 'min:0.01'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $prices = $this->input('prices', []);

        foreach ($prices as $i => $row) {
            if (empty($row['supplier_item_code']) && ! empty($row['itemCode'])) {
                $prices[$i]['supplier_item_code'] = $row['itemCode'];
            }

            if (array_key_exists('supplier_item_code', $prices[$i])
                && trim((string) $prices[$i]['supplier_item_code']) === '') {
                $prices[$i]['supplier_item_code'] = null;
            }
        }

        $this->merge(['prices' => $prices]);
    }

    public function messages(): array
    {
        return [
            'name.required'                   => 'يرجى إدخال اسم الصنف.',
            'category_id.required'            => 'يرجى اختيار الفئة.',
            'prices.required'                 => 'يرجى إضافة سعر واحد على الأقل.',
            'prices.min'                      => 'يرجى إضافة سعر واحد على الأقل.',
            'prices.*.supplier_id.required'   => 'يرجى اختيار المورد لكل سعر.',
            'prices.*.amount.required'        => 'يرجى إدخال السعر.',
        ];
    }
}
