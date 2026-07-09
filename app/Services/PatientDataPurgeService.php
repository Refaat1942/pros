<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Patient;
use App\Models\StockItem;
use Illuminate\Support\Facades\DB;

/**
 * حذف كل بيانات المرضى والحالات مع الإبقاء على الإعدادات والمستخدمين والكتalog.
 */
class PatientDataPurgeService
{
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

            $counts['stock_movements_case'] = DB::table('stock_movements')
                ->whereIn('reference_type', ['bom', 'return_note'])
                ->delete();

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

            if ($resetContractDebts) {
                $counts['contract_debts_reset'] = DB::table('contract_company_debts')->update([
                    'due' => 0,
                    'collected' => 0,
                    'status' => 'pending',
                ]);
                $counts['debt_collection_entries'] = DB::table('debt_collection_entries')->delete();
            }

            if ($syncStock) {
                $counts['stock_items_synced'] = $this->syncStockFromMovements();
            }
        });

        return $counts;
    }

    public function hasPatientData(): bool
    {
        return Patient::query()->exists();
    }

    private function syncStockFromMovements(): int
    {
        $synced = 0;

        StockItem::query()->each(function (StockItem $item) use (&$synced) {
            $lastMovement = $item->movements()
                ->orderByDesc('moved_at')
                ->orderByDesc('id')
                ->first();

            $item->qty = $lastMovement ? (int) $lastMovement->balance_after : (int) $item->qty;
            $item->reserved = 0;
            $item->recalculateAndSaveStatus();
            $item->save();
            $synced++;
        });

        return $synced;
    }
}
