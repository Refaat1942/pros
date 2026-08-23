<?php

namespace Tests\Feature\Integrity;

use App\Support\AuditLogImmutability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * C-5 fix: أمر تثبيت مشغّلات حماية سجل الرقابة (للـ DBA على MySQL).
 *
 * على SQLite/PostgreSQL المشغّلات تُدار عبر الهجرة، فالأمر يبلّغ فقط ولا يفشل.
 * سلوك MySQL الفعلي (نقص الصلاحية → فشل واضح ثم استرداد) مُتحقَّق منه يدوياً على
 * MySQL مع binary logging (SQLite لا يعيد إنتاج قيد الصلاحية).
 */
class InstallAuditGuardsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_reports_success_on_non_mysql_engines(): void
    {
        // على محرك الاختبار (SQLite) المشغّلات مُدارة عبر الهجرة — الأمر ينجح ويبلّغ.
        $driver = DB::getDriverName();

        $this->artisan('prosthetics:install-audit-guards')
            ->assertSuccessful();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            // لا شيء إضافي — المهم أنه لا يفشل ولا يمسّ البيانات.
            $this->assertTrue(true);
        }
    }

    public function test_support_helper_constants_are_stable(): void
    {
        $this->assertSame('audit_logs_no_update', AuditLogImmutability::TRIGGER_UPDATE);
        $this->assertSame('audit_logs_no_delete', AuditLogImmutability::TRIGGER_DELETE);
    }

    public function test_command_does_not_touch_audit_data(): void
    {
        \App\Services\AuditService::log(action: 'create', description: 'seed', tag: 'admin');
        $before = \App\Models\AuditLog::count();

        $this->artisan('prosthetics:install-audit-guards')->assertSuccessful();

        $this->assertSame($before, \App\Models\AuditLog::count());
    }
}
