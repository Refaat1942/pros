<?php

namespace Database\Seeders;

use App\Models\ContractCompany;
use App\Services\ContractDebtService;
use Database\Seeders\Support\PrototypeSeedData;
use Database\Seeders\Support\SeedRegistry;
use Illuminate\Database\Seeder;

class ContractCompanySeeder extends Seeder
{
    public function __construct(private readonly ContractDebtService $contractDebtService) {}

    public function run(): void
    {
        foreach (PrototypeSeedData::contractCompanies() as $row) {
            $company = ContractCompany::query()->create($row);
            $this->contractDebtService->initialise($company);
            SeedRegistry::$companies[$row['name']] = $company->id;
        }
    }
}
