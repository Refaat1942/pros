<?php

namespace Tests\Feature\Integrity;

use App\Models\CaseRecord;
use App\Models\Payment;
use App\Models\Quote;
use App\Services\PatientDeletionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * C-4: منع محو السجلات المالية/القانونية عند حذف المريض.
 */
class PatientDeletionGuardTest extends TestCase
{
    use ProstheticTestHelper;
    use RefreshDatabase;

    private function cashierAwaitingCase(): CaseRecord
    {
        $this->stockItem('RM-001', qty: 10);
        $case = $this->operationsReadyCase($this->cashPatient());

        return $case->fresh();
    }

    public function test_patient_with_payment_is_blocked_from_deletion(): void
    {
        $case = $this->cashierAwaitingCase();
        $quote = Quote::where('case_id', $case->id)->firstOrFail();

        Payment::create([
            'payment_no' => 'PAY-GUARD-0001',
            'installment_no' => 1,
            'case_id' => $case->id,
            'quote_id' => $quote->id,
            'patient_id' => $case->patient_id,
            'patient_name' => 'اختبار',
            'amount' => 500,
            'method' => 'cash',
            'received_at' => now(),
        ]);

        $guard = app(PatientDeletionGuard::class);
        $patient = $case->patient;

        $this->assertTrue($guard->patientHasFinancialRecords($patient));

        try {
            $guard->assertPatientDeletable($patient);
            $this->fail('كان يجب منع حذف مريض له دفعة مالية.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        // السجل المالي باقٍ.
        $this->assertDatabaseHas('payments', ['payment_no' => 'PAY-GUARD-0001']);
    }

    public function test_patient_without_financial_records_can_be_deleted(): void
    {
        $patient = $this->cashPatient();
        $guard = app(PatientDeletionGuard::class);

        $this->assertFalse($guard->patientHasFinancialRecords($patient));

        // لا استثناء.
        $guard->assertPatientDeletable($patient);
        $this->assertTrue(true);
    }

    public function test_case_with_military_debt_blocks_deletion(): void
    {
        $this->stockItem('RM-MIL', qty: 10);
        $case = $this->operationsReadyCase($this->militaryPatient($this->militaryCompany()));

        \App\Models\MilitaryDebt::create([
            'case_id' => $case->id,
            'work_order_no' => $case->work_order_no,
            'patient_name' => 'اختبار عسكري',
            'sovereign_entity' => 'القوات المسلحة',
            'total_cost' => 1000,
            'status' => 'pending_collection',
        ]);

        $guard = app(PatientDeletionGuard::class);
        $this->assertTrue($guard->caseHasFinancialRecords($case->fresh()));
    }
}
