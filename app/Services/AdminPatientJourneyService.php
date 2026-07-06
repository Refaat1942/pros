<?php

namespace App\Services;

use App\Enums\CaseStage;
use App\Enums\ManufacturingStage;
use App\Models\Appointment;
use App\Models\ApprovalContract;
use App\Models\AuditLog;
use App\Enums\StockWarehouseType;
use App\Models\Bom;
use App\Models\BomItem;
use App\Models\CaseRecord;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\PricingRequest;
use App\Models\Quote;
use App\Support\CaseFinancialSummary;
use App\Support\ClinicTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * سجل تفصيلي لمسار المريض — من الاستقبال حتى المرحلة الحالية.
 */
class AdminPatientJourneyService
{
    /** ترتيب مراحل الدورة — أولاً بالمسار المنطقي ثم بالوقت داخل المرحلة. */
    private const CATEGORY_ORDER = [
        'reception'      => 1,
        'exam'           => 2,
        'technical'      => 3,
        'adjustments'    => 4,
        'cost_calc'      => 5,
        'quote'          => 6,
        'operations'     => 7,
        'manufacturing'  => 8,
        'ready_delivery' => 9,
        'delivered'      => 10,
    ];
    /** @return array<int, AuditLog|null> keyed by patient id */
    public function registrationAuditsForPatients(Collection $patients): array
    {
        if ($patients->isEmpty()) {
            return [];
        }

        $codes = $patients->pluck('patient_code', 'id');

        $logs = AuditLog::query()
            ->where('tag', 'patients')
            ->where('action', 'create')
            ->where(function ($q) use ($codes) {
                foreach ($codes as $code) {
                    $q->orWhere('description', 'like', "%{$code}%");
                }
            })
            ->orderBy('logged_at')
            ->get(['id', 'description', 'user_name', 'logged_at']);

        $map = [];
        foreach ($codes as $patientId => $code) {
            $map[$patientId] = $logs->first(
                fn (AuditLog $log) => str_contains($log->description ?? '', (string) $code)
            );
        }

        return $map;
    }

    /**
     * @return list<array{
     *     sort_at: string,
     *     at_label: string,
     *     category: string,
     *     category_label: string,
     *     title: string,
     *     lines: list<string>,
     *     link?: array{label: string, url: string}
     * }>
     */
    public function build(Patient $patient, ?CaseRecord $case, ?AuditLog $registrationAudit = null): array
    {
        $events = [];

        $this->pushRegistrationEvents($events, $patient, $registrationAudit);
        $this->pushAppointmentEvents($events, $patient);

        if ($case) {
            $this->pushCaseEvents($events, $patient, $case);
        }

        usort($events, function (array $a, array $b): int {
            $order = ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
            if ($order !== 0) {
                return $order;
            }

            return strcmp($a['sort_at'], $b['sort_at']);
        });

        return array_values($events);
    }

    /** @param  list<array<string, mixed>>  $events */
    private function pushRegistrationEvents(array &$events, Patient $patient, ?AuditLog $registrationAudit): void
    {
        $at = $registrationAudit?->logged_at ?? $patient->created_at ?? $patient->registered_at;
        $lines = [
            'رقم المريض: ' . ($patient->patient_code ?? '—'),
            'جهة التعاقد: ' . $patient->displayEntity(),
        ];

        if ($registrationAudit?->user_name) {
            $lines[] = 'سجّله (الاستقبال): ' . $registrationAudit->user_name;
        }

        $firstVisit = $patient->relationLoaded('appointments')
            ? $patient->appointments->last()
            : null;

        if ($firstVisit instanceof Appointment) {
            $lines[] = 'نوع الزيارة: ' . $firstVisit->displayVisitType();
        }

        $this->pushEvent($events, $at, 'reception', 'الاستقبال', 'تسجيل المريض في الاستقبال', $lines, null, 1);
    }

