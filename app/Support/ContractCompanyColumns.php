<?php

namespace App\Support;

/**
 * أعمدة قالب واستيراد جهات التعاقد (Excel).
 */
final class ContractCompanyColumns
{
    public const SHEET_NAME = 'جهات التعاقد';

    /** @return list<string> */
    public static function templateHeaders(): array
    {
        return ['اسم الجهة', 'نوع الهيئة', 'نسبة الخصم %'];
    }

    /** @return array<string, list<string>> */
    public static function importAliases(): array
    {
        return [
            'name' => [
                'اسم الجهة',
                'اسم الهيئة',
                'الجهة',
                'جهة التعاقد',
                'name',
                'company',
            ],
            'is_contracted' => [
                'نوع الجهة',
                'نوع الهيئة',
                'متعاقدة',
                'contracted',
                'type',
            ],
            'discount_percent' => [
                'نسبة الخصم',
                'نسبة الخصم %',
                'الخصم',
                'discount',
                'discount_percent',
            ],
        ];
    }
}
