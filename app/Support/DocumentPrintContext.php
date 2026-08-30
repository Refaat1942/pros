<?php

namespace App\Support;

use App\Models\CaseRecord;
use Illuminate\Http\Request;

/** يحدد القسم والمرحلة عند الطباعة لاختيار قالب الوثيقة المناسب. */
final class DocumentPrintContext
{
    public function __construct(
        public ?string $department,
        public ?string $stage,
    ) {}

    public static function fromRequest(Request $request, ?CaseRecord $case = null): self
    {
        $department = self::departmentFromRoute($request);
        $stage = $case?->stage_key ?? self::stageHintFromRoute($request);

        return new self($department, $stage);
    }

    private static function departmentFromRoute(Request $request): ?string
    {
        $prefix = $request->segment(1);

        return match ($prefix) {
            'reception' => 'reception',
            'doctor' => 'doctor',
            'spec' => 'spec',
            'adjustments' => 'adjustments',
            'costing' => 'costing',
            'operations' => 'operations',
            'cashier' => 'cashier',
            'technical' => 'warehouse',
            'workshop' => 'workshop',
            'admin' => 'admin',
            default => null,
        };
    }

    private static function stageHintFromRoute(Request $request): ?string
    {
        $prefix = $request->segment(1);

        return match ($prefix) {
            'reception' => CaseRecord::STAGE_RECEPTION,
            'cashier' => CaseRecord::STAGE_CASHIER,
            'operations' => CaseRecord::STAGE_OPERATIONS,
            'workshop' => CaseRecord::STAGE_MANUFACTURING,
            'spec' => CaseRecord::STAGE_TECHNICAL,
            'adjustments' => CaseRecord::STAGE_ADJUSTMENTS,
            'costing' => CaseRecord::STAGE_COST_CALC,
            default => null,
        };
    }
}