    /** @param  list<array<string, mixed>>  $events */
    private function pushAppointmentEvents(array &$events, Patient $patient): void
    {
        if (! $patient->relationLoaded('appointments')) {
            return;
        }

        foreach ($patient->appointments->sortBy('appointment_date') as $appointment) {
            $transferredAt = $appointment->transferredAt();
            if (! $transferredAt) {
                continue;
            }

            $this->pushEvent(
                $events,
                $transferredAt,
                'reception',
                'الاستقبال',
                'تحويل للعيادة — ' . $appointment->displayVisitType(),
                [
                    'الموعد: ' . ClinicTime::format($appointment->appointment_date, 'd/m/Y') . ' ' . ($appointment->appointment_time ?? ''),
                    'الحالة: ' . $this->appointmentStatusLabel($appointment->status),
                ],
                null,
                2,
            );
        }
    }

    /** @param  list<array<string, mixed>>  $events */
    private function pushCaseEvents(array &$events, Patient $patient, CaseRecord $case): void
    {
        $this->pushEvent(
            $events,
            $case->created_at,
            'reception',
            'الاستقبال',
            'فتح ملف حالة — ' . ($case->case_no ?? '—'),
            array_filter([
                'مرجع الطلب: ' . ($case->order_ref ?? '—'),
                $case->work_order_no ? 'أمر التشغيل: ' . $case->work_order_no : null,
            ]),
            null,
            3,
        );

        $records = $case->relationLoaded('medicalRecords')
            ? $case->medicalRecords
            : collect();

        foreach ($records->sortBy('record_date') as $record) {
            if ($record->status !== MedicalRecord::STATUS_APPROVED) {
                continue;
            }

            $this->pushEvent(
                $events,
                $record->created_at ?? $record->record_date,
                'exam',
                'الكشف الطبي',
                'تشخيص طبي معتمد',
                array_filter([
                    'الطبيب: ' . ($record->doctor_name ?? '—'),
                    $record->diagnosis ? 'التشخيص: ' . \Illuminate\Support\Str::limit($record->diagnosis, 120) : null,
                    $record->prescription ? 'الروشتة: ' . \Illuminate\Support\Str::limit($record->prescription, 120) : null,
                ])
            );
        }

        $spec = $case->relationLoaded('techOrderSpec') ? $case->techOrderSpec : null;
        if ($spec) {
            $itemCount = $spec->relationLoaded('items') ? $spec->items->count() : $spec->items()->count();
            $this->pushEvent(
                $events,
                $spec->submitted_at ?? $spec->created_at,
                'technical',
                'التوصيف الفني',
                'حفظ التوصيف الفني',
                array_filter([
                    $spec->doctor_name ? 'الطبيب: ' . $spec->doctor_name : null,
                    'عدد الأصناف: ' . $itemCount,
                    $spec->tech_notes ? 'ملاحظات: ' . \Illuminate\Support\Str::limit($spec->tech_notes, 100) : null,
                ])
            );
        }

        $bom = $case->relationLoaded('bom') ? $case->bom : null;
        if ($bom && $bom->relationLoaded('items')) {
            $adjustments = $bom->items->where('source', BomItem::SOURCE_ADJUSTMENT);
            if ($adjustments->isNotEmpty()) {
                $lines = $adjustments->map(
                    fn (BomItem $item) => ($item->name ?: $item->stock_item_code) . ' ×' . $item->qty
                )->values()->all();

                $this->pushEvent(
                    $events,
                    $adjustments->max('created_at') ?? $bom->created_at,
                    'adjustments',
                    'المعدلات',
                    'بنود أضافتها المعدلات (' . $adjustments->count() . ')',
                    $lines
                );
            }
        }

        $pricing = $case->relationLoaded('pricingRequest') ? $case->pricingRequest : null;
        if ($pricing instanceof PricingRequest) {
            $this->pushPricingEvents($events, $patient, $case, $pricing);
        }

        if (! $patient->isMilitary()) {
            $this->pushQuoteEvents($events, $case);
            $this->pushApprovalEvents($events, $case);
        }

        $this->pushOperationsEvents($events, $case, $bom);
        $this->pushDeliveryEvents($events, $patient, $case, $bom);
    }

