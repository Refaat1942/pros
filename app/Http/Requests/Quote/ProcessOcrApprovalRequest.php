<?php

namespace App\Http\Requests\Quote;

use App\Http\Requests\BaseRequest;

class ProcessOcrApprovalRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'quote_no' => ['required', 'string', 'max:50'],
            'patient_name' => ['required', 'string', 'max:255'],
            'approved_amount' => ['required', 'numeric', 'min:0'],
            'company_name' => ['required', 'string', 'max:255'],
            'letter_ref' => ['nullable', 'string', 'max:100'],
            'letter_date' => ['nullable', 'string', 'max:50'],
            // H-2: المسار يُعاد التحقق منه في الخدمة (approval_letters/ فقط + وجود الملف)؛
            // هنا قيد أولي: يبدأ بـ approval_letters/ ولا يحوي تجاوز مسار (..).
            'letter_path' => ['nullable', 'string', 'max:500', 'regex:#^approval_letters/[^/\\\\]+$#'],
        ];
    }
}
