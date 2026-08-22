<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;

/**
 * C-4: حارس حذف المرضى/الحالات — يمنع محو السجلات المالية والقانونية.
 *
 * حماية على مستوى التطبيق تعمل في كل بيئات قاعدة البيانات (PostgreSQL على
 * VPS/LAN و SQLite في الاختبارات). تمنع حذف مريض أو حالة تحمل مدفوعات أو
 * إشعارات دائن أو مديونيات (تعاقدية/عسكرية) — لأن حذفها يُتلف تاريخاً مالياً/قانونياً.
 *
 * الجداول المالية التي تُقيّد الحذف:
 *   - payments             (مدفوعات نقدية محصّلة)
 *   - credit_notes         (إشعارات دائن)
 *   - military_debts       (مديونية سيادية عسكرية)
 */
class PatientDeletionGuard
{
    /**
     * هل يملك المريض سجلات مالية تمنع الحذف؟
     */
    public function patientHasFinancialRecords(Patient $patient): bool
    {
        $caseIds = CaseRecord::query()
            ->where('patient_id', $patient->id)
            ->pluck('id');

        if ($caseIds->isEmpty()) {
            return false;
        }

        return $this->caseIdsHaveFinancialRecords($caseIds->all());
    }

    /**
     * هل تملك الحالة سجلات مالية تمنع الحذف؟
     */
    public function caseHasFinancialRecords(CaseRecord $case): bool
    {
        return $this->caseIdsHaveFinancialRecords([$case->id]);
    }

    public function assertPatientDeletable(Patient $patient): void
    {
        if ($this->patientHasFinancialRecords($patient)) {
            abort(422, 'لا يمكن حذف المريض — يوجد سجل مالي (مدفوعات/مديونية/إشعار دائن) مرتبط. السجلات المالية والقانونية تُحفظ دائماً.');
        }
    }

    public function assertCaseDeletable(CaseRecord $case): void
    {
        if ($this->caseHasFinancialRecords($case)) {
            abort(422, 'لا يمكن حذف الحالة — يوجد سجل مالي مرتبط. السجلات المالية والقانونية تُحفظ دائماً.');
        }
    }

    /**
     * @param  list<int>  $caseIds
     */
    private function caseIdsHaveFinancialRecords(array $caseIds): bool
    {
        if ($caseIds === []) {
            return false;
        }

        return DB::table('payments')->whereIn('case_id', $caseIds)->exists()
            || DB::table('credit_notes')->whereIn('case_id', $caseIds)->exists()
            || DB::table('military_debts')->whereIn('case_id', $caseIds)->exists();
    }
}
