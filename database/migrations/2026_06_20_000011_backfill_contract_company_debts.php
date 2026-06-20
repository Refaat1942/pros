<?php

use App\Models\ContractCompany;
use App\Services\ContractDebtService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        /** @var ContractDebtService $debts */
        $debts = app(ContractDebtService::class);

        ContractCompany::query()
            ->whereDoesntHave('debt')
            ->orderBy('id')
            ->each(fn (ContractCompany $company) => $debts->initialise($company));
    }

    public function down(): void
    {
        // irreversible data correction
    }
};
