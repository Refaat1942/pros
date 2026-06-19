<?php

namespace App\Http\Requests\Stock;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends BaseRequest
{
    public function rules(): array
    {
        $supplierId = $this->route('supplier')?->id;

        return [
            'name'    => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('suppliers', 'name')->ignore($supplierId),
            ],
            'phone'   => $this->egyptianMobileRules(),
            'email'   => ['nullable', 'email', 'max:191'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes'   => ['nullable', 'string', 'max:1000'],
        ];
    }
}
