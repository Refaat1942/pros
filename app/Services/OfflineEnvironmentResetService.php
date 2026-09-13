<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * مسح تشغيلي للبيئة الأوفلاين — حالات المرضى ثم كتالوج الأصناف بالكامل.
 */
class OfflineEnvironmentResetService
{
    public function __construct(
        private readonly PatientDataPurgeService $patientPurge,
        private readonly StockCatalogPurgeService $catalogPurge,
    ) {}

    public function hasDataToReset(): bool
    {
        return $this->patientPurge->hasPatientRelatedData()
            || $this->catalogPurge->hasStockItems()
            || DB::table('supply_requests')->exists();
    }

    /**
     * @return array<string, int>
     */
    public function reset(bool $resetContractDebts = true): array
    {
        $counts = [];

        if ($this->patientPurge->hasPatientRelatedData()) {
            foreach ($this->patientPurge->purge(
                resetContractDebts: $resetContractDebts,
                syncStock: false,
            ) as $key => $value) {
                $counts['patient.'.$key] = $value;
            }
        }

        if ($this->catalogPurge->hasStockItems() || DB::table('supply_requests')->exists()) {
            foreach ($this->catalogPurge->purge() as $key => $value) {
                $counts['catalog.'.$key] = $value;
            }
        }

        AuditService::log(
            action: 'purge',
            description: 'مسح بيئة أوفلاين — حالات المرضى وكتالوج الأصناف',
            tag: 'admin',
            after: $counts,
        );

        StockCatalogPicker::forgetCachedRows();

        return $counts;
    }
}
