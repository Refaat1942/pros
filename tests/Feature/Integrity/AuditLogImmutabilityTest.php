<?php

namespace Tests\Feature\Integrity;

use App\Models\AuditLog;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * C-5: عدم قابلية تعديل/حذف سجل الرقابة — على مستوى قاعدة البيانات (مشغّلات).
 *
 * تعمل على SQLite (اختبارات) وPostgreSQL/MySQL (إنتاج/LAN) — المشغّلات تُثبَّت
 * حسب المحرك في الهجرة.
 */
class AuditLogImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    private function seedAuditRow(): int
    {
        AuditService::log(
            action: 'create',
            description: 'سجل اختبار',
            tag: 'admin',
            after: ['x' => 1],
        );

        return (int) AuditLog::query()->latest('id')->value('id');
    }

    /**
     * ينفّذ عبارة يُتوقّع أن يرفضها المشغّل داخل savepoint حتى لا تُفسِد معاملة
     * RefreshDatabase المحيطة (على SQLite، فشل عبارة يُبطل المعاملة كلها).
     */
    private function assertStatementRejected(callable $statement): void
    {
        $rejected = false;

        try {
            DB::transaction(function () use ($statement) {
                $statement();
            });
        } catch (\Illuminate\Database\QueryException $e) {
            $rejected = true;
        }

        $this->assertTrue($rejected, 'العبارة كان يجب أن تُرفض على مستوى قاعدة البيانات.');
    }

    public function test_raw_update_on_audit_logs_is_rejected(): void
    {
        $id = $this->seedAuditRow();

        // تجاوز حارس النموذج عبر DB::table — يجب أن يرفضه المشغّل في قاعدة البيانات.
        $this->assertStatementRejected(
            fn () => DB::table('audit_logs')->where('id', $id)->update(['description' => 'محاولة تعديل']),
        );
    }

    public function test_raw_delete_on_audit_logs_is_rejected(): void
    {
        $id = $this->seedAuditRow();

        $this->assertStatementRejected(
            fn () => DB::table('audit_logs')->where('id', $id)->delete(),
        );
    }

    public function test_insert_still_works(): void
    {
        $before = AuditLog::count();
        $this->seedAuditRow();
        $this->assertSame($before + 1, AuditLog::count());
    }

    public function test_row_remains_after_rejected_mutation(): void
    {
        $id = $this->seedAuditRow();

        $this->assertStatementRejected(
            fn () => DB::table('audit_logs')->where('id', $id)->delete(),
        );

        $this->assertDatabaseHas('audit_logs', ['id' => $id]);
    }
}
