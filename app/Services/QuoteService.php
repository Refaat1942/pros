<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Models\PricingRequest;
use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Support\Facades\DB;

/**
 * إصدار عرض السعر (الخطوة 6) — مبني على BOM المجمّع النهائي وبأعلى سعر شراء.
 * العرض يُصدر مباشرةً ويهبط في مكتب التشغيل (الخطوة 7) لاتخاذ القرار.
 */
class QuoteService
{
    public function __construct(
        private readonly StockPriceService $stockPriceService,
    ) {
    }

    /**
     * إنشاء Quote صادر من طلب تسعير محتسَب — يُستدعى داخل transaction المعدلات.
     */
    public function issue(PricingRequest $request, float $total): Quote
    {
        $request->load('items', 'caseRecord');

        $quoteNo = $this->nextQuoteNo();

        $quote = Quote::create([
            'quote_no'           => $quoteNo,
            'order_ref'          => $request->order_ref,
            'case_id'            => $request->case_id,
            'pricing_request_id' => $request->id,
            'patient_name'       => $request->patient_name,
            'company_name'       => $request->company_name,
            'quote_date'         => now()->toDateString(),
            'status'             => Quote::STATUS_ISSUED,
            'status_label'       => 'صادر — بمكتب التشغيل',
            'total'              => $total,
        ]);

        foreach ($request->items as $item) {
            $unitPrice = (float) ($item->unit_price
                ?? $this->stockPriceService->highestUnitPrice($item->stock_item_code ?? ''));

            $lineAmount = (float) ($item->line_total ?? round($item->qty * $unitPrice, 2));

            QuoteItem::create([
                'quote_id'        => $quote->id,
                'name'            => $item->name,
                'stock_item_code' => $item->stock_item_code,
                'qty'             => $item->qty,
                'amount'          => $lineAmount,
            ]);
        }

        CaseRecord::where('id', $request->case_id)->update([
            'quote_no'    => $quoteNo,
            'quote_date'  => now()->toDateString(),
            'quote_total' => $total,
        ]);

        AuditService::log(
            action:      'create',
            description: "إنشاء عرض السعر {$quoteNo} — {$request->request_no}",
            tag:         'quotes',
            after:       $quote->only(['id', 'quote_no', 'case_id', 'total', 'status']),
        );

        return $quote->load('items');
    }

    /**
     * إعادة تأكيد إصدار العرض للجهة (طباعة/QR) — العرض صادر أصلاً من محرك التكاليف.
     */
    public function markIssued(Quote $quote): Quote
    {
        if (! in_array($quote->status, [Quote::STATUS_PENDING, Quote::STATUS_ISSUED], true)) {
            abort(422, 'لا يمكن إصدار عرض السعر — الحالة الحالية: ' . $quote->status);
        }

        $before = $quote->only(['status', 'status_label']);

        return DB::transaction(function () use ($quote, $before) {
            $quote->update([
                'status'       => Quote::STATUS_ISSUED,
                'status_label' => 'صادر للجهة',
            ]);

            AuditService::log(
                action:      'issue',
                description: "إصدار عرض السعر {$quote->quote_no}",
                tag:         'quotes',
                before:      $before,
                after:       $quote->only(['status', 'status_label']),
            );

            return $quote->fresh()->load('items');
        });
    }

    private function nextQuoteNo(): string
    {
        $year   = now()->year;
        $prefix = "QT-{$year}-";

        $last = Quote::where('quote_no', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderByDesc('quote_no')
            ->value('quote_no');

        $num = $last
            ? ((int) substr($last, strlen($prefix)) + 1)
            : 1;

        return sprintf('%s%04d', $prefix, $num);
    }
}
