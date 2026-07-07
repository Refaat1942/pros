<?php

namespace Database\Seeders;

use App\Models\ContractCompanyDebt;
use Database\Seeders\Support\PrototypeSeedData;
use Database\Seeders\Support\SeedRegistry;
use Illuminate\Database\Seeder;

class ContractCompanyDebtSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PrototypeSeedData::contractDebts() as $row) {
            $companyId = SeedRegistry::$companies[$row['company']] ?? null;
            if (! $companyId) {
                continue;
            }

            ContractCompanyDebt::query()->updateOrCreate(
                ['contract_company_id' => $companyId],
                [
                    'due' => $row['due'],
                    'collected' => $row['collected'],
                    'status' => $row['status'],
                ]
            );
        }
    }
}
