<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/** قائمة الأسلحة/الفروع العسكرية — ثابتة حسب متطلبات العميل. */
final class MilitaryWeapons
{
    /** @return list<string> */
    public static function labels(): array
    {
        return [
            'الاسلحة و الذخيرة',
            'المدفعية',
            'المشاة',
            'الجوية',
            'البحرية',
            'الدفاع الجوي',
            'الأمن الحربي',
            'الاستطلاع',
            'المدرعات',
            'الحرب الإلكترونية',
            'الإمداد و التموين',
            'الإشارة',
            'الحرس الجمهوري',
            'الصاعقة',
            'المظلات',
        ];
    }

    /** @return array<int, string|\Illuminate\Contracts\Validation\ValidationRule> */
    public static function validationRule(): array
    {
        return ['required', 'string', Rule::in(self::labels())];
    }

    /** @return array<int, string|\Illuminate\Contracts\Validation\ValidationRule> */
    public static function optionalValidationRule(): array
    {
        return ['nullable', 'string', Rule::in(self::labels())];
    }
}
