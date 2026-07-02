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
     * إنشاء أو تحديث Quote صادر من طلب تسعير محتسَب.
     * عند إعادة الحالة من مكتب التشغيل يُحدَّث العرض الحالي بدل إنشاء سجل مكرر.
     */
    public function issue(PricingRequest $request, float $total): Quote
    {
        $request->load('items', 'caseRecord.patient');

        $total = round($total, 2);

        $existing = Quote::where('pricing_request_id', $request->id)->first();

        if ($existing) {
            return $this->refreshIssued($existing, $request, $total);
        }

        $quoteNo = $this->nextQuoteNo();

        $quote = Quote::create([
            'quote_no'           => $quoteNo,
            'order_ref'          => $request->order_ref,
            'case_id'            => $request->case_id,
            'pricing_request_id' => $request->id,
            'patient_name'       => $request->patient_name,
            'company_name'       => $request->company_name,
            'quote_date'         => now()->toDateString(),
            'status'             => Quote::STATUS_PENDING,
            'status_label'       => 'بمكتب التشغيل — بانتظار الإصدار',
            'total'              => $total,
        ]);

        $this->syncQuoteItems($quote, $request);
        $this->syncCaseQuoteFields($request->case_id, $quoteNo, $total);

        AuditService::log(
            action:      'create',
            description: "إنشاء عرض السعر {$quoteNo} — {$request->request_no}",
            tag:         'quotes',
            after:       $quote->only(['id', 'quote_no', 'case_id', 'total', 'status']),
        );

        return $quote->load('items');
    }

    private function refreshIssued(Quote $quote, PricingRequest $request, float $total): Quote
    {
        $before = $quote->only(['total', 'status', 'status_label']);

        $quote->items()->delete();
        $this->syncQuoteItems($quote, $request);

        $quote->update([
            'patient_name' => $request->patient_name,
            'company_name' => $request->company_name,
            'quote_date'   => now()->toDateString(),
            'status'       => Quote::STATUS_PENDING,
            'status_label' => 'بمكتب التشغيل — بانتظار الإصدار',
            'total'        => $total,
        ]);

        $this->syncCaseQuoteFields($request->case_id, $quote->quote_no, $total);

        AuditService::log(
            action:      'reissue',
            description: "تحديث عرض السعر {$quote->quote_no} بعد إعادة المسار — {$request->request_no}",
            tag:         'quotes',
            before:      $before,
            after:       $quote->only(['id', 'quote_no', 'case_id', 'total', 'status', 'status_label']),
        );

        return $quote->load('items');
    }

    private function syncQuoteItems(Quote $quote, PricingRequest $request): void
    {
        foreach ($request->items as $item) {
            $unitPrice = (float) ($item->unit_price
                ?? $this->stockPriceService->highestUnitPrice($item->stock_item_code ?? ''));

            $lineAmount = (float) ($item->line_total ?? round($item->qty * $unitPrice, 2));

            QuoteItem::create([
                'quote_id'        => $quote->id,
                'name'            => $item->name,
                'source'          => $item->source ?? \App\Models\BomItem::SOURCE_SPEC,
                'stock_item_code' => $item->stock_item_code,
                'qty'             => $item->qty,
                'amount'          => $lineAmount,
            ]);
        }
    }

    private function syncCaseQuoteFields(int $caseId, string $quoteNo, float $total): void
    {
        CaseRecord::where('id', $caseId)->update([
            'quote_no'    => $quoteNo,
            'quote_date'  => now()->toDateString(),
            'quote_total' => $total,
        ]);
    }

    /**
     * إصدار العرض من مكتب التشغيل إلى الاستقبال — يظهر في قسم عروض الأسعار.
     */
    public function releaseToReception(Quote $quote): Quote
    {
        if ($quote->status !== Quote::STATUS_PENDING) {
            abort(422, 'العرض مُصدَر للاستقبال مسبقاً أو غير قابل للإصدار.');
        }

        return $this->markIssued($quote, 'إصدار من مكتب التشغيل');
    }

    /**
     * إصدار العرض من مكتب التشغيل إلى الخزنة — مسار الكاش (المريض على نفقته الشخصية).
     * لا خطاب موافقة جهة؛ العرض بانتظار تحصيل الدفع فقط.
     */
    public function releaseToCashier(Quote $quote): Quote
    {
        if ($quote->status !== Quote::STATUS_PENDING) {
            abort(422, 'العرض مُصدَر مسبقاً أو غير قابل للإصدار.');
        }

        $before = $quote->only(['status', 'status_label']);

        return DB::transaction(function () use ($quote, $before) {
            $quote->update([
                'status'       => Quote::STATUS_ISSUED,
                'status_label' => 'بانتظار الدفع في الخزنة',
            ]);

            AuditService::log(
                action:      'issue',
                description: "إصدار عرض سعر نقدي للخزنة — {$quote->quote_no}",
                tag:         'quotes',
                before:      $before,
                after:       $quote->only(['status', 'status_label']),
            );

            return $quote->fresh()->load('items');
        });
    }

    /**
     * تأكيد إصدار العرض للجهة (طباعة/QR) — بعد موافقة مكتب التشغيل.
     */
    public function markIssued(Quote $quote, ?string $auditNote = null): Quote
    {
        if (! in_array($quote->status, [Quote::STATUS_PENDING, Quote::STATUS_ISSUED], true)) {
            abort(422, 'لا يمكن إصدار عرض السعر — الحالة الحالية: ' . $quote->status);
        }

        $before = $quote->only(['status', 'status_label']);

        return DB::transaction(function () use ($quote, $before, $auditNote) {
            $quote->update([
                'status'       => Quote::STATUS_ISSUED,
                'status_label' => 'صادر للجهة — بانتظار خطاب الموافقة',
            ]);

            AuditService::log(
                action:      'issue',
                description: $auditNote
                    ? "{$auditNote} — {$quote->quote_no}"
                    : "إصدار عرض السعر {$quote->quote_no}",
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

        do {
            $quoteNo = sprintf('%s%04d', $prefix, $num++);
        } while (Quote::where('quote_no', $quoteNo)->exists());

        return $quoteNo;
    }
}
