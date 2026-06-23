<?php

namespace App\Services;

use App\Models\StockItem;
use App\Models\StockItemPrice;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * خدمة دفعات أسعار الشراء وإعادة حساب WAC
 *
 * - addBatch()         → يسجّل دفعة سعر شراء ويُعيد حساب WAC
 * - highestUnitPrice() → أعلى سعر وحدة (للتسعير — ليس للـ WAC)
 * - recalcWac()        → يُعيد حساب WAC ويُخزّنه في stock_items
 */
class StockPriceService
{
    /**
     * تسجيل دفعة سعر شراء — بدون إعادة حساب WAC أو تدقيق (يُستدعى من StockReceiveService).
     */
    public function createPriceBatch(
        StockItem $item,
        int       $qty,
        float     $unitPrice,
        Supplier  $supplier,
        string    $invoiceNo,
        Carbon    $receivedAt,
    ): StockItemPrice {
        $seq      = $item->prices()->count() + 1;
        $priceRef = sprintf('PR-%s-%d', $item->code, $seq);

        return StockItemPrice::create([
            'stock_item_id' => $item->id,
            'price_ref'     => $priceRef,
            'supplier_id'   => $supplier->id,
            'amount'        => $unitPrice,
            'qty'           => $qty,
            'invoice_no'    => $invoiceNo,
            'received_at'   => $receivedAt->toDateString(),
        ]);
    }

    /**
     * إضافة دفعة سعر شراء جديدة وإعادة حساب WAC.
     *
     * لا يُنشئ StockMovement — الاستلام الفيزيائي في StockReceiveService (Task 08).
     */
    public function addBatch(
        StockItem $item,
        int       $qty,
        float     $unitPrice,
        Supplier  $supplier,
        string    $invoiceNo,
        Carbon    $receivedAt,
    ): StockItemPrice {
        return DB::transaction(function () use ($item, $qty, $unitPrice, $supplier, $invoiceNo, $receivedAt) {
            $batch = $this->createPriceBatch(
                $item, $qty, $unitPrice, $supplier, $invoiceNo, $receivedAt
            );

            $this->recalcWac($item, $qty, $unitPrice);

            AuditService::log(
                action:      'create',
                description: "إضافة دفعة سعر {$batch->price_ref} للصنف {$item->code} — سعر {$unitPrice} × {$qty}",
                tag:         'warehouse',
                after:       ['price_ref' => $batch->price_ref, 'amount' => $unitPrice, 'qty' => $qty],
            );

            return $batch;
        });
    }

    /**
     * أعلى سعر وحدة مسجَّل للصنف (دفعات الكمية > 0 فقط).
     * يُستخدم من PricingService لبناء عرض الأسعار — لا علاقة له بالـ WAC.
     */
    public function highestUnitPrice(string $stockItemCode): float
    {
        $item = StockItem::where('code', $stockItemCode)->first();

        if (! $item) {
            return 0.0;
        }

        $maxBatch = (float) ($item->prices()->where('qty', '>', 0)->max('amount') ?? 0.0);

        // أعلى سعر = الأعلى بين السعر الأساسي والأسعار الإضافية (صنف بأكثر من سعر).
        return max((float) $item->price, $maxBatch);
    }

    /**
     * متوسط التكلفة المرجح (WAC) للصنف — للتكلفة الداخلية وحساب الربح الحقيقي.
     * يُستخدم من محرك التكاليف (للأدمن فقط) وليس لبناء عرض السعر.
     */
    public function wacUnitPrice(string $stockItemCode): float
    {
        $wac = (float) (StockItem::where('code', $stockItemCode)->value('wac') ?? 0.0);

        // إن لم يُحتسب WAC بعد، نرجع لأعلى سعر شراء كأساس تكلفة آمن.
        return $wac > 0 ? $wac : $this->highestUnitPrice($stockItemCode);
    }

    /**
     * إعادة حساب WAC وتخزينه في stock_items.
     *
     * Formula: (prior_qty × prior_wac + in_qty × in_price) / (prior_qty + in_qty)
     *
     * يُستدعى من:
     *  - addBatch()             (هنا — عند تسجيل دفعة سعر)
     *  - StockReceiveService    (Task 08 — عند الاستلام الفيزيائي)
     */
    public function recalcWac(StockItem $item, int $inQty, float $inPrice): void
    {
        $priorQty = (int) $item->qty;
        $priorWac = (float) ($item->wac ?? 0);

        $denominator = $priorQty + $inQty;

        if ($denominator <= 0) {
            return;
        }

        $newWac = (($priorQty * $priorWac) + ($inQty * $inPrice)) / $denominator;

        StockItem::where('id', $item->id)
            ->update(['wac' => round($newWac, 4)]);
    }
}