    /** @param  list<array<string, mixed>>  $events */
    private function pushPricingEvents(
        array &$events,
        Patient $patient,
        CaseRecord $case,
        PricingRequest $pricing,
    ): void {
        $lines = [
            'رقم طلب التسعير: ' . ($pricing->request_no ?? '—'),
            'إجمالي عرض السعر (أعلى سعر شراء): ' . $this->money($pricing->computed_total),
            'التكلفة الداخلية (متوسط التكلفة المرجح): ' . $this->money($pricing->internal_total),
        ];

        if ($pricing->doctor_name) {
            $lines[] = 'الطبيب: ' . $pricing->doctor_name;
        }

        $this->pushEvent(
            $events,
            $pricing->created_at ?? $pricing->request_date,
            'cost_calc',
            'حساب التكاليف',
            $patient->isMilitary() ? 'احتساب تكلفة صامت (مسار عسكري)' : 'احتساب التكاليف',
            $lines,
            null,
            1,
        );

        if ($pricing->approved_at) {
            $this->pushEvent(
                $events,
                $pricing->approved_at,
                'cost_calc',
                'حساب التكاليف',
                'اعتماد التسعير',
                array_filter([
                    $pricing->approved_by ? 'اعتمد بواسطة: ' . $pricing->approved_by : null,
                    'الحالة: ' . ($pricing->status_label ?? '—'),
                ]),
                null,
                2,
            );
        }
    }

    /** @param  list<array<string, mixed>>  $events */
    private function pushQuoteEvents(array &$events, CaseRecord $case): void
    {
        if (! $case->relationLoaded('quotes')) {
            return;
        }

        foreach ($case->quotes->sortBy('quote_date') as $quote) {
            $lines = [
                'رقم العرض: ' . ($quote->quote_no ?? '—'),
                'الإجمالي: ' . $this->money($quote->total),
                'الحالة: ' . $this->quoteStatusLabel($quote),
            ];

            if ($quote->relationLoaded('items') && $quote->items->isNotEmpty()) {
                foreach ($quote->items->take(8) as $item) {
                    $lines[] = ($item->name ?: $item->stock_item_code) . ' — ' . $this->money($item->amount);
                }
                if ($quote->items->count() > 8) {
                    $lines[] = '… +' . ($quote->items->count() - 8) . ' بنود أخرى';
                }
            }

            $link = null;
            try {
                $link = ['label' => 'طباعة / عرض PDF', 'url' => route('admin.cases.quote', $case)];
            } catch (\Throwable) {
                // route may be unavailable in some contexts
            }

            $this->pushEvent(
                $events,
                $quote->created_at ?? $quote->quote_date,
                'quote',
                'عرض السعر',
                'إصدار عرض سعر',
                $lines,
                $link
            );
        }
    }

    /** @param  list<array<string, mixed>>  $events */
    private function pushApprovalEvents(array &$events, CaseRecord $case): void
    {
        if ($case->approval_confirmed_at || $case->approval_date) {
            $this->pushEvent(
                $events,
                $case->approval_confirmed_at ?? $case->approval_date,
                'operations',
                'مكتب التشغيل',
                'اعتماد مكتب التشغيل',
                array_filter([
                    $case->work_order_no ? 'أمر التشغيل: ' . $case->work_order_no : null,
                    $case->approval_date ? 'تاريخ الاعتماد: ' . ClinicTime::format($case->approval_date, 'd/m/Y') : null,
                    $case->total_cost ? 'إجمالي الحالة: ' . $this->money($case->total_cost) : null,
                ]),
                null,
                1,
                $this->quotePreviewForCase($case),
            );
        }

        $contract = ApprovalContract::query()
            ->where('case_id', $case->id)
            ->orderByDesc('id')
            ->first();

        if ($contract instanceof ApprovalContract) {
            $this->pushEvent(
                $events,
                $contract->created_at ?? $contract->approval_date,
                'operations',
                'موافقة الجهة',
                'موافقة جهة التعاقد / التأمين',
                array_filter([
                    'رقم العقد: ' . ($contract->contract_no ?? '—'),
                    'المبلغ المعتمد: ' . $this->money($contract->approved_amount),
                    $contract->letter_ref ? 'مرجع الخطاب: ' . $contract->letter_ref : null,
                    $contract->approval_date ? 'تاريخ الموافقة: ' . ClinicTime::format($contract->approval_date, 'd/m/Y') : null,
                ]),
                null,
                2,
                $this->approvalLetterPreviewForContract($contract),
            );
        }
    }

