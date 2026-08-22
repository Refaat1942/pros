<?php

namespace Tests\Feature\Pipeline;

use App\Models\CaseRecord;
use App\Models\Payment;
use App\Models\Quote;
use App\Services\CashierPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * C-1: منع التحصيل المزدوج في الخزنة.
 *
 * لا يمكن محاكاة تزامن حقيقي على SQLite بذاكرة واحدة، لذا نتحقق من الضمانتين
 * القابلتين للاختبار:
 *   1) إعادة حساب المتبقي تحت القفل داخل المعاملة → تأكيد ثانٍ على حالة مدفوعة
 *      بالكامل يُرفض (الحالة لم تعد بانتظار الدفع).
 *   2) قيد قاعدة البيانات الفريد يمنع تكرار (case_id, installment_no).
 */
class CashierPaymentConcurrencyTest extends TestCase
{
    use ProstheticTestHelper;
    use RefreshDatabase;

    private function cashierAwaitingCase(): CaseRecord
    {
        $this->stockItem('RM-001', qty: 10);
        $case = $this->operationsReadyCase($this->cashPatient());

        $this->assertSame(CaseRecord::STAGE_CASHIER, $case->fresh()->stage_key);

        return $case->fresh();
    }

    public function test_second_full_payment_is_rejected_after_case_leaves_cashier(): void
    {
        $case = $this->cashierAwaitingCase();
        $service = app(CashierPaymentService::class);

        // أول تأكيد كامل — ينجح ويحوّل الحالة خارج الخزنة.
        $first = $service->confirmPayment($case, ['method' => 'cash']);
        $this->assertTrue($first['fully_paid']);
        $this->assertSame(CaseRecord::STAGE_OPERATIONS, $case->fresh()->stage_key);

        // تأكيد ثانٍ (محاكاة نقرة مزدوجة/طلب مكرر) — يُرفض لأن الحالة لم تعد بانتظار الدفع.
        try {
            $service->confirmPayment($case->fresh(), ['method' => 'cash']);
            $this->fail('التأكيد الثاني كان يجب أن يُرفض.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        // دفعة واحدة فقط سُجّلت — لا تحصيل مزدوج.
        $this->assertSame(1, Payment::where('case_id', $case->id)->count());
    }

    public function test_duplicate_installment_number_is_rejected_by_db_constraint(): void
    {
        $case = $this->cashierAwaitingCase();
        $quote = Quote::where('case_id', $case->id)->firstOrFail();

        Payment::create([
            'payment_no' => 'PAY-TEST-0001',
            'installment_no' => 1,
            'case_id' => $case->id,
            'quote_id' => $quote->id,
            'patient_id' => $case->patient_id,
            'patient_name' => 'اختبار',
            'amount' => 100,
            'method' => 'cash',
            'received_at' => now(),
        ]);

        // نفس (case_id, installment_no) — يجب أن يرفضه القيد الفريد.
        // ننفّذه داخل savepoint حتى لا يُفسد معاملة RefreshDatabase على SQLite.
        $rejected = false;
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($case, $quote) {
                Payment::create([
                    'payment_no' => 'PAY-TEST-0002',
                    'installment_no' => 1,
                    'case_id' => $case->id,
                    'quote_id' => $quote->id,
                    'patient_id' => $case->patient_id,
                    'patient_name' => 'اختبار مكرر',
                    'amount' => 100,
                    'method' => 'cash',
                    'received_at' => now(),
                ]);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            $rejected = true;
        }

        $this->assertTrue($rejected, 'القيد الفريد كان يجب أن يرفض القسط المكرر.');
        $this->assertSame(1, Payment::where('case_id', $case->id)->count());
    }

    public function test_partial_payments_still_work_and_use_distinct_installments(): void
    {
        $case = $this->cashierAwaitingCase();
        $quote = Quote::where('case_id', $case->id)->firstOrFail();
        $due = (float) $quote->total;
        $this->assertGreaterThan(0, $due);

        $service = app(CashierPaymentService::class);

        $first = $service->confirmPayment($case, ['method' => 'cash', 'amount' => round($due / 2, 2)]);
        $this->assertFalse($first['fully_paid']);
        $this->assertSame(CaseRecord::STAGE_CASHIER, $case->fresh()->stage_key);

        $second = $service->confirmPayment($case->fresh(), ['method' => 'cash', 'amount' => round($due / 2, 2)]);
        $this->assertTrue($second['fully_paid']);

        $installments = Payment::where('case_id', $case->id)->orderBy('installment_no')->pluck('installment_no')->all();
        $this->assertSame([1, 2], $installments);
    }
}
