<?php

namespace App\Http\Requests\Bom;

use App\Http\Requests\BaseRequest;

class DispenseBomRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'scanned_barcodes' => ['required_without:dispense_lines', 'array', 'min:1'],
            'scanned_barcodes.*' => $this->barcodeRules(),
            'dispense_lines' => ['required_without:scanned_barcodes', 'array', 'min:1'],
            'dispense_lines.*.barcode' => array_merge(['required'], $this->barcodeRules()),
            'dispense_lines.*.qty' => ['nullable'],
            'dispense_lines.*.qty_uom' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'scanned_barcodes.required_without' => 'يجب مسح باركود واحد على الأقل أو إرسال بنود الصرف.',
            'dispense_lines.required_without' => 'يجب مسح باركود واحد على الأقل أو إرسال بنود الصرف.',
        ];
    }

    /**
     * @return list<string>|list<array{barcode: string, qty?: mixed, qty_uom?: string}>
     */
    public function dispensePayload(): array
    {
        if ($this->has('dispense_lines')) {
            return $this->input('dispense_lines', []);
        }

        return $this->input('scanned_barcodes', []);
    }
}
