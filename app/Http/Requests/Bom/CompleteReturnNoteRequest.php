<?php

namespace App\Http\Requests\Bom;

use App\Http\Requests\BaseRequest;

class CompleteReturnNoteRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'scanned_lines'                 => ['required', 'array', 'min:1'],
            'scanned_lines.*.line_id'       => ['required', 'integer', 'exists:return_note_lines,id'],
            'scanned_lines.*.barcode'       => ['required', 'string', 'max:100'],
            'scanned_lines.*.qty_returned'  => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'scanned_lines.required' => 'يجب مسح باركود واحد على الأقل.',
        ];
    }
}
