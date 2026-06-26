<?php

namespace App\Services\Dashboard;

use App\Models\Appointment;
use App\Models\Bom;
use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\Quote;
use App\Support\ClinicTime;

/**
 * استعلامات الطوابير الحية — نفس منطق لوحات التحكم (Query-Chain Monitoring).
 */
class DashboardQueueService
{
    /** @return list<int> */
    public function doctorWaitingPatientIds(?string $date = null): array
    {
        $date = $date ?? ClinicTime::todayDateString();

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

    /** مرضى بانتظار الكشف في عيادة الطبيب ليوم معيّن. */
    public function doctorWaitingCount(?string $date = null): int
    {
        $date = $date ?? ClinicTime::todayDateString();

        return Appointment::query()
            ->whereDate('appointment_date', $date)
            ->where('status', Appointment::STATUS_IN_CLINIC)
            ->where('transferred_to_clinic', true)
            ->count();
    }

    /** مرضى مسجّلون في الاستقبال اليوم ولم يُحوَّلوا للعيادة بعد. */
    public function doctorReceptionPendingCount(?string $date = null): int
    {
        $date = $date ?? ClinicTime::todayDateString();

        return Appointment::query()
            ->whereDate('appointment_date', $date)
            ->where('status', Appointment::STATUS_WAITING)
            ->where('transferred_to_clinic', false)
            ->count();
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
    /** أوامر في ورشة التصنيع (BOM wip). */
    public function operationsManufacturingCaseIds(): array
    {
        return CaseRecord::query()
            ->workshopDeskQueue()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return list<int> */
    public function technicalBomRawIds(): array
    {
        return Bom::query()
            ->where('stage', Bom::STAGE_RAW)
            ->whereHas('caseRecord', fn ($q) => $q->where('stage_key', CaseRecord::STAGE_MANUFACTURING))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Civilian cases with quote issued to reception — awaiting OCR / entity approval.
     * After operations release the case may already be at warehouse (MFG_WAREHOUSE).
     *
     * @return list<int>
     */
    public function receptionApprovalPendingCaseIds(): array
    {
        return CaseRecord::query()
            ->where('patient_type', Patient::TYPE_CIVILIAN)
            ->where(function ($q) {
                $q->where('stage_key', CaseRecord::STAGE_OPERATIONS)
                  ->orWhere(function ($q) {
                      $q->where('stage_key', CaseRecord::STAGE_MANUFACTURING)
                        ->where('manufacturing_stage', CaseRecord::MFG_WAREHOUSE);
                  });
            })
            ->whereHas('quotes', fn ($q) => $q->where('status', Quote::STATUS_ISSUED))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return list<int> */
    public function adjustmentsCaseIds(): array
    {
        return CaseRecord::query()
            ->where('stage_key', CaseRecord::STAGE_ADJUSTMENTS)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return list<int> */
    public function operationsPendingCaseIds(): array
    {
        return CaseRecord::query()
            ->where('stage_key', CaseRecord::STAGE_OPERATIONS)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return list<int> */
    public function operationsIssuedQuoteIds(): array
    {
        return Quote::query()
            ->where('status', Quote::STATUS_ISSUED)
            ->whereHas('caseRecord', fn ($q) => $q->where('patient_type', Patient::TYPE_CIVILIAN))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function operationsIssuedQuotesCount(): int
    {
        return count($this->operationsIssuedQuoteIds());
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
