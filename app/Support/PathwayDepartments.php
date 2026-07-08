<?php

namespace App\Support;

/**
 * أقسام/لوحات المسار — للعرض في مصمم المسار.
 */
final class PathwayDepartments
{
    /** @return list<array{value: string, label: string, icon: string}> */
    public static function options(): array
    {
        return [
            ['value' => 'reception', 'label' => 'الاستقبال', 'icon' => '📋'],
            ['value' => 'doctor', 'label' => 'الطبيب / الكشف', 'icon' => '🩺'],
            ['value' => 'spec', 'label' => 'التوصيف الفني', 'icon' => '📐'],
            ['value' => 'adjustments', 'label' => 'المعدلات', 'icon' => '📏'],
            ['value' => 'costing', 'label' => 'التكاليف', 'icon' => '💰'],
            ['value' => 'operations', 'label' => 'مكتب التشغيل', 'icon' => '🎯'],
            ['value' => 'cashier', 'label' => 'الخزنة', 'icon' => '💵'],
            ['value' => 'warehouse', 'label' => 'المخزن', 'icon' => '📦'],
            ['value' => 'workshop', 'label' => 'الورشة', 'icon' => '🏭'],
            ['value' => 'delivery', 'label' => 'التسليمات', 'icon' => '📦'],
        ];
    }

    public static function label(?string $value): string
    {
        foreach (self::options() as $opt) {
            if ($opt['value'] === $value) {
                return $opt['label'];
            }
        }

        return $value ?? '—';
    }
}
