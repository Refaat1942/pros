<?php

namespace App\Services;

use App\Enums\CaseStage;
use App\Enums\StockWarehouseType;
use App\Models\Bom;
use App\Models\CaseRecord;
use App\Models\Patient;

/**
 * استعلام متابعة المريض من لوحة الاستقبال — تفاصيل كاملة + مسار + شريط تقدم.
 */
class ReceptionSelfServiceService
{
    public function __construct(private readonly PublicTrackingService $publicTrackingService)
    {
    }

    public function lookup(string $query): ?array
    {
        $patient = $this->findPatient($query);

        if (! $patient) {
            return null;
        }

        $patient->load([
            'contractCompany:id,name,is_military',
            'militaryRank:id,name',
            'cases' => fn ($q) => $q->with('bom:id,case_id,stage')->orderByDesc('id'),
        ]);

        $cases = $patient->cases;
        $activeCase = $cases->first(fn (CaseRecord $c) => $c->stage_key !== CaseRecord::STAGE_DELIVERED)
            ?? $cases->first();

        $trackingUid = $patient->tracking_uid ?: $activeCase?->tracking_uid;
        $tracking = $trackingUid
            ? $this->publicTrackingService->resolve($trackingUid)
            : $this->fallbackTracking($patient, $activeCase);
        $totalSteps = count($tracking['steps']);
        $currentIndex = $tracking['current_index'];
        $progressPercent = $totalSteps > 1
            ? (int) round(($currentIndex / ($totalSteps - 1)) * 100)
            : 0;

        return [
            'patient' => [
                'id'               => $patient->id,
                'name'             => $patient->name,
                'phone'            => $patient->phone,
                'national_id'      => $patient->national_id,
                'patient_code'     => $patient->patient_code,
                'patient_qr'       => $patient->patient_qr,
                'tracking_uid'     => $patient->tracking_uid,
                'patient_type'     => $patient->patient_type,
                'patient_type_label' => $patient->isMilitary() ? 'عسكري' : 'مدني',
                'rank'             => $patient->rank ?: $patient->militaryRank?->name,
                'sovereign_entity' => $patient->sovereign_entity,
                'company_name'     => $patient->company_name ?: $patient->contractCompany?->name,
                'registered_at'    => $patient->registered_at?->format('Y-m-d'),
                'last_visit_at'    => $patient->last_visit_at?->format('Y-m-d'),
                'status'           => $patient->status,
            ],
            'active_case' => $activeCase ? $this->formatCase($activeCase) : null,
            'cases' => $cases->map(fn (CaseRecord $c) => $this->formatCase($c))->values()->all(),
            'tracking' => $tracking,
            'progress_percent' => $progressPercent,
            'queue_position'   => $this->queuePosition($activeCase),
            'expected_delivery' => $this->expectedDelivery($activeCase),
        ];
    }

    private function findPatient(string $query): ?Patient
    {
        $q = trim($query);

        if ($q === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $q);

        return Patient::query()
            ->where(function ($builder) use ($q, $digits) {
                $builder->where('phone', $q)
                    ->orWhere('patient_code', $q)
                    ->orWhere('patient_qr', $q)
                    ->orWhere('tracking_uid', $q)
                    ->orWhere('national_id', $q)
                    ->orWhere('name', 'like', "%{$q}%");

                if ($digits !== '' && strlen($digits) >= 6) {
                    $builder->orWhere('phone', $digits)
                        ->orWhere('phone', 'like', "%{$digits}%")
                        ->orWhere('national_id', $digits);
                }
            })
            ->orderByDesc('id')
            ->first();
    }

    private function formatCase(CaseRecord $case): array
    {
        return [
            'id'                  => $case->id,
            'case_no'             => $case->case_no,
            'order_ref'           => $case->order_ref,
            'work_order_no'       => $case->work_order_no,
            'quote_no'            => $case->quote_no,
            'stage_key'           => $case->stage_key,
            'stage_label'         => CaseStage::labelFor($case->stage_key),
            'manufacturing_stage' => $case->manufacturing_stage,
            'bom_stage'           => $case->bom?->stage,
            'bom_stage_label'     => $this->bomStageLabel($case->bom?->stage),
            'path'                => $case->path,
            'delivered_at'        => $case->delivered_at?->format('Y-m-d'),
            'created_at'          => $case->created_at?->format('Y-m-d H:i'),
        ];
    }

