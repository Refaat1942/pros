<?php

namespace App\Services;

use App\Enums\PricingRequestStatus;
use App\Enums\WorkflowEvent;
use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\PricingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * محرك التسعير — أعلى سعر شراء (Highest Purchase Price) وليس WAC.
 *
 * دورة الحياة الكاملة:
 *   PricingRequest يُنشأ بحالة processing
 *   → calculate() يحتسب المجموع ثم يُحوِّل إلى awaiting_admin_approval
 *   → approve()   يُرسل للاستقبال ويُحوِّل إلى sent_to_reception
 */
class PricingService
{
    public function __construct(
        private readonly StockPriceService $stockPriceService,
        private readonly WorkflowService $workflowService,
        private readonly QuoteService $quoteService,
        private readonly WorkOrderService $workOrderService,
    ) {
    }

    /**
     * احتساب تكلفة طلب التسعير.
     *
     * يبدأ بـ processing ثم يُحوِّل إلى awaiting_admin_approval عند الانتهاء.
     */
    public function calculate(PricingRequest $request): float
    {
        return DB::transaction(function () use ($request) {
            $request = PricingRequest::lockForUpdate()->findOrFail($request->id);

            // Mark as "engine is running"
            $request->update(['status_key' => PricingRequestStatus::Processing->value]);

            $request->load('items');
            $total = 0.0;
            $lines = [];

            foreach ($request->items as $item) {
                $unitPrice = $this->stockPriceService->highestUnitPrice($item->stock_item_code ?? '');

                if ($unitPrice <= 0) {
                    Log::warning('Pricing: no valid price batch for item', [
                        'pricing_request_id' => $request->id,
                        'stock_item_code'    => $item->stock_item_code,
                    ]);
                }

                $lineTotal = round($item->qty * $unitPrice, 2);

                $item->update([
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ]);

                $lines[] = [
                    'stock_item_code' => $item->stock_item_code,
                    'qty'             => $item->qty,
                    'unit_price'      => $unitPrice,
                    'line_total'      => $lineTotal,
                ];

                $total += $lineTotal;
            }

            $total = round($total, 2);
            $before = ['status_key' => PricingRequestStatus::Processing->value, 'computed_total' => 0];

            $request->update([
                'computed_total' => $total,
                'status_key'     => PricingRequestStatus::AwaitingAdminApproval->value,
            ]);

            AuditService::log(
                action:      'calculate',
                description: "احتساب تكلفة الحالة — {$request->request_no}",
                tag:         'pricing',
                before:      $before,
                after:       [
                    'request_no'     => $request->request_no,
                    'status_key'     => PricingRequestStatus::AwaitingAdminApproval->value,
                    'computed_total' => $total,
                    'lines'          => $lines,
                ],
            );

            return $total;
        });
    }

    /**
     * إعادة احتساب أسطر الطلب من أعلى سعر شراء — بدون تغيير status_key.
     * يُستخدم عند عرض طلب قديم أو بعد تصحيح دفعات الأسعار.
     */
    public function refreshLinePrices(PricingRequest $request): float
    {
        return DB::transaction(function () use ($request) {
            $request = PricingRequest::lockForUpdate()->findOrFail($request->id);
            $request->load('items');

            $total = 0.0;

            foreach ($request->items as $item) {
                $unitPrice = $this->stockPriceService->highestUnitPrice($item->stock_item_code ?? '');

                if ($unitPrice <= 0) {
                    Log::warning('Pricing refresh: no valid price batch for item', [
                        'pricing_request_id' => $request->id,
                        'stock_item_code'    => $item->stock_item_code,
                    ]);
                }

                $lineTotal = round($item->qty * $unitPrice, 2);

                $item->update([
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ]);

                $total += $lineTotal;
            }

            $total = round($total, 2);

            $request->update(['computed_total' => $total]);

            return $total;
        });
    }

