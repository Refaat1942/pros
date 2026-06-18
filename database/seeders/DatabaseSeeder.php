<?php

namespace Database\Seeders;

use Database\Seeders\Support\SeedRegistry;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * بيانات العرض التجريبي — من DEFAULT arrays في assets/js/shared/*.js
     *
     * سيناريوهات مغطاة:
     * - مدني كامل: CASE-2026-003 (تسعير → عرض → انتظار رجوع)
     * - مدني مبكر: CASE-2026-001/002 (انتظار موافقة أدمن)
     * - مدني تصنيع: CASE-2026-005/006 (BOM raw/wip)
     * - مدني مُسلّم: CASE-2026-007/008 + credit note
     * - عسكري bypass: CASE-2026-004 (path=military, pricingQueueId=null)
     * - BOM: raw (001,005) → wip (002,006,008) → finished (003,004,007)
     */
    public function run(): void
    {
        SeedRegistry::reset();

        $this->call([
            ContractCompanySeeder::class,
            ContractCompanyDebtSeeder::class,
            SupplierSeeder::class,
            InventorySeeder::class,
            PatientSeeder::class,
            CaseSeeder::class,
            PricingSeeder::class,
            QuoteSeeder::class,
            BomSeeder::class,
            ReturnNoteSeeder::class,
            CreditNoteSeeder::class,
        ]);
    }
}
