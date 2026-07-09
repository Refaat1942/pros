<?php

namespace App\Support;

use App\Models\CaseRecord;
use App\Models\PathwayStep;
use App\Services\PathwayConfigService;

/**
 * الترتيب الرسمي للمسارات — مطابق لجدول العميل (مدني / عسكري / جهات).
 */
final class PathwayDefaultSteps
{
    /** @return array<string, list<array<string, mixed>>> */
    public static function all(): array
    {
        return [
            PathwayStep::PATHWAY_CIVILIAN => self::directCivilian(),
            PathwayStep::PATHWAY_MILITARY => self::military(),
            PathwayStep::PATHWAY_ENTITY => self::entity(),
        ];
    }

    /** مدني نقدي — 11 خطوة */
    private static function directCivilian(): array
    {
        return array_merge(
            self::openSteps(),
            [
                self::operationsWorkOrderStep(6),
            ],
            self::productionAndCloseSteps(7),
        );
    }

    /** عسكري — نفس ترتيب المدني (11 خطوة) — الخزنة تُتخطى تلقائياً */
    private static function military(): array
    {
        $steps = self::openSteps('الطبيب', 'تسجيل عسكري — فتح ملف — موعد');
        foreach ($steps as &$step) {
            if ($step['key'] === 'adjustments') {
                $step['auto_skip'] = true;
                $step['skip_roles'] = ['admin', 'adjustments'];
            }
            if ($step['key'] === 'cost_calc') {
                $step['action_summary'] = 'تسجيل التكلفة صامتاً (مديونية سيادية)';
            }
        }
        unset($step);

        return array_merge(
            $steps,
            [self::operationsWorkOrderStep(6)],
            self::productionAndCloseSteps(7, cashierAutoSkip: true),
        );
    }

    /** جهات تعاقد — 13 خطوة (عرض سعر + خطاب موافقة) */
    private static function entity(): array
    {
        $steps = self::openSteps();
        foreach ($steps as &$step) {
            if ($step['key'] === 'cost_calc') {
                $step['on_complete'] = 'ينتقل لإصدار عرض السعر';
                $step['next_step_key'] = 'quote';
            }
        }
        unset($step);

        return array_merge(
            $steps,
            [
                [
                    'key' => 'quote',
                    'label' => 'التشغيل — إصدار عرض سعر',
                    'sort' => 6,
                    'owner_department' => 'operations',
                    'action_summary' => 'طباعة عرض السعر وتسليمه للمريض / الجهة',
                    'on_complete' => 'ينتقل للاستقبال',
                    'next_step_key' => 'entity_return',
                    'stage_keys' => [CaseRecord::STAGE_QUOTE],
                    'required' => true,
                    'auto_skip' => false,
                    'skip_roles' => [],
                    'handlers' => [],
                ],
                [
                    'key' => 'entity_return',
                    'label' => 'الاستقبال — خطاب الموافقة',
                    'sort' => 7,
                    'owner_department' => 'reception',
                    'action_summary' => 'استلام عرض السعر — عودة المريض بخطاب موافقة الجهة للرفع',
                    'on_complete' => 'ينتقل للتشغيل',
                    'next_step_key' => 'operations_wo',
                    'stage_keys' => [CaseRecord::STAGE_OPERATIONS],
                    'required' => true,
                    'auto_skip' => false,
                    'skip_roles' => [],
                    'handlers' => ['entity_approval' => 'reception'],
                ],
                self::operationsWorkOrderStep(8, 'الاطلاع على خطاب الموافقة — إصدار أمر شغل'),
            ],
            self::productionAndCloseSteps(9),
        );
    }

    /** @return list<array<string, mixed>> */
    private static function openSteps(string $examLabel = 'الطبيب', ?string $receptionSummary = null): array
    {
        return [
            [
                'key' => 'reception',
                'label' => 'الاستقبال',
                'sort' => 1,
                'owner_department' => 'reception',
                'action_summary' => $receptionSummary ?? 'تسجيل المريض — فتح الملف — حجز الموعد — QR',
                'on_complete' => 'ينتقل للطبيب',
                'next_step_key' => 'exam',
                'stage_keys' => [CaseRecord::STAGE_RECEPTION],
                'required' => true,
                'auto_skip' => false,
                'skip_roles' => [],
                'handlers' => [],
            ],
            [
                'key' => 'exam',
                'label' => $examLabel,
                'sort' => 2,
                'owner_department' => 'doctor',
                'action_summary' => 'الكشف الطبي — تقرير — تحويل للتوصيف',
                'on_complete' => 'ينتقل للتوصيف',
                'next_step_key' => 'technical',
                'stage_keys' => [CaseRecord::STAGE_EXAM],
                'required' => false,
                'auto_skip' => false,
                'skip_roles' => ['admin', 'doctor'],
                'handlers' => [],
            ],
            [
                'key' => 'technical',
                'label' => 'التوصيف',
                'sort' => 3,
                'owner_department' => 'spec',
                'action_summary' => 'كتابة المواصفات الفنية واختيار الأصناف',
                'on_complete' => 'ينتقل للمعدلات',
                'next_step_key' => 'adjustments',
                'stage_keys' => [CaseRecord::STAGE_TECHNICAL],
                'required' => true,
                'auto_skip' => false,
                'skip_roles' => [],
                'handlers' => [],
            ],
            self::adjustmentsStep(4),
            self::costCalcStep(5),
        ];
    }

