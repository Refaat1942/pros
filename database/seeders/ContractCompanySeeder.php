<?php

namespace Database\Seeders;

use App\Models\ContractCompany;
use Database\Seeders\Support\PrototypeSeedData;
use Database\Seeders\Support\SeedRegistry;
use Illuminate\Database\Seeder;

class ContractCompanySeeder extends Seeder
{
    public function run(): void
    {
        foreach (PrototypeSeedData::contractCompanies() as $row) {
            $company = ContractCompany::query()->create($row);
            SeedRegistry::$companies[$row['name']] = $company->id;
        }
    }
}
