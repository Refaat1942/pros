<?php

namespace App\Support;

/**
 * تسميات عربية ثابتة لمفاتيح خطوات المسار — للرسائل والإشعارات (بدون مفاتيح تقنية).
 */
final class PathwayStepLabels
{
    /** @var array<string, string> */
    private const LABELS = [
        'reception' => 'الاستقبال',
        'exam' => 'الطبيب / الكشف',
        'technical' => 'التوصيف الفني',
        'adjustments' => 'المعدلات',
        'cost_calc' => 'الاعتماد',
        'services_approval' => 'إدارة الخدمات — تصديق',
        'quote' => 'إصدار عرض السعر',
        'entity_return' => 'الاستقبال — خطاب الموافقة',
        'operations_wo' => 'مكتب التشغيل — إصدار أمر شغل',
        'operations_release' => 'مكتب التشغيل — إصدار أمر صرف',
        'warehouse' => 'المخزن — صرف مواد',
        'workshop' => 'قسم الإنتاج — تصنيع',
        'cashier' => 'الخزنة — تحصيل الدفع',
        'delivery' => 'التسليم للمريض',
    ];

    public static function label(string $stepKey): string
    {
        if (isset(self::LABELS[$stepKey])) {
            return self::LABELS[$stepKey];
        }

        $dept = PathwayDepartments::label($stepKey);

        return $dept !== $stepKey ? $dept : $stepKey;
    }
}
