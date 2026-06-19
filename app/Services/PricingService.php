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
 */
class PricingService
{
    public function __construct(
        private readonly StockPriceService $stockPriceService,
        private readonly WorkflowService $workflowService,
        private readonly QuoteService $quoteService,
    ) {
    }

    /**
     * احتساب تكلفة طلب التسعير — يُستدعى تلقائياً عند إنشاء PricingRequest.
     */
    public function calculate(PricingRequest $request): float
    {
        return DB::transaction(function () use ($request) {
            $request->load('items');

            $total  = 0.0;
            $lines  = [];

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

            $request->update(['computed_total' => round($total, 2)]);

            AuditService::log(
                action:      'calculate',
                description: "احتساب تكلفة الحالة — {$request->request_no}",
                tag:         'pricing',
                after:       [
                    'request_no'     => $request->request_no,
                    'computed_total' => round($total, 2),
                    'lines'          => $lines,
                ],
            );

            return round($total, 2);
        });
    }

    /**
     * اعتماد طلب التسعير — مسار مدني (Quote) أو عسكري (total_cost صامت).
     */
    public function approve(PricingRequest $request, User $approver): void
    {
        $status = $request->status_key instanceof PricingRequestStatus
            ? $request->status_key
            : PricingRequestStatus::from((string) $request->status_key);

        if (! $status->isPending()) {
            abort(422, 'تم اعتماد طلب التسعير مسبقاً.');
        }

        DB::transaction(function () use ($request, $approver) {
            $request = PricingRequest::lockForUpdate()->findOrFail($request->id);

            $lockedStatus = $request->status_key instanceof PricingRequestStatus
                ? $request->status_key
                : PricingRequestStatus::from((string) $request->status_key);

            if (! $lockedStatus->isPending()) {
                abort(422, 'تم اعتماد طلب التسعير مسبقاً.');
            }

            $before = $request->only(['status_key', 'step', 'approved_at']);

            $request->update([
                'approved_at'         => now(),
                'approved_by'         => $approver->name,
                'approved_by_user_id' => $approver->id,
                'step'                => PricingRequest::STEP_QUOTE_READY,
                'status_key'          => PricingRequestStatus::Sent->value,
            ]);

            $case = CaseRecord::lockForUpdate()->findOrFail($request->case_id);
            $total = (float) $request->computed_total;

            if ($request->patient_type === Patient::TYPE_MILITARY) {
                $case->update(['total_cost' => $total]);
                $this->workflowService->advance($case, WorkflowEvent::PricingCompletedMilitary->value);
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
}
