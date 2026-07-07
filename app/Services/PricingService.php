<?php

namespace App\Services;

use App\Enums\PricingRequestStatus;
use App\Models\Bom;
use App\Models\BomItem;
use App\Models\CaseRecord;
use App\Models\MedicalRecord;
use App\Models\PricingRequest;
use App\Models\PricingRequestItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * محرك التكاليف (الخطوة 5 — التكاليف).
 *
 * يحتسب رقمين لكل حالة من BOM النهائي (بنود الفني + بنود مستشار المعدلات):
 *   - computed_total : مجموع المواد (أعلى سعر شراء) — توصيف + معدلات — قبل خصم الجهة.
 *   - internal_total : متوسط التكلفة المرجح (WAC) — التكلفة الحقيقية لحساب الربح،
 *                      تظهر للأدمن فقط.
 *
 * الاحتساب تلقائي وللعرض فقط (read-only) — لا اعتماد يدوي هنا؛ القرار في مكتب التشغيل.
 */
class PricingService
{
    public function __construct(
        private readonly StockPriceService $stockPriceService,
    ) {}

    /**
     * بناء طلب تسعير من BOM النهائي للحالة ثم احتساب التكلفة فوراً.
     * يُستدعى من AdjustmentsService عند إغلاق مرحلة المعدلات.
     */
    public function createAndCalculateForCase(CaseRecord $case): PricingRequest
    {
        return DB::transaction(function () use ($case) {
            $case = CaseRecord::with('patient:id,name')->lockForUpdate()->findOrFail($case->id);

            if ($case->pricing_request_id) {
                $existing = PricingRequest::with('items')->find($case->pricing_request_id);
                if ($existing) {
                    $this->syncItemsFromBom($case, $existing);
                    $this->calculate($existing);

                    return $existing->fresh()->load('items');
                }
            }

            $bom = Bom::with('items')->where('case_id', $case->id)->first();

            if (! $bom || $bom->items->isEmpty()) {
                abort(422, 'لا توجد قائمة مواد (BOM) لاحتساب تكلفتها.');
            }

            $doctor = MedicalRecord::where('case_id', $case->id)
                ->where('locked', true)
                ->latest()
                ->first();

            $pricingRequest = PricingRequest::create([
                'request_no' => $this->nextRequestNo(),
                'order_ref' => $case->order_ref,
                'case_id' => $case->id,
                'patient_name' => $case->patient?->name ?? '—',
                'company_name' => $case->company_name,
                'request_date' => now()->toDateString(),
                'items_count' => $bom->items->count(),
                'doctor_name' => $doctor?->doctor_name,
                'doctor_user_id' => $doctor?->doctor_user_id,
                'patient_type' => $case->patient_type,
                'status_key' => PricingRequestStatus::Processing->value,
                'step' => PricingRequest::STEP_ADMIN,
            ]);

            foreach ($bom->items as $item) {
                PricingRequestItem::create([
                    'pricing_request_id' => $pricingRequest->id,
                    'stock_item_code' => $item->stock_item_code,
                    'name' => $item->name,
                    'source' => $item->source ?? BomItem::SOURCE_SPEC,
                    'qty' => $item->qty,
                ]);
            }

            $pricingRequest->load('items');
            $this->calculate($pricingRequest);

            $case->update(['pricing_request_id' => $pricingRequest->id]);

            return $pricingRequest->fresh()->load('items');
        });
    }

    /**
     * مزامنة بنود طلب التسعير مع BOM النهائي (فني + معدلات).
     */
    public function syncItemsFromBom(CaseRecord $case, PricingRequest $request): void
    {
        $bom = Bom::with('items')->where('case_id', $case->id)->first();

        if (! $bom || $bom->items->isEmpty()) {
            abort(422, 'لا توجد قائمة مواد (BOM) لاحتساب تكلفتها.');
        }

        $request->items()->delete();

        foreach ($bom->items as $item) {
            PricingRequestItem::create([
                'pricing_request_id' => $request->id,
                'stock_item_code' => $item->stock_item_code,
                'name' => $item->name,
                'source' => $item->source ?? BomItem::SOURCE_SPEC,
                'qty' => $item->qty,
            ]);
        }

        $request->update(['items_count' => $bom->items->count()]);
    }

