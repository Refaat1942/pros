<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Database\Seeders\Support\PrototypeSeedData;
use Database\Seeders\Support\SeedRegistry;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PrototypeSeedData::supplierNamesFromStock() as $name) {
            $supplier = Supplier::query()->firstOrCreate(['name' => $name]);

            SeedRegistry::$suppliers[$name] = $supplier->id;
        }

        foreach (PrototypeSeedData::extraSupplierNames() as $name) {
            if (isset(SeedRegistry::$suppliers[$name])) {
                continue;
            }

            $supplier = Supplier::query()->firstOrCreate(
                ['name' => $name],
                ['notes' => 'مورد إضافي من لوحة الإدارة']
            );

            SeedRegistry::$suppliers[$name] = $supplier->id;
        }
    }
}
