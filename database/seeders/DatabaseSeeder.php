<?php

namespace Database\Seeders;

use Database\Seeders\Support\SeedRegistry;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * بعد migrate:fresh — قاعدة البيانات فارغة ما عدا:
     *   - roles
     *   - users (حساب اختبار لكل لوحة: {slug}@clinic.local)
     *
     * باقي الجداول تُملأ يدوياً من التطبيق أو بإلغاء التعليق على seeders أدناه.
     */
    public function run(): void
    {
        SeedRegistry::reset();

        $this->call([
            RolesAndAdminSeeder::class,

            // ── بيانات أساسية (معطّلة — فعّل عند الحاجة) ─────────────────────
            // ContractCompanySeeder::class,
            // MilitaryRankSeeder::class,
            // VisitTypeSeeder::class,
            // StockCategorySeeder::class,

            // ── موردون ومخزون ───────────────────────────────────────────────
            // ContractCompanyDebtSeeder::class,
            // SupplierSeeder::class,
            // InventorySeeder::class,

            // ── مسار المريض والحالات ────────────────────────────────────────
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
