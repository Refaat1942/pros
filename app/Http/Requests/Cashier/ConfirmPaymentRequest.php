<?php

namespace App\Http\Requests\Cashier;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', 'string', Rule::in(PaymentMethod::values())],
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:99999999'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'method.required' => 'يجب تحديد وسيلة الدفع.',
            'method.in' => 'وسيلة دفع غير صالحة.',
            'amount.numeric' => 'قيمة المبلغ غير صالحة.',
            'amount.min' => 'قيمة المبلغ غير صالحة.',
        ];
    }
}
