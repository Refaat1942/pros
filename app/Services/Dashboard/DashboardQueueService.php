<?php

namespace App\Services\Dashboard;

use App\Enums\PricingRequestStatus;
use App\Models\Appointment;
use App\Models\Bom;
use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\PricingRequest;

/**
 * استعلامات الطوابير الحية — نفس منطق لوحات التحكم (Query-Chain Monitoring).
 */
class DashboardQueueService
{
    /** @return list<int> */
    public function doctorWaitingPatientIds(?string $date = null): array
    {
        $date = $date ?? now()->toDateString();

        return Appointment::query()
            ->whereDate('appointment_date', $date)
            ->where('status', Appointment::STATUS_IN_CLINIC)
            ->where('transferred_to_clinic', true)
            ->whereNotNull('patient_id')
            ->pluck('patient_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<int> */
    public function specTechnicalCaseIds(): array
    {
        return CaseRecord::query()
            ->where('stage_key', CaseRecord::STAGE_TECHNICAL)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return list<int> */
    public function adminPricingAwaitingIds(): array
    {
        return PricingRequest::query()
            ->where('status_key', PricingRequestStatus::AwaitingAdminApproval->value)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return list<int> */
    public function operationsManufacturingCaseIds(): array
    {
        return CaseRecord::query()
            ->where('stage_key', CaseRecord::STAGE_MANUFACTURING)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return list<int> */
    public function technicalBomRawIds(): array
    {
        return Bom::query()
            ->where('stage', Bom::STAGE_RAW)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return list<int> */
    public function receptionDeliveryReadyCaseIds(): array
    {
        return CaseRecord::query()
            ->where('stage_key', CaseRecord::STAGE_READY_DELIVERY)
            ->whereHas('bom', fn ($q) => $q->where('stage', Bom::STAGE_FINISHED))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return list<int> */
    public function deliveredCaseIds(): array
    {
        return CaseRecord::query()
            ->where('stage_key', CaseRecord::STAGE_DELIVERED)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function patientIsArchived(int $patientId): bool
    {
        return Patient::query()
            ->where('id', $patientId)
            ->whereNotNull('archived_at')
            ->exists();
    }
}
