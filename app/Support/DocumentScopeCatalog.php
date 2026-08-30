<?php

namespace App\Support;

use App\Models\CaseRecord;

/** قوائم الأقسام والمراحل لتخصيص الوثائق في مركز الوثائق. */
final class DocumentScopeCatalog
{
    /** @return list<array{value: string, label: string}> */
    public static function departmentOptions(): array
    {
        $options = [['value' => '', 'label' => 'كل الأقسام (افتراضي عام)']];
        foreach (PathwayDepartments::options() as $opt) {
            $options[] = ['value' => $opt['value'], 'label' => $opt['label']];
        }
        $options[] = ['value' => 'admin', 'label' => 'الإدارة العامة'];

        return $options;
    }

    /** @return list<array{value: string, label: string}> */
    public static function stageOptions(): array
    {
        $options = [['value' => '', 'label' => 'كل المراحل (افتراضي عام)']];

        $stages = [
            CaseRecord::STAGE_RECEPTION => 'الاستقبال',
            CaseRecord::STAGE_EXAM => 'الكشف الطبي',
            CaseRecord::STAGE_TECHNICAL => 'التوصيف الفني',
            CaseRecord::STAGE_ADJUSTMENTS => 'المعدلات',
            CaseRecord::STAGE_COST_CALC => 'التكاليف / الاعتماد',
            CaseRecord::STAGE_SERVICES_APPROVAL => 'اعتماد الخدمات',
            CaseRecord::STAGE_QUOTE => 'عرض السعر',
            CaseRecord::STAGE_OPERATIONS => 'مكتب التشغيل',
            CaseRecord::STAGE_CASHIER => 'الخزنة',
            CaseRecord::STAGE_MANUFACTURING => 'الإنتاج',
            CaseRecord::STAGE_READY_DELIVERY => 'جاهز للتسليم',
            CaseRecord::STAGE_DELIVERED => 'تم التسليم',
        ];

        foreach ($stages as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }

    public static function departmentLabel(?string $value): string
    {
        if ($value === null || $value === '') {
            return 'كل الأقسام';
        }

        if ($value === 'admin') {
            return 'الإدارة العامة';
        }

        return PathwayDepartments::label($value);
    }

    public static function stageLabel(?string $value): string
    {
        if ($value === null || $value === '') {
            return 'كل المراحل';
        }

        foreach (self::stageOptions() as $opt) {
            if ($opt['value'] === $value) {
                return $opt['label'];
            }
        }

        return PathwayStepLabels::label($value);
    }

    public static function scopeKey(?string $department, ?string $stage): ?string
    {
        $dept = trim((string) $department);
        $stage = trim((string) $stage);

        if ($dept === '' && $stage === '') {
            return null;
        }

        if ($dept !== '' && $stage !== '') {
            return $dept.':'.$stage;
        }

        if ($dept !== '') {
            return $dept.':*';
        }

        return '*:'.$stage;
    }

    public static function scopeLabel(?string $scopeKey): string
    {
        if ($scopeKey === null || $scopeKey === '') {
            return 'افتراضي عام';
        }

        $parts = explode(':', $scopeKey, 2);
        $dept = $parts[0] ?? '';
        $stage = $parts[1] ?? '';

        if ($dept === '*') {
            return 'مرحلة: '.self::stageLabel($stage === '*' ? '' : $stage);
        }
        if ($stage === '*') {
            return 'قسم: '.self::departmentLabel($dept);
        }

        return self::departmentLabel($dept).' · '.self::stageLabel($stage);
    }
}
