<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;

class UpdateNotificationSettingsRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'sound_enabled' => ['sometimes', 'boolean'],
            'reminder_minutes' => ['required', 'integer', 'min:1', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'reminder_minutes.required' => 'يرجى تحديد فترة التذكير.',
            'reminder_minutes.min' => 'الحد الأدنى للتذكير دقيقة واحدة.',
            'reminder_minutes.max' => 'الحد الأقصى للتذكير 60 دقيقة.',
        ];
    }
}
