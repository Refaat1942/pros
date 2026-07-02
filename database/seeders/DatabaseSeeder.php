<?php

namespace Database\Seeders;

use Database\Seeders\Support\SeedRegistry;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * بعد migrate:fresh — قاعدة البيانات فارغة ما عدا:
     *   - roles
     *   - users (حساب اختبار لكل لوحة: username = slug الدور)
     *
     * باقي الجداول تُملأ من seeders أدناه.
     */
    public function run(): void
    {
        SeedRegistry::reset();

        $this->call([
            RolesAndAdminSeeder::class,

            // ── بيانات أساسية ───────────────────────────────────────────────
            ContractCompanySeeder::class,
            // MilitaryRankSeeder::class,
            VisitTypeSeeder::class,
            StockCategorySeeder::class,

            // ── موردون ومخزون ───────────────────────────────────────────────
            // ContractCompanyDebtSeeder::class,
            SupplierSeeder::class,
            InventorySeeder::class,

            // // ── مسار المريض والحالات ────────────────────────────────────────
            // PatientSeeder::class,
            // CaseSeeder::class,
            // PricingSeeder::class,
            // QuoteSeeder::class,
            // BomSeeder::class,
            // ReturnNoteSeeder::class,
            // CreditNoteSeeder::class,
        ]);
    }
}