    /** @param  list<string>  $skipRoles */
    private static function adjustmentsStep(int $sort, bool $autoSkip = false, array $skipRoles = ['admin', 'adjustments', 'operations']): array
    {
        return [
            'key' => 'adjustments',
            'label' => 'المعدلات',
            'sort' => $sort,
            'owner_department' => 'adjustments',
            'action_summary' => 'مراجعة البنود — تعديل الكميات',
            'on_complete' => 'ينتقل للتكاليف',
            'next_step_key' => 'cost_calc',
            'stage_keys' => [CaseRecord::STAGE_ADJUSTMENTS],
            'required' => false,
            'auto_skip' => $autoSkip,
            'skip_roles' => $skipRoles,
            'handlers' => [],
        ];
    }

    private static function costCalcStep(int $sort, ?string $summary = null): array
    {
        return [
            'key' => 'cost_calc',
            'label' => 'التكاليف',
            'sort' => $sort,
            'owner_department' => 'costing',
            'action_summary' => $summary ?? 'احتساب التكلفة — تجميد اللقطة — تأكيد السعر',
            'on_complete' => 'ينتقل للتشغيل',
            'next_step_key' => 'operations_wo',
            'stage_keys' => [CaseRecord::STAGE_COST_CALC],
            'required' => true,
            'auto_skip' => false,
            'skip_roles' => [],
            'handlers' => [],
        ];
    }

    private static function operationsWorkOrderStep(int $sort, ?string $summary = null): array
    {
        return [
            'key' => 'operations_wo',
            'label' => 'التشغيل — إصدار أمر شغل',
            'sort' => $sort,
            'owner_department' => 'operations',
            'action_summary' => $summary ?? 'اعتماد الحالة — إصدار أمر الشغل (WO)',
            'on_complete' => 'ينتقل للمخزن',
            'next_step_key' => 'warehouse',
            'stage_keys' => [CaseRecord::STAGE_OPERATIONS],
            'required' => true,
            'auto_skip' => false,
            'skip_roles' => [],
            'handlers' => ['work_order' => 'operations'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function productionAndCloseSteps(int $startSort, bool $cashierAutoSkip = false): array
    {
        $sort = $startSort;

        return [
            [
                'key' => 'warehouse',
                'label' => 'المخزن — صرف مواد',
                'sort' => $sort++,
                'owner_department' => 'warehouse',
                'action_summary' => 'صرف خامات BOM بالباركود — إذن صرف',
                'on_complete' => 'ينتقل للورشة',
                'next_step_key' => 'workshop',
                'stage_keys' => [CaseRecord::STAGE_MANUFACTURING],
                'required' => true,
                'auto_skip' => false,
                'skip_roles' => [],
                'handlers' => ['barcode_dispense' => 'warehouse'],
            ],
            [
                'key' => 'workshop',
                'label' => 'الورشة — تصنيع',
                'sort' => $sort++,
                'owner_department' => 'workshop',
                'action_summary' => 'تصنيع الطرف — إغلاق الجودة',
                'on_complete' => 'ينتقل للتشغيل',
                'next_step_key' => 'operations_release',
                'stage_keys' => [CaseRecord::STAGE_MANUFACTURING],
                'required' => true,
                'auto_skip' => false,
                'skip_roles' => [],
                'handlers' => ['production' => 'workshop'],
            ],
            [
                'key' => 'operations_release',
                'label' => 'التشغيل — إصدار أمر صرف',
                'sort' => $sort++,
                'owner_department' => 'operations',
                'action_summary' => 'طباعة إذن الصرف النهائي — تجهيز للتحصيل',
                'on_complete' => 'ينتقل للخزنة',
                'next_step_key' => 'cashier',
                'stage_keys' => [CaseRecord::STAGE_READY_DELIVERY],
                'required' => true,
                'auto_skip' => false,
                'skip_roles' => [],
                'handlers' => [],
            ],
            [
                'key' => 'cashier',
                'label' => 'الخزنة — إصدار إيصال دفع',
                'sort' => $sort++,
                'owner_department' => 'cashier',
                'action_summary' => $cashierAutoSkip
                    ? 'تُتخطى — مديونية سيادية (لا تحصيل نقدي)'
                    : 'تحصيل المبلغ — إصدار إيصال الدفع',
                'on_complete' => 'ينتقل للاستقبال',
                'next_step_key' => 'delivery',
                'stage_keys' => [CaseRecord::STAGE_CASHIER],
                'required' => ! $cashierAutoSkip,
                'auto_skip' => $cashierAutoSkip,
                'skip_roles' => $cashierAutoSkip ? [] : ['admin'],
                'handlers' => ['collect_payment' => 'cashier'],
            ],
            [
                'key' => 'delivery',
                'label' => 'الاستقبال — التسليم',
                'sort' => $sort,
                'owner_department' => 'reception',
                'action_summary' => 'مسح QR — تسليم الطرف — إغلاق الحالة',
                'on_complete' => 'الحالة مكتملة',
                'next_step_key' => PathwayConfigService::NEXT_COMPLETED,
                'stage_keys' => [CaseRecord::STAGE_DELIVERED],
                'required' => true,
                'auto_skip' => false,
                'skip_roles' => [],
                'handlers' => [],
            ],
        ];
    }
}