    /**
     * احتساب تكلفة طلب التسعير — أعلى سعر شراء (عرض السعر) + WAC (داخلي).
     * computed_total = مجموع qty × أعلى سعر (توصيف + معدلات).
     */
    public function calculate(PricingRequest $request): float
    {
        return DB::transaction(function () use ($request) {
            $request = PricingRequest::lockForUpdate()->findOrFail($request->id);

            $request->update(['status_key' => PricingRequestStatus::Processing->value]);

            $request->load('items');
            $total = 0.0;
            $internal = 0.0;
            $lines = [];

            foreach ($request->items as $item) {
                $code = $item->stock_item_code ?? '';
                $unitPrice = $this->stockPriceService->highestUnitPrice($code);
                $wacUnit = $this->stockPriceService->wacUnitPrice($code);

                if ($unitPrice <= 0) {
                    Log::warning('Pricing: no valid price batch for item', [
                        'pricing_request_id' => $request->id,
                        'stock_item_code' => $code,
                    ]);
                }

                $lineTotal = round($item->qty * $unitPrice, 2);

                $item->update([
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ]);

                $lines[] = [
                    'stock_item_code' => $code,
                    'qty' => $item->qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'wac_unit' => $wacUnit,
                ];

                $total += $lineTotal;
                $internal += round($item->qty * $wacUnit, 2);
            }

            $total = round($total, 2);
            $internal = round($internal, 2);

            $before = $request->only(['status_key', 'computed_total', 'internal_total']);

            $request->update([
                'computed_total' => $total,
                'internal_total' => $internal,
                'status_key' => PricingRequestStatus::AwaitingAdminApproval->value,
            ]);

            AuditService::log(
                action: 'calculate',
                description: "احتساب تكلفة الحالة — {$request->request_no}",
                tag: 'pricing',
                before: $before,
                after: [
                    'request_no' => $request->request_no,
                    'status_key' => PricingRequestStatus::AwaitingAdminApproval->value,
                    'computed_total' => $total,
                    'internal_total' => $internal,
                    'lines' => $lines,
                ],
            );

            return $total;
        });
    }

    /**
     * إعادة احتساب الأسطر من أعلى سعر شراء + WAC — بدون تغيير status_key.
     */
    public function refreshLinePrices(PricingRequest $request): float
    {
        return DB::transaction(function () use ($request) {
            $request = PricingRequest::lockForUpdate()->findOrFail($request->id);
            $request->load('items');

            $total = 0.0;
            $internal = 0.0;

            foreach ($request->items as $item) {
                $code = $item->stock_item_code ?? '';
                $unitPrice = $this->stockPriceService->highestUnitPrice($code);
                $wacUnit = $this->stockPriceService->wacUnitPrice($code);

                if ($unitPrice <= 0) {
                    Log::warning('Pricing refresh: no valid price batch for item', [
                        'pricing_request_id' => $request->id,
                        'stock_item_code' => $code,
                    ]);
                }

                $lineTotal = round($item->qty * $unitPrice, 2);

                $item->update([
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ]);

                $total += $lineTotal;
                $internal += round($item->qty * $wacUnit, 2);
            }

            $total = round($total, 2);
            $internal = round($internal, 2);

            $request->update([
                'computed_total' => $total,
                'internal_total' => $internal,
            ]);

            return $total;
        });
    }

    public function nextRequestNo(): string
    {
        do {
            $requestNo = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
        } while (PricingRequest::where('request_no', $requestNo)->lockForUpdate()->exists());

        return $requestNo;
    }
}