    /**
     * اعتماد طلب التسعير من الأدmin.
     *
     * الشرط: status_key === awaiting_admin_approval
     * النتيجة: status_key → sent_to_reception + workflow advance
     */
    public function approve(PricingRequest $request, User $approver): void
    {
        $status = $this->resolveStatus($request);

        if (! $status->isApprovable()) {
            abort(422, 'طلب التسعير ليس في مرحلة الاعتماد — الحالة الحالية: ' . $status->label());
        }

        DB::transaction(function () use ($request, $approver) {
            $request = PricingRequest::lockForUpdate()->findOrFail($request->id);

            $lockedStatus = $this->resolveStatus($request);

            if (! $lockedStatus->isApprovable()) {
                abort(422, 'طلب التسعير ليس في مرحلة الاعتماد.');
            }

            $before = $request->only(['status_key', 'step', 'approved_at']);

            $request->update([
                'approved_at'         => now(),
                'approved_by'         => $approver->name,
                'approved_by_user_id' => $approver->id,
                'step'                => PricingRequest::STEP_QUOTE_READY,
                'status_key'          => PricingRequestStatus::SentToReception->value,
            ]);

            $case  = CaseRecord::lockForUpdate()->findOrFail($request->case_id);
            $total = (float) $request->computed_total;

            if ($request->patient_type === Patient::TYPE_MILITARY) {
                $case->update(['total_cost' => $total]);
                $this->workflowService->advance($case, WorkflowEvent::PricingCompletedMilitary->value);
                $this->workOrderService->generate($case->fresh());
            } else {
                $this->quoteService->issue($request, $total);
                $this->workflowService->advance($case->fresh(), WorkflowEvent::PricingCompletedCivilian->value);
            }

            AuditService::log(
                action:      'approve',
                description: "اعتماد طلب التسعير — {$request->request_no}",
                tag:         'pricing',
                before:      $before,
                after:       $request->fresh()->only(['status_key', 'step', 'approved_at', 'approved_by']),
            );
        });
    }

    /**
     * اعتماد تلقائي صامت للمسار العسكري — بدون تدخل أدمن.
     *
     * يُستدعى مباشرةً من SpecService::submit() فور إرسال التوصيف الفني
     * للحالة العسكرية، مما يُلغي انتظار طابور الاعتماد الإداري بالكامل.
     */
    public function silentAutoApprove(PricingRequest $request): void
    {
        DB::transaction(function () use ($request) {
            $request = PricingRequest::lockForUpdate()->findOrFail($request->id);

            // Guard: يُطبَّق على العسكريين فقط
            if ($request->patient_type !== Patient::TYPE_MILITARY) {
                return;
            }

            $before = $request->only(['status_key', 'step']);

            $request->update([
                'approved_at'  => now(),
                'approved_by'  => 'النظام — اعتماد تلقائي عسكري',
                'step'         => PricingRequest::STEP_QUOTE_READY,
                'status_key'   => PricingRequestStatus::SentToReception->value,
            ]);

            $case  = CaseRecord::lockForUpdate()->findOrFail($request->case_id);
            $total = (float) $request->computed_total;

            $case->update(['total_cost' => $total]);
            $this->workflowService->advance($case, WorkflowEvent::PricingCompletedMilitary->value);
            $this->workOrderService->generate($case->fresh());

            AuditService::log(
                action:      'auto_approve',
                description: "اعتماد تلقائي (مسار عسكري) — {$request->request_no} — تجاوز طابور الإدارة",
                tag:         'pricing',
                before:      $before,
                after:       [
                    'request_no'     => $request->request_no,
                    'status_key'     => PricingRequestStatus::SentToReception->value,
                    'computed_total' => $total,
                    'auto'           => true,
                    'patient_type'   => 'military',
                ],
            );
        });
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function resolveStatus(PricingRequest $request): PricingRequestStatus
    {
        return $request->status_key instanceof PricingRequestStatus
            ? $request->status_key
            : PricingRequestStatus::from((string) $request->status_key);
    }
}
