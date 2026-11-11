<?php

namespace App\Services;

use App\Models\StockItem;
use App\Models\StockItemPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * حذف مرضى محددين (مدير النظام فقط) بكل ما يرتبط بهم — نفس ترتيب المسح الكامل الآمن لقيود RESTRICT:
 *   - المدفوعات والمديونيات العسكرية وإشعارات الدائن تُحذف قبل الحالات.
 *   - صرف المخزن الخاص بحالاتهم يُعكَس (الكمية ترجع للرصيد ولدفعات الفيفو) وحجوزاتهم تُلغى.
 *   - مستحقات جهات التعاقد الناتجة عن حالاتهم تُخصم من مديونية الجهة.
 *   - سجل الرقابة لا يُحذف أبداً، ويُسجَّل فيه الحذف.
 */
class SelectivePatientPurgeService
{
    /**
     * @param  list<int>  $patientIds
     * @return array<string, int>
     */
    public function purgePatients(array $patientIds): array
    {
        $patientIds = array_values(array_unique(array_map('intval', $patientIds)));
        if ($patientIds === []) {
            return [];
        }

        $counts = [];
        $names = DB::table('patients')->whereIn('id', $patientIds)->pluck('name')->all();

        DB::transaction(function () use ($patientIds, &$counts) {
            $caseIds = DB::table('cases')->whereIn('patient_id', $patientIds)->pluck('id')->all();
            $in = fn (string $table, string $col, array $ids) => Schema::hasTable($table) && $ids !== []
                ? DB::table($table)->whereIn($col, $ids)
                : null;
            $del = function (string $key, ?\Illuminate\Database\Query\Builder $q) use (&$counts) {
                $counts[$key] = $q ? $q->delete() : 0;
            };

            $quoteIds = $caseIds ? DB::table('quotes')->whereIn('case_id', $caseIds)->pluck('id')->all() : [];
            $pricingIds = $caseIds ? DB::table('pricing_requests')->whereIn('case_id', $caseIds)->pluck('id')->all() : [];
            $bomIds = $caseIds ? DB::table('boms')->whereIn('case_id', $caseIds)->pluck('id')->all() : [];
            $returnIds = $caseIds ? DB::table('return_notes')->whereIn('case_id', $caseIds)->pluck('id')->all() : [];
            $specIds = $caseIds ? DB::table('tech_order_specs')->whereIn('case_id', $caseIds)->pluck('id')->all() : [];
            $recordIds = DB::table('medical_records')
                ->where(fn ($q) => $q->whereIn('patient_id', $patientIds)->when($caseIds, fn ($q) => $q->orWhereIn('case_id', $caseIds)))
                ->pluck('id')->all();
            $militaryDebtIds = $caseIds ? DB::table('military_debts')->whereIn('case_id', $caseIds)->pluck('id')->all() : [];

            // 1) عكس صرف/ارتجاع المخزن الخاص بحالاتهم + إلغاء حجز قوائم المواد اللي لسه ما اتصرفتش.
            $movements = fn () => DB::table('stock_movements')->where(function ($q) use ($bomIds, $returnIds) {
                $q->where(fn ($q) => $q->where('reference_type', 'bom')->whereIn('reference_id', $bomIds ?: [0]))
                    ->orWhere(fn ($q) => $q->where('reference_type', 'return_note')->whereIn('reference_id', $returnIds ?: [0]));
            });
            $netByItem = $movements()->selectRaw('stock_item_id, SUM(quantity) as net')->groupBy('stock_item_id')->pluck('net', 'stock_item_id')->all();
            $netByBatch = $movements()->whereNotNull('stock_item_price_id')->selectRaw('stock_item_price_id, SUM(quantity) as net')->groupBy('stock_item_price_id')->pluck('net', 'stock_item_price_id')->all();
            $reservedByCode = $bomIds ? DB::table('bom_items')->join('boms', 'boms.id', '=', 'bom_items.bom_id')
                ->whereIn('boms.id', $bomIds)->where('boms.stage', 'raw')->whereNotNull('boms.stock_reserved_at')
                ->selectRaw('bom_items.stock_item_code as code, SUM(bom_items.qty) as qty')->groupBy('bom_items.stock_item_code')
                ->pluck('qty', 'code')->all() : [];
            $counts['stock_movements'] = $movements()->delete();

            // 2) مستحقات جهات التعاقد الناتجة عن حالاتهم.
            if ($caseIds && Schema::hasTable('contract_debt_accruals')) {
                $byDebt = DB::table('contract_debt_accruals')->whereIn('case_id', $caseIds)
                    ->selectRaw('contract_company_debt_id, SUM(amount) as total')->groupBy('contract_company_debt_id')
                    ->pluck('total', 'contract_company_debt_id')->all();
                foreach ($byDebt as $debtId => $total) {
                    $debt = DB::table('contract_company_debts')->where('id', $debtId)->lockForUpdate()->first();
                    if ($debt) {
                        $due = max(0, round((float) $debt->due - (float) $total, 2));
                        $collected = (float) $debt->collected;
                        DB::table('contract_company_debts')->where('id', $debtId)->update([
                            'due' => $due,
                            'status' => $due <= 0 ? 'pending' : ($collected >= $due ? 'paid' : ($collected > 0 ? 'partial' : 'pending')),
                        ]);
                    }
                }
                $counts['contract_debt_accruals'] = DB::table('contract_debt_accruals')->whereIn('case_id', $caseIds)->delete();
            }

            // 3) الحذف بالترتيب الآمن.
            if ($caseIds) {
                DB::table('cases')->whereIn('id', $caseIds)->update(['pricing_request_id' => null]);
            }
            $del('payments', DB::table('payments')->where(fn ($q) => $q->whereIn('patient_id', $patientIds)->when($caseIds, fn ($q) => $q->orWhereIn('case_id', $caseIds))));
            $del('quote_items', $in('quote_items', 'quote_id', $quoteIds));
            $del('approval_contracts', $in('approval_contracts', 'case_id', $caseIds));
            $del('quotes', $in('quotes', 'id', $quoteIds));
            $del('pricing_request_items', $in('pricing_request_items', 'pricing_request_id', $pricingIds));
            $del('pricing_requests', $in('pricing_requests', 'id', $pricingIds));
            if ($militaryDebtIds && Schema::hasTable('debt_collection_entries')) {
                $counts['military_collections'] = DB::table('debt_collection_entries')->where('payable_type', 'military_debt')->whereIn('payable_id', $militaryDebtIds)->delete();
            }
            $del('military_debts', $in('military_debts', 'id', $militaryDebtIds));
            $del('spec_edit_requests', $in('spec_edit_requests', 'case_id', $caseIds));
            $del('stock_dispense_requests', $in('stock_dispense_requests', 'case_id', $caseIds));
            $del('services_approvals', $in('services_approvals', 'case_id', $caseIds));
            $del('case_workshop_assignments', $in('case_workshop_assignments', 'case_id', $caseIds));
            $del('return_note_lines', $in('return_note_lines', 'return_note_id', $returnIds));
            $del('return_notes', $in('return_notes', 'id', $returnIds));
            $del('bom_items', $in('bom_items', 'bom_id', $bomIds));
            $del('boms', $in('boms', 'id', $bomIds));
            $del('credit_notes', $in('credit_notes', 'case_id', $caseIds));
            $del('case_recommendations', $in('case_recommendations', 'case_id', $caseIds));
            $del('tech_order_spec_items', $in('tech_order_spec_items', 'tech_order_spec_id', $specIds));
            $del('tech_order_specs', $in('tech_order_specs', 'id', $specIds));
            $del('medical_record_items', $in('medical_record_items', 'medical_record_id', $recordIds));
            $del('medical_records', $in('medical_records', 'id', $recordIds));
            $del('notifications', $in('app_notifications', 'case_id', $caseIds));
            $del('cases', $in('cases', 'id', $caseIds));
            $del('appointments', DB::table('appointments')->whereIn('patient_id', $patientIds));
            $del('patients', DB::table('patients')->whereIn('id', $patientIds));

            // 4) تطبيق عكس المخزن على الأصناف المتأثرة فقط.
            foreach ($netByBatch as $batchId => $net) {
                $batch = StockItemPrice::query()->find($batchId);
                $batch?->update(['qty' => round((float) $batch->qty - (float) $net, 4)]);
            }
            $touched = array_keys($netByItem);
            foreach ($reservedByCode as $code => $qty) {
                $item = StockItem::findByOperationalCode((string) $code);
                if ($item) {
                    $item->reserved = max(0, round((float) $item->reserved - (float) $qty, 4));
                    $item->save();
                    $touched[] = $item->id;
                }
            }
            StockItem::query()->whereIn('id', array_unique($touched) ?: [0])->each(function (StockItem $item) use ($netByItem) {
                $item->qty = round((float) $item->qty - (float) ($netByItem[$item->id] ?? 0), 4);
                $item->recalculateAndSaveStatus();
                $item->save();
            });
            $counts['stock_items_restored'] = count(array_unique($touched));
        });

        AuditService::log(
            action: 'purge',
            description: 'حذف مرضى محددين ('.count($patientIds).'): '.mb_substr(implode('، ', $names), 0, 400),
            tag: 'admin',
            after: $counts,
        );
        AdminOverviewService::clearBiBoardsCache();

        return $counts;
    }
}
