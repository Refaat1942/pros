<?php

namespace Tests\Feature\Integrity;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Correction A: الهجرة لا تُعدّل بيانات مالية تلقائياً — تكشف التكرار وتُوقِف بأمان،
 * والإصلاح يتم عبر أمر متعمّد (عرض أولاً ثم --apply) دون حذف أي دفعة.
 *
 * عزل المحرك: هذا الاختبار يفحص «منطق الأمر» (إعادة الترقيم) وهو مستقل عن المحرك
 * (query-builder صرف). لأنه يحتاج إسقاط القيد الفريد لإدخال تكرار — وعبارات DDL
 * على MySQL تُنفَّذ COMMIT ضمنياً وتُفسِد عزل RefreshDatabase على القاعدة المشتركة —
 * نُثبّت هذا الاختبار على اتصال SQLite بذاكرة مستقلة خاص به فقط. أما سلوك القيد
 * الفعلي على PostgreSQL و MySQL فمُتحقَّق منه مباشرةً (raw SQL) في تقارير التحقق.
 */
class DuplicateInstallmentRemediationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // اتصال SQLite مستقل بذاكرة — لا يمسّ قاعدة الاختبار المشتركة (MySQL/PG).
        config([
            'database.default' => 'sqlite_remediation',
            'database.connections.sqlite_remediation' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        DB::purge('sqlite_remediation');
        $this->artisan('migrate', ['--database' => 'sqlite_remediation', '--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite_remediation');
        parent::tearDown();
    }

    private function dropUniqueConstraint(): void
    {
        Schema::connection('sqlite_remediation')->table('payments', function ($table) {
            $table->dropUnique('payments_case_installment_unique');
        });
    }

    private function seedDuplicatePayments(): int
    {
        $this->dropUniqueConstraint();

        $patientId = DB::table('patients')->insertGetId([
            'patient_code' => 'PDUP', 'patient_qr' => 'QRDUP', 'name' => 'مكرر',
            'registered_at' => now(), 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $caseId = DB::table('cases')->insertGetId([
            'case_no' => 'CDUP', 'order_ref' => 'ODUP', 'patient_id' => $patientId,
            'patient_type' => 'civilian', 'path' => 'standard', 'stage_key' => 'cashier',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach (['PAY-D1', 'PAY-D2'] as $no) {
            Payment::create([
                'payment_no' => $no,
                'installment_no' => 1, // نفس رقم القسط — تكرار متعمّد
                'case_id' => $caseId,
                'amount' => 250,
                'method' => 'cash',
                'received_at' => now(),
            ]);
        }

        return $caseId;
    }

    public function test_remediation_command_dry_run_changes_nothing(): void
    {
        $caseId = $this->seedDuplicatePayments();

        $this->artisan('prosthetics:remediate-duplicate-installments')
            ->assertSuccessful();

        // لا تغيير: القسطان ما زالا 1 (عرض فقط).
        $installments = Payment::where('case_id', $caseId)->pluck('installment_no')->all();
        $this->assertSame([1, 1], $installments);
        $this->assertSame(2, Payment::where('case_id', $caseId)->count());
    }

    public function test_remediation_command_apply_renumbers_without_data_loss(): void
    {
        $caseId = $this->seedDuplicatePayments();
        $amountsBefore = Payment::where('case_id', $caseId)->orderBy('id')->pluck('amount')->all();

        $this->artisan('prosthetics:remediate-duplicate-installments --apply')
            ->assertSuccessful();

        // كل الدفعات محفوظة، المبالغ لم تتغيّر، والأقساط أصبحت فريدة.
        $this->assertSame(2, Payment::where('case_id', $caseId)->count());
        $installments = Payment::where('case_id', $caseId)->orderBy('id')->pluck('installment_no')->all();
        $this->assertSame([1, 2], $installments);
        $this->assertSame(
            $amountsBefore,
            Payment::where('case_id', $caseId)->orderBy('id')->pluck('amount')->all(),
        );

        // حدث الإصلاح مُدوَّن في سجل الرقابة.
        $this->assertDatabaseHas('audit_logs', ['action' => 'remediate']);
    }

    public function test_no_duplicates_reports_clean(): void
    {
        $this->artisan('prosthetics:remediate-duplicate-installments')
            ->expectsOutputToContain('لا توجد أقساط مكررة')
            ->assertSuccessful();
    }
}
