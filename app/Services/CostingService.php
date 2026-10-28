<?php

namespace App\Services;

use App\Enums\WorkflowEvent;
use App\Models\CaseRecord;
use Illuminate\Support\Facades\DB;

/**
 * محرك التكاليف (الخطوة 5) — توقف صريح عند cost_calc للمراجعة اليدوية.
 *
 * بعد إغلاق المعدلات تُحتسب التكلفة وتتوقف الحالة هنا.
 * تأكيد التكاليف يُصدر عرض السعر (مدني) ويُحوّل لمكتب التشغيل.
 */
class CostingService
{
    public function __construct(
        private readonly WorkflowService $workflowService,
        private readonly PricingService $pricingService,
        private readonly QuoteService $quoteService,
        private readonly OperationsService $operationsService,
        private readonly MilitaryMarkupService $militaryMarkupService,
        private readonly SpecEditRequestService $editRequestService,
        private readonly CostingSnapshotService $snapshotService,
    ) {}

    /**
     * استقبال الحالة من المعدلات — احتساب التكلفة والتوقف عند cost_calc.
     */
    public function receiveFromAdjustments(CaseRecord $case): CaseRecord
    {
        if ($case->stage_key !== CaseRecord::STAGE_ADJUSTMENTS) {
            abort(422, 'الحالة ليست في مرحلة المعدلات.');
        }

        DB::transaction(function () use ($case) {
            $case = CaseRecord::lockForUpdate()->findOrFail($case->id);

            $this->workflowService->advance($case, WorkflowEvent::AdjustmentsCompleted->value);

            $pricingRequest = $this->pricingService->createAndCalculateForCase($case->fresh());

            // تجميد لقطة التكاليف الأولية (بلا نمط بعد → سعر البيع = المواد).
            $pricingRequest = $this->snapshotService->refresh($pricingRequest);

            CaseRecord::where('id', $case->id)->update([
                'internal_cost' => (float) $pricingRequest->internal_total,
            ]);

            $this->militaryMarkupService->apply($case->fresh());

            AuditService::log(
                action: 'receive',
                description: "استلام التكاليف — {$case->case_no} — بانتظار التأكيد",
                tag: 'pricing',
                after: [
                    'case_id' => $case->id,
                    'pricing_request' => $pricingRequest->request_no,
                    'computed_total' => $pricingRequest->computed_total,
                    'internal_total' => $pricingRequest->internal_total,
                    'stage_key' => CaseRecord::STAGE_COST_CALC,
                ],
            );
        });

        return $case->fresh()->load(['patient', 'pricingRequest.items', 'bom.items']);
    }

