<?php

namespace App\Services\Authorization;

use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\User;

/**
 * تحقق سجلّي رفيع — يُكمّل صلاحيات الصفحة/الإجراء دون استبدالها.
 */
class ResourceAuthorizationService
{
    public function assertCanViewPayment(User $user, Payment $payment): void
    {
        abort_unless(
            $user->canViewDashboardPage('cashier', 'payments'),
            403,
            'ليس لديك صلاحية الوصول إلى هذه الصفحة.',
        );

        $payment->loadMissing('caseRecord');
        $case = $payment->caseRecord;

        abort_unless(
            $case instanceof CaseRecord && $this->isCashierPaymentCaseAccessible($case),
            403,
            'لا يمكنك الوصول إلى إيصال هذا الدفع.',
        );
    }

    public function assertCanViewCasePayments(User $user, CaseRecord $case): void
    {
        abort_unless(
            $user->canViewDashboardPage('cashier', 'payments'),
            403,
            'ليس لديك صلاحية الوصول إلى هذه الصفحة.',
        );

        abort_unless(
            $this->isCashierPaymentCaseAccessible($case),
            403,
            'لا يمكنك عرض دفعات هذه الحالة.',
        );
    }

    public function assertCanConfirmCashPayment(User $user, CaseRecord $case): void
    {
        abort_unless(
            $user->canViewDashboardPage('cashier', 'payments'),
            403,
            'ليس لديك صلاحية الوصول إلى هذه الصفحة.',
        );

        abort_unless(
            $this->isCashierEligibleCaseType($case),
            403,
            'لا يمكن تأكيد تحصيل هذه الحالة في الخزنة.',
        );

        abort_unless(
            $case->isAwaitingCashier(),
            403,
            'الحالة ليست بانتظار الدفع في الخزنة.',
        );
    }

    public function assertCanViewPatient(User $user, Patient $patient): void
    {
        abort_unless(
            $user->canViewDashboardPage('reception', 'patients'),
            403,
            'ليس لديك صلاحية الوصول إلى هذه الصفحة.',
        );
    }

    public function assertCanMutatePatient(User $user, Patient $patient): void
    {
        $this->assertCanViewPatient($user, $patient);

        abort_unless(
            $patient->archived_at === null,
            403,
            'ملف المريض مؤرشف — التعديل غير متاح.',
        );
    }

    public function assertCanOpenCaseForPatient(User $user, Patient $patient): void
    {
        $this->assertCanMutatePatient($user, $patient);
    }

    public function assertCanPrintQuote(User $user, Quote $quote, string $dashboard): void
    {
        match ($dashboard) {
            'cashier' => abort_unless(
                $user->canViewDashboardPage('cashier', 'payments'),
                403,
                'ليس لديك صلاحية الوصول إلى هذه الصفحة.',
            ),
            'operations' => abort_unless(
                $user->canViewDashboardPage('operations', 'pending'),
                403,
                'ليس لديك صلاحية الوصول إلى هذه الصفحة.',
            ),
            default => abort_unless(
                $user->canViewDashboardPage('reception', 'quote'),
                403,
                'ليس لديك صلاحية الوصول إلى هذه الصفحة.',
            ),
        };

        abort_unless(
            $user->can('print-quote'),
            403,
            'ليس لديك صلاحية طباعة عرض السعر.',
        );

        $quote->loadMissing('caseRecord');

        abort_unless(
            $quote->caseRecord?->patient_type === Patient::TYPE_CIVILIAN,
            404,
        );

        abort_unless(
            in_array($quote->status, [Quote::STATUS_ISSUED, Quote::STATUS_APPROVED], true),
            403,
            'عرض السعر غير متاح للطباعة في هذه المرحلة.',
        );
    }

    /**
     * نطاق الخزنة: حالات مدنية نقدية في طابور الخزنة أو سبق تحصيلها جزئياً/كاملاً هناك.
     */
    private function isCashierPaymentCaseAccessible(CaseRecord $case): bool
    {
        if (! $this->isCashierEligibleCaseType($case)) {
            return false;
        }

        if ($case->isAwaitingCashier()) {
            return true;
        }

        return Payment::query()->where('case_id', $case->id)->exists();
    }

    /** مدني كاش فقط — نفس تعريف نطاق إيصال/سجل الدفعات. */
    private function isCashierEligibleCaseType(CaseRecord $case): bool
    {
        return ! $case->isMilitary() && $case->isCashCivilian();
    }
}
