<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\StockItem;
use App\Models\StockItemPrice;
use Illuminate\Support\Facades\DB;

/**
 * مسح إداري متعمّد لكل بيانات المرضى التشغيلية — مع الإبقاء على الكور (مستخدمون،
 * مخزن، إعدادات، جهات). مسار إداري مقصود، وليس مسار حذف تطبيقي عادي.
 *
 * ملاحظات السلامة (Correction C):
 *   1) يتجاوز عمداً حارس PatientDeletionGuard (المخصّص لمسارات التطبيق العادية).
 *   2) يحذف الأبناء بترتيب آمن لقيود المفاتيح الأجنبية: تُحذف الجداول المالية
 *      (payments, military_debts, credit_notes) قبل «cases»، لذا لا تُنتهَك قيود
 *      RESTRICT على PostgreSQL (VPS + الشبكة المحلية الأوفلاين). تم التحقق فعلياً
 *      على PostgreSQL في اختبار PurgeUnderRestrictPgTest.
 *   3) لا يحذف سجل الرقابة (audit_logs) أبداً — دليل قانوني «إضافة فقط»؛ قاعدة
 *      البيانات نفسها تمنع UPDATE/DELETE عبر مشغّلات.
 *   4) يحفظ أثر الرقابة كاملاً.
 *   5) يكتب حدث «purge» في سجل الرقابة بعد نجاح العملية.
 *   6) يعمل داخل معاملة واحدة — أي فشل يُرجِع كل الحذف بأمان (ذرّي).
 *
 * ⚠️ ترتيب الحذف أدناه حسّاس لقيود RESTRICT — لا تُعِد ترتيب حذف
 *    payments/military_debts/credit_notes إلى ما بعد حذف «cases».
 */
class PatientDataPurgeService
{
    /**
     * عدّادات الترقيم (حالة، عرض سعر، دور…) تبدأ من جديد — كل عدّاد يُعاد بناؤه من أعلى رقم باقٍ.
     * عدّادات المخزن (كود الصنف، طلبات التوريد) تبقى لأن الأصناف لا تُمسح هنا.
     */
    public function resetNumbering(): int
    {
        return DB::table('document_sequences')->where('key', '<>', 'ITM')->where('key', 'not like', 'SR-%')->delete();
    }

    /** @return array<string, int> */
    public function purge(bool $resetContractDebts = true, bool $syncStock = true): array
    {
        $counts = [];

        DB::transaction(function () use ($resetContractDebts, $syncStock, &$counts) {
            DB::table('cases')->update(['pricing_request_id' => null]);

            $counts['payments'] = DB::table('payments')->delete();
            $counts['quote_items'] = DB::table('quote_items')->delete();
            $counts['quotes'] = DB::table('quotes')->delete();
            $counts['pricing_request_items'] = DB::table('pricing_request_items')->delete();
            $counts['pricing_requests'] = DB::table('pricing_requests')->delete();
            $counts['approval_contracts'] = DB::table('approval_contracts')->delete();
            $counts['military_debts'] = DB::table('military_debts')->delete();
            $counts['spec_edit_requests'] = DB::table('spec_edit_requests')->delete();

            $caseMovements = fn () => DB::table('stock_movements')->whereIn('reference_type', ['bom', 'return_note']);

            // صافي حركات الحالات لكل صنف ولكل دفعة سعر — يُعكَس بعد الحذف ليعود الرصيد كما كان قبل الصرف.
            $netByItem = $caseMovements()
                ->selectRaw('stock_item_id, SUM(quantity) as net')
                ->groupBy('stock_item_id')
                ->pluck('net', 'stock_item_id')
                ->all();
            $netByBatch = $caseMovements()
                ->whereNotNull('stock_item_price_id')
                ->selectRaw('stock_item_price_id, SUM(quantity) as net')
                ->groupBy('stock_item_price_id')
                ->pluck('net', 'stock_item_price_id')
                ->all();

            $counts['stock_movements_case'] = $caseMovements()->delete();

            $counts['return_notes'] = DB::table('return_notes')->delete();
            $counts['bom_items'] = DB::table('bom_items')->delete();
            $counts['boms'] = DB::table('boms')->delete();
            $counts['credit_notes'] = DB::table('credit_notes')->delete();
            $counts['case_recommendations'] = DB::table('case_recommendations')->delete();
            $counts['tech_order_specs'] = DB::table('tech_order_specs')->delete();
            $counts['medical_records'] = DB::table('medical_records')->delete();
            $counts['notifications'] = AppNotification::query()->delete();
            $counts['cases'] = DB::table('cases')->delete();
            $counts['appointments'] = DB::table('appointments')->delete();
            $counts['patients'] = DB::table('patients')->delete();
            $this->resetNumbering();

            // C-5: سجل الرقابة يُحفَظ دائماً (append-only) — لا يُحذف في المسح.
            $counts['audit_logs_preserved'] = (int) DB::table('audit_logs')->count();

            if ($resetContractDebts) {
                $counts['contract_debts_reset'] = DB::table('contract_company_debts')->update([
                    'due' => 0,
                    'collected' => 0,
                    'status' => 'pending',
                ]);
                $counts['debt_collection_entries'] = DB::table('debt_collection_entries')->delete();
                $counts['contract_debt_accruals'] = DB::table('contract_debt_accruals')->delete();
            }

            if ($syncStock) {
                $counts['stock_items_synced'] = $this->reverseCaseMovements($netByItem, $netByBatch);
            }
        });

        // C-5: تسجيل عملية المسح في سجل الرقابة (بعد نجاح المعاملة) — الأثر يبقى دليلاً.
        AuditService::log(
            action: 'purge',
            description: 'مسح بيانات المرضى التشغيلية — سجل الرقابة محفوظ بالكامل.',
            tag: 'admin',
            after: $counts,
        );

        AdminOverviewService::clearBiBoardsCache();

        return $counts;
    }

    public function hasPatientRelatedData(): bool
    {
        return DB::table('patients')->exists()
            || DB::table('cases')->exists()
            || DB::table('appointments')->exists();
    }

    /**
     * يعكس صافي حركات الصرف/الارتجاع المحذوفة على رصيد الصنف ودفعات الفيفو.
     * (كان يأخذ رصيد آخر حركة متبقية — فالصنف المرفوع من الشيت بلا حركات لا يسترد ما صُرف منه،
     * وكان يقطع الكسور: 2.5 متر ← 2.)
     *
     * @param  array<int|string, float|string>  $netByItem
     * @param  array<int|string, float|string>  $netByBatch
     */
    private function reverseCaseMovements(array $netByItem, array $netByBatch): int
    {
        foreach ($netByBatch as $batchId => $net) {
            $batch = StockItemPrice::query()->find($batchId);
            if ($batch !== null) {
                $batch->update(['qty' => round((float) $batch->qty - (float) $net, 4)]);
            }
        }

        $synced = 0;

        StockItem::query()->each(function (StockItem $item) use ($netByItem, &$synced) {
            $item->qty = round((float) $item->qty - (float) ($netByItem[$item->id] ?? 0), 4);
            $item->reserved = 0;
            $item->recalculateAndSaveStatus();
            $item->save();
            $synced++;
        });

        return $synced;
    }
}