    /**
     * تأكيد التكاليف وإصدار عرض السعر — cost_calc → quote → operations.
     * المسار العسكري: بدون عرض سعر، اعتماد صامت تلقائي → المخزن.
     */
    /**
     * @param  array{type: string, value: float, reason: string}|null  $cashDiscount  خصم يدوي — للمدني الكاش فقط.
     */
    public function confirmAndIssueQuote(CaseRecord $case, ?string $confirmedBy = null, ?array $cashDiscount = null): CaseRecord
    {
        if ($case->stage_key !== CaseRecord::STAGE_COST_CALC) {
            abort(422, 'الحالة ليست في مرحلة التكاليف — لا يمكن التأكيد.');
        }

        DB::transaction(function () use ($case, $confirmedBy, $cashDiscount) {
            $case = CaseRecord::lockForUpdate()->findOrFail($case->id);

            $this->editRequestService->assertNoPendingForCase($case);

            $pricingRequest = $case->pricingRequest
                ?? $this->pricingService->createAndCalculateForCase($case);

            if ($case->bom && $pricingRequest) {
                $this->pricingService->syncItemsFromBom($case, $pricingRequest);
                $this->pricingService->refreshLinePrices($pricingRequest);
                $pricingRequest->refresh();
            }

            // إعادة تجميد اللقطة على المواد المحدّثة ثم اعتماد سعر البيع كقيمة العرض.
            $pricingRequest = $this->snapshotService->refresh($pricingRequest);
            $quoteAmount = (float) $pricingRequest->selling_price > 0
                ? (float) $pricingRequest->selling_price
                : (float) $pricingRequest->computed_total;

            $case = $case->fresh()->load('patient');

            if (! $case->isMilitary() && $quoteAmount <= 0) {
                abort(
                    422,
                    'لا يمكن إصدار عرض السعر — إجمالي التكلفة صفر. راجع أسعار الأصناف في الكتالوج أو دفعات الشراء في المخزن ثم أعد المحاولة من الاعتماد.'
                );
            }

            if ($case->needsServicesApproval()) {
                $this->workflowService->advance($case, WorkflowEvent::ServicesApprovalRequired->value);
                app(ServicesApprovalService::class)->openForCase($case);

                AuditService::log(
                    action: 'confirm',
                    description: "تأكيد التكاليف — بانتظار تصديق إدارة الخدمات — {$case->case_no}",
                    tag: 'pricing',
                    after: [
                        'case_id' => $case->id,
                        'confirmed_by' => $confirmedBy ?? 'مكتب التكاليف',
                        'stage_key' => CaseRecord::STAGE_SERVICES_APPROVAL,
                    ],
                );

                return;
            }

            $discount = $this->resolveCashDiscount($case, $quoteAmount, $cashDiscount);

            $this->workflowService->advance($case, WorkflowEvent::CostingCompleted->value);

            if (! $case->isMilitary()) {
                // العرض يُصدر بالصافي — الخزنة والفاتورة والتقارير تقرأ quotes.total / cases.quote_total.
                $quote = $this->quoteService->issue($pricingRequest, $quoteAmount - ($discount['amount'] ?? 0.0));
                $quote->update([
                    'gross_total' => $discount ? $quoteAmount : null,
                    'discount_amount' => $discount['amount'] ?? 0,
                    'discount_percent' => $discount['percent'] ?? null,
                    'discount_reason' => $discount['reason'] ?? null,
                    'discount_by' => $discount ? ($confirmedBy ?? 'الاعتماد') : null,
                ]);

                if ($discount) {
                    AuditService::log(
                        action: 'discount',
                        description: sprintf(
                            'خصم للعميل الكاش %s ج.م على عرض %s — %s — السبب: %s',
                            number_format($discount['amount'], 2),
                            $quote->quote_no,
                            $case->case_no,
                            $discount['reason'],
                        ),
                        tag: 'pricing',
                        after: [
                            'case_id' => $case->id,
                            'quote_id' => $quote->id,
                            'gross_total' => $quoteAmount,
                            'discount_amount' => $discount['amount'],
                            'discount_percent' => $discount['percent'],
                            'net_total' => $quoteAmount - $discount['amount'],
                            'reason' => $discount['reason'],
                            'by' => $confirmedBy,
                        ],
                    );
                }
            }

            $this->workflowService->advance($case->fresh(), WorkflowEvent::QuoteIssued->value);

            AuditService::log(
                action: 'confirm',
                description: "تأكيد التكاليف وإصدار عرض السعر — {$case->case_no}",
                tag: 'pricing',
                after: [
                    'case_id' => $case->id,
                    'confirmed_by' => $confirmedBy ?? 'مكتب التشغيل',
                    'stage_key' => CaseRecord::STAGE_OPERATIONS,
                ],
            );
        });

        $fresh = $case->fresh()->load(['patient', 'pricingRequest.items', 'quotes']);

        if ($fresh->isCashCivilian() && $fresh->stage_key === CaseRecord::STAGE_OPERATIONS) {
            $quote = $fresh->quotes->sortByDesc('id')->first();
            if ($quote) {
                $fresh = $this->operationsService->sendToCashier($fresh, $quote)
                    ->load(['patient', 'pricingRequest.items', 'quotes']);
            }
        }

        if ($fresh->isMilitary() && $fresh->stage_key === CaseRecord::STAGE_OPERATIONS) {
            $fresh = $this->operationsService->approve($fresh, 'النظام — اعتماد تلقائي عسكري');
        }

        return $fresh->load(['patient', 'pricingRequest.items', 'quotes']);
    }

    /**
     * يحسب الخصم اليدوي ويتحقق منه — متاح للمريض المدني الكاش فقط (لا جهات ولا عسكري).
     *
     * @param  array{type?: string, value?: float|int|string, reason?: string|null}|null  $input
     * @return array{amount: float, percent: float|null, reason: string}|null
     */
    private function resolveCashDiscount(CaseRecord $case, float $gross, ?array $input): ?array
    {
        $value = round((float) ($input['value'] ?? 0), 2);
        if ($input === null || $value <= 0) {
            return null;
        }

        if (! $case->isCashCivilian()) {
            abort(422, 'الخصم اليدوي متاح للعميل المدني الكاش فقط.');
        }

        $reason = trim((string) ($input['reason'] ?? ''));
        if ($reason === '') {
            abort(422, 'اكتب سبب الخصم.');
        }

        $isPercent = ($input['type'] ?? 'amount') === 'percent';
        if ($isPercent && $value >= 100) {
            abort(422, 'نسبة الخصم لازم تكون أقل من 100%.');
        }

        $amount = $isPercent ? round($gross * $value / 100, 2) : $value;
        if ($amount >= $gross) {
            abort(422, 'الخصم لا يمكن أن يساوي أو يتجاوز إجمالي السعر ('.number_format($gross, 2).' ج.م).');
        }

        return [
            'amount' => $amount,
            'percent' => $isPercent ? $value : null,
            'reason' => mb_substr($reason, 0, 255),
        ];
    }
}
