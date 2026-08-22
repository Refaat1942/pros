<?php

namespace Tests\Feature\Integrity;

use App\Services\PatientDataPurgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Correction C: يثبت أن المسح يعمل بشكل صحيح مع قيود RESTRICT للمفاتيح الأجنبية
 * على PostgreSQL — يحذف الأبناء الماليين بترتيب آمن قبل «cases»، ويحفظ سجل الرقابة.
 *
 * يُتخطّى على SQLite (لا يفرض RESTRICT عبر ALTER) — هذا الاختبار خاص بـ PostgreSQL
 * وهو بيئة الإنتاج على VPS وعلى الشبكة المحلية الأوفلاين.
 */
class PurgeUnderRestrictPgTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('اختبار خاص بـ PostgreSQL (RESTRICT حقيقي).');
        }
    }

    public function test_purge_succeeds_with_restrict_fks_and_preserves_audit(): void
    {
        $patientId = DB::table('patients')->insertGetId([
            'patient_code' => 'PPG', 'patient_qr' => 'QRPG', 'name' => 'Restrict Test',
            'registered_at' => now(), 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $caseId = DB::table('cases')->insertGetId([
            'case_no' => 'CPG', 'order_ref' => 'OPG', 'patient_id' => $patientId,
            'patient_type' => 'civilian', 'path' => 'standard', 'stage_key' => 'cashier',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // أبناء ماليون بقيود RESTRICT على case_id.
        DB::table('payments')->insert([
            'payment_no' => 'PAY-PG', 'installment_no' => 1, 'case_id' => $caseId,
            'amount' => 100, 'method' => 'cash', 'received_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('credit_notes')->insert([
            'credit_note_no' => 'CN-PG', 'case_id' => $caseId, 'order_ref' => 'OPG',
            'patient_name' => 'Restrict Test', 'amount' => 50, 'original_total' => 100,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('military_debts')->insert([
            'case_id' => $caseId, 'patient_name' => 'Restrict Test',
            'sovereign_entity' => 'X', 'total_cost' => 100,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        \App\Services\AuditService::log(action: 'create', description: 'pre-purge', tag: 'patients');
        $auditBefore = DB::table('audit_logs')->count();

        // يجب أن ينجح رغم قيود RESTRICT (ترتيب حذف آمن).
        $counts = app(PatientDataPurgeService::class)->purge();

        $this->assertSame(0, DB::table('patients')->count());
        $this->assertSame(0, DB::table('cases')->count());
        $this->assertSame(0, DB::table('payments')->count());
        $this->assertSame(0, DB::table('credit_notes')->count());
        $this->assertSame(0, DB::table('military_debts')->count());

        // سجل الرقابة محفوظ + حدث المسح مُضاف.
        $this->assertGreaterThanOrEqual($auditBefore, DB::table('audit_logs')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'purge']);
    }
}
