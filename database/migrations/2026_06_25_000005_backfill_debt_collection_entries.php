<?php

use App\Models\ContractCompanyDebt;
use App\Models\MilitaryDebt;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        ContractCompanyDebt::query()
            ->where('collected', '>', 0)
            ->each(function (ContractCompanyDebt $debt) use ($now) {
                $exists = DB::table('debt_collection_entries')
                    ->where('payable_type', 'contract_company_debt')
                    ->where('payable_id', $debt->id)
                    ->exists();

                if ($exists) {
                    return;
                }

                $due       = (float) $debt->due;
                $collected = (float) $debt->collected;

                DB::table('debt_collection_entries')->insert([
                    'payable_type'      => 'contract_company_debt',
                    'payable_id'        => $debt->id,
                    'installment_no'    => 1,
                    'amount'            => $collected,
                    'running_collected' => $collected,
                    'remaining_after'   => max(0, round($due - $collected, 2)),
                    'recorded_by'       => null,
                    'recorded_by_name'  => 'ترحيل سابق',
                    'collected_at'      => $debt->updated_at ?? $now,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);
            });

        MilitaryDebt::query()
            ->where('collected', '>', 0)
            ->each(function (MilitaryDebt $debt) use ($now) {
                $exists = DB::table('debt_collection_entries')
                    ->where('payable_type', 'military_debt')
                    ->where('payable_id', $debt->id)
                    ->exists();

                if ($exists) {
                    return;
                }

                $due       = (float) $debt->total_cost;
                $collected = (float) $debt->collected;

                DB::table('debt_collection_entries')->insert([
                    'payable_type'      => 'military_debt',
                    'payable_id'        => $debt->id,
                    'installment_no'    => 1,
                    'amount'            => $collected,
                    'running_collected' => $collected,
                    'remaining_after'   => max(0, round($due - $collected, 2)),
                    'recorded_by'       => null,
                    'recorded_by_name'  => 'ترحيل سابق',
                    'collected_at'      => $debt->collected_at ?? $debt->updated_at ?? $now,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);
            });
    }

    public function down(): void
    {
        DB::table('debt_collection_entries')
            ->where('recorded_by_name', 'ترحيل سابق')
            ->delete();
    }
};
