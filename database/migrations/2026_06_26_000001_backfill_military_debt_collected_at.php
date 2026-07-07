<?php

use App\Models\DebtCollectionEntry;
use App\Models\MilitaryDebt;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        MilitaryDebt::query()
            ->where('collected', '>', 0)
            ->whereNull('collected_at')
            ->each(function (MilitaryDebt $debt) {
                $lastAt = DebtCollectionEntry::query()
                    ->where('payable_type', 'military_debt')
                    ->where('payable_id', $debt->id)
                    ->orderByDesc('installment_no')
                    ->value('collected_at');

                if ($lastAt) {
                    $debt->update(['collected_at' => $lastAt]);
                }
            });
    }

    public function down(): void
    {
        // لا رجوع — البيانات المُحدَّثة تبقى
    }
};