    /** @param  list<array<string, mixed>>  $events */
    private function pushOperationsEvents(array &$events, CaseRecord $case, ?Bom $bom): void
    {
        if ($case->stage_key === CaseRecord::STAGE_OPERATIONS && ! $case->approval_confirmed_at) {
            $this->pushEvent(
                $events,
                $case->updated_at,
                'operations',
                'مكتب التشغيل',
                'الحالة بانتظار قرار مكتب التشغيل',
                ['المرحلة الحالية — لم يُعتمد بعد'],
                null,
                0,
            );
        }

        if ($bom instanceof Bom) {
            if ($bom->released_at) {
                $this->pushEvent(
                    $events,
                    $bom->released_at,
                    'manufacturing',
                    'التصنيع',
                    'صرف خامات من المخزن — BOM ' . ($bom->bom_no ?? '—'),
                    [
                        'مرحلة BOM: ' . $this->bomStageLabel($bom->stage),
                        'مرحلة التصنيع: ' . ManufacturingStage::labelFor($case->manufacturing_stage),
                    ],
                    null,
                    1,
                );
            }

            if ($bom->finished_at) {
                $this->pushEvent(
                    $events,
                    $bom->finished_at,
                    'manufacturing',
                    'التصنيع',
                    'إتمام التصنيع في الورشة',
                    ['BOM: ' . ($bom->bom_no ?? '—')],
                    null,
                    3,
                );
            }
        }

        if ($case->stage_key === CaseRecord::STAGE_MANUFACTURING && $case->manufacturing_stage) {
            $this->pushEvent(
                $events,
                $case->updated_at,
                'manufacturing',
                'التصنيع',
                'المرحلة الفرعية الحالية: ' . ManufacturingStage::labelFor($case->manufacturing_stage),
                ['حالة المسار: ' . CaseStage::labelFor($case->stage_key)],
                null,
                2,
            );
        }
    }

    /** @param  list<array<string, mixed>>  $events */
    private function pushDeliveryEvents(array &$events, Patient $patient, CaseRecord $case, ?Bom $bom): void
    {
        if ($case->stage_key === CaseRecord::STAGE_READY_DELIVERY) {
            $this->pushEvent(
                $events,
                $case->updated_at,
                'ready_delivery',
                'التسليم',
                'جاهز للتسليم',
                array_filter([
                    $bom?->bom_no ? 'BOM: ' . $bom->bom_no : null,
                    $case->work_order_no ? 'أمر التشغيل: ' . $case->work_order_no : null,
                ])
            );
        }

        if ($case->delivered_at) {
            $total = CaseFinancialSummary::totalCost($case);
            $lines = [
                'تاريخ التسليم: ' . ClinicTime::format($case->delivered_at, 'd/m/Y H:i'),
            ];

            if (! $patient->isMilitary()) {
                $lines[] = 'إجمالي الحالة: ' . $this->money($total);
                $lines[] = 'المحصّل: ' . $this->money(CaseFinancialSummary::paidAmount($case, $total));
            } else {
                $lines[] = 'التكلفة الداخلية: ' . $this->money($case->internal_cost ?: $total);
            }

            $this->pushEvent(
                $events,
                $case->delivered_at,
                'delivered',
                'تم التسليم',
                'تسليم الطرف للمريض',
                $lines
            );
        }
    }

