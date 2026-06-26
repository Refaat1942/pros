<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('debt_collection_entries')
            ->where('payable_type', 'App\\Models\\ContractCompanyDebt')
            ->update(['payable_type' => 'contract_company_debt']);

        DB::table('debt_collection_entries')
            ->where('payable_type', 'App\\Models\\MilitaryDebt')
            ->update(['payable_type' => 'military_debt']);
    }

    public function down(): void
    {
        DB::table('debt_collection_entries')
            ->where('payable_type', 'contract_company_debt')
            ->update(['payable_type' => 'App\\Models\\ContractCompanyDebt']);

        DB::table('debt_collection_entries')
            ->where('payable_type', 'military_debt')
            ->update(['payable_type' => 'App\\Models\\MilitaryDebt']);
    }
};
