<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\SupplierDebt;
use Illuminate\Support\Facades\DB;

class SupplierDebtService
{
    public function increaseDue(Supplier $supplier, float $amount): SupplierDebt
    {
        if ($amount <= 0) {
            return $this->forSupplier($supplier);
        }

        return DB::transaction(function () use ($supplier, $amount) {
            $debt = SupplierDebt::query()
                ->lockForUpdate()
                ->firstOrCreate(
                    ['supplier_id' => $supplier->id],
                    ['due' => 0, 'collected' => 0, 'status' => 'pending']
                );

            $debt->update([
                'due' => round((float) $debt->due + $amount, 2),
            ]);

            $debt->refreshStatus();

            return $debt->fresh();
        });
    }

    public function forSupplier(Supplier $supplier): SupplierDebt
    {
        return SupplierDebt::query()->firstOrCreate(
            ['supplier_id' => $supplier->id],
            ['due' => 0, 'collected' => 0, 'status' => 'pending']
        );
    }
}