    /** @return array{type: string, label: string, url: string, title?: string, ext?: string, contract_no?: string}|null */
    private function quotePreviewForCase(CaseRecord $case): ?array
    {
        if ($case->patient_type === Patient::TYPE_MILITARY) {
            return null;
        }

        $hasQuote = $case->relationLoaded('quotes')
            ? $case->quotes->isNotEmpty()
            : Quote::query()->where('case_id', $case->id)->exists();

        if (! $hasQuote) {
            return null;
        }

        try {
            return [
                'type'  => 'quote',
                'label' => 'عرض السعر',
                'url'   => route('admin.cases.quote', ['case' => $case, 'embed' => 1]),
                'title' => 'عرض السعر — ' . ($case->quote_no ?? $case->case_no ?? ''),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{type: string, label: string, url: string, title?: string, ext?: string, contract_no?: string}|null */
    private function approvalLetterPreviewForContract(ApprovalContract $contract): ?array
    {
        if (! $contract->letter_path || ! Storage::disk('public')->exists($contract->letter_path)) {
            return null;
        }

        return [
            'type'          => 'approval_letter',
            'label'         => 'موافقة الجهة',
            'url'           => asset('storage/' . $contract->letter_path),
            'ext'           => strtolower(pathinfo($contract->letter_path, PATHINFO_EXTENSION)),
            'title'         => 'خطاب الموافقة — ' . ($contract->contract_no ?? ''),
            'contract_no'   => $contract->contract_no,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @param  list<string>  $lines
     * @param  array{label: string, url: string}|null  $link
     * @param  array{type: string, label: string, url: string, title?: string, ext?: string, contract_no?: string}|null  $preview
     */
    private function pushEvent(
        array &$events,
        mixed $at,
        string $category,
        string $categoryLabel,
        string $title,
        array $lines,
        ?array $link = null,
        int $sequence = 50,
        ?array $preview = null,
    ): void {
        $carbon = $this->toCarbon($at);
        if (! $carbon) {
            return;
        }

        $categoryRank = self::CATEGORY_ORDER[$category] ?? 99;

        $event = [
            'sort_order'     => ($categoryRank * 1000) + $sequence,
            'sort_at'        => $carbon->toIso8601String(),
            'at_label'       => ClinicTime::format($carbon, 'd/m/Y H:i'),
            'category'       => $category,
            'category_label' => $categoryLabel,
            'title'          => $title,
            'lines'          => array_values(array_filter($lines, fn ($line) => $line !== null && $line !== '')),
        ];

        if ($link) {
            $event['link'] = $link;
        }

        if ($preview) {
            $event['preview'] = $preview;
        }

        $events[] = $event;
    }

    private function toCarbon(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 0) . ' ج.م';
    }

    private function appointmentStatusLabel(?string $status): string
    {
        return match ($status) {
            Appointment::STATUS_WAITING   => 'بانتظار التحويل',
            Appointment::STATUS_IN_CLINIC => 'في العيادة',
            Appointment::STATUS_QUOTED  => 'تم التسعير',
            Appointment::STATUS_DONE    => 'مكتمل',
            default                     => $status ?? '—',
        };
    }

    private function quoteStatusLabel(Quote $quote): string
    {
        return match ($quote->status) {
            Quote::STATUS_ISSUED   => 'صادر للعميل',
            Quote::STATUS_APPROVED => 'معتمد',
            Quote::STATUS_PENDING  => 'معلق',
            default                => $quote->status_label ?? $quote->status ?? '—',
        };
    }

    private function bomStageLabel(?string $stage): string
    {
        return StockWarehouseType::labelForBomStage($stage);
    }
}