    private function bomStageLabel(?string $stage): ?string
    {
        if (! $stage) {
            return null;
        }

        return StockWarehouseType::labelForBomStage($stage);
    }

    private function queuePosition(?CaseRecord $case): ?int
    {
        if (! $case || $case->stage_key !== CaseRecord::STAGE_MANUFACTURING) {
            return null;
        }

        return CaseRecord::where('stage_key', CaseRecord::STAGE_MANUFACTURING)
            ->where('created_at', '<', $case->created_at)
            ->count() + 1;
    }

    private function expectedDelivery(?CaseRecord $case): ?string
    {
        if (! $case) {
            return 'بعد إنشاء الحالة والكشف الطبي';
        }

        if ($case->stage_key === CaseRecord::STAGE_DELIVERED) {
            return $case->delivered_at?->format('Y-m-d') ?? 'تم التسليم';
        }

        if ($case->stage_key === CaseRecord::STAGE_MANUFACTURING) {
            return 'خلال 3–5 أيام عمل';
        }

        if ($case->stage_key === CaseRecord::STAGE_READY_DELIVERY) {
            return 'جاهز — يمكن التسليم الآن';
        }

        if (in_array($case->stage_key, [
            CaseRecord::STAGE_ADJUSTMENTS,
            CaseRecord::STAGE_COST_CALC,
            CaseRecord::STAGE_QUOTE,
            CaseRecord::STAGE_OPERATIONS,
        ], true)) {
            return 'بعد اعتماد مكتب التشغيل';
        }

        return 'بعد اعتماد الطلب وبدء التصنيع';
    }

    /** @return array{tracking_uid: ?string, pathway: string, stage_label: string, current_index: int, steps: list<array{key: string, label: string, status: string}>} */
    private function fallbackTracking(Patient $patient, ?CaseRecord $case): array
    {
        $isMilitary = $patient->isMilitary();
        $steps = $isMilitary
            ? [
                ['key' => 'registered', 'label' => 'تسجيل واستقبال', 'status' => 'current'],
                ['key' => 'exam', 'label' => 'الكشف الطبي', 'status' => 'pending'],
                ['key' => 'technical', 'label' => 'التوصيف الفني', 'status' => 'pending'],
                ['key' => 'manufacturing', 'label' => 'التصنيع بالورشة', 'status' => 'pending'],
                ['key' => 'ready', 'label' => 'جاهز للتسليم', 'status' => 'pending'],
                ['key' => 'delivered', 'label' => 'تم التسليم', 'status' => 'pending'],
            ]
            : [
                ['key' => 'registered', 'label' => 'تسجيل واستقبال', 'status' => 'current'],
                ['key' => 'exam', 'label' => 'الكشف الطبي', 'status' => 'pending'],
                ['key' => 'technical', 'label' => 'التوصيف الفني', 'status' => 'pending'],
                ['key' => 'approval', 'label' => 'اعتماد عروض الأسعار والموافقات', 'status' => 'pending'],
                ['key' => 'manufacturing', 'label' => 'التصنيع بالورشة', 'status' => 'pending'],
                ['key' => 'ready', 'label' => 'جاهز للتسليم', 'status' => 'pending'],
                ['key' => 'delivered', 'label' => 'تم التسليم', 'status' => 'pending'],
            ];

        if (! $case) {
            return [
                'tracking_uid'  => null,
                'pathway'       => $isMilitary ? 'military' : 'civilian',
                'stage_label'   => 'تم التسجيل — في انتظار الكشف الطبي',
                'current_index' => 0,
                'steps'         => $steps,
            ];
        }

        return [
            'tracking_uid'    => null,
            'pathway'         => $isMilitary ? 'military' : 'civilian',
            'stage_label'     => CaseStage::labelFor($case->stage_key),
            'current_index'   => 0,
            'steps'           => $steps,
        ];
    }
}
