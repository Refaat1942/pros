<?php

namespace App\Http\Requests\Stock;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

abstract class SupplierFieldsRequest extends BaseRequest
{
    /** @return array<string, mixed> */
    protected function supplierFieldRules(?int $supplierId = null): array
    {
        $uniqueName = Rule::unique('suppliers', 'name')
            ->whereNull('deleted_at');

        if ($supplierId) {
            $uniqueName->ignore($supplierId);
        }

        return [
            'name' => array_filter([
                $supplierId ? 'sometimes' : 'required',
                'string',
                'min:2',
                'max:255',
                $uniqueName,
            ]),
            'phone' => $this->egyptianMobileRules(),
            'fax' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:191'],
            'address' => ['nullable', 'string', 'max:1000'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'commercial_registry' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:191'],
            'bank_branch' => ['nullable', 'string', 'max:191'],
            'bank_account' => ['nullable', 'string', 'max:64'],
            'iban' => ['nullable', 'string', 'max:34'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'stock_item_ids' => ['nullable', 'array'],
            'stock_item_ids.*' => ['integer', 'exists:stock_items,id'],
        ];
    }
}
