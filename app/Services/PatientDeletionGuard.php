<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;

/**
 * C-4: حارس حذف المرضى — يمنع محو السجلات المالية والقانونية في مسارات الحذف العادية.
 *
 * نطاق الحماية:
 *   - يحمي «مسارات حذف التطبيق العادية» (حالياً AppointmentService::removeReceptionEntry).
 *   - لا يحمي مسار المسح الإداري المتعمّد (PatientDataPurgeService) — انظر توثيق ذلك
 *     الكلاس: المسح يحذف الأبناء بترتيب آمن لقيود RESTRICT ويحفظ سجل الرقابة.
 *
 * حماية على مستوى التطبيق تعمل في كل محركات قاعدة البيانات (PostgreSQL على VPS
 * وعلى الشبكة المحلية الأوفلاين، و SQLite في الاختبارات). طبقة ثانية على مستوى
 * قاعدة البيانات: قيود RESTRICT على payments/credit_notes/military_debts.
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
     * هل تملك الحالة سجلات مالية؟ (لبنة قابلة لإعادة الاستخدام — تُستخدم داخلياً
     * وفي الاختبارات. لا يوجد اليوم مسار حذف مباشر لحالة في التطبيق، لذا لا يوجد
     * assertCaseDeletable حتى لا يبقى كود حماية ميت يوحي بحماية غير قائمة.)
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
