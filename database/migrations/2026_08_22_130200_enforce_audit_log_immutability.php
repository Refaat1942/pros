<?php

use App\Support\AuditLogImmutability;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * C-5: فرض عدم قابلية تعديل/حذف سجل الرقابة على مستوى قاعدة البيانات.
 *
 * الحماية على مستوى النموذج (AuditLog::save/delete) يمكن تجاوزها عبر DB::table()
 * أو SQL خام. هذه الهجرة تُثبّت مشغّلات (triggers) تمنع UPDATE و DELETE على
 * audit_logs مباشرة في المحرك — «سجل الرقابة دليل قانوني: إضافة فقط».
 *
 * التوافق عبر المحركات:
 *   - PostgreSQL (VPS/LAN): دالة + مشغّلان BEFORE UPDATE/DELETE يرفعان استثناء.
 *   - SQLite (اختبارات): مشغّلان BEFORE UPDATE/DELETE مع RAISE(ABORT).
 *   - MySQL/MariaDB: مشغّلان BEFORE UPDATE/DELETE مع SIGNAL SQLSTATE.
 *
 * MySQL + binary logging: إنشاء المشغّلات يتطلب صلاحية (SUPER أو
 * log_bin_trust_function_creators=1). لا نمنح SUPER لمستخدم التطبيق. إن تعذّر
 * الإنشاء، تَفشل الهجرة **بوضوح** (لا نجاح صامت) مع تعليمات تشغيل أمر الـ DBA:
 *     php artisan prosthetics:install-audit-guards
 * بعد تشغيله بحساب مخوّل، أعد تشغيل الهجرة — ستتحقّق من وجود المشغّلَين وتنجح.
 *
 * الإدراج (INSERT) يبقى مسموحاً — الجدول append-only. الحماية التطبيقية
 * (AuditLog model) تبقى قائمة دائماً بصرف النظر عن مشغّلات قاعدة البيانات.
 *
 * المنطق الفعلي في App\Support\AuditLogImmutability لإعادة استخدامه من أمر الـ DBA.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->installMysqlOrFailClearly();

            return;
        }

        match ($driver) {
            'pgsql' => $this->createPostgres(),
            'sqlite' => $this->createSqlite(),
            default => null,
        };
    }

    public function down(): void
    {
        match (DB::getDriverName()) {
            'pgsql' => $this->dropPostgres(),
            'sqlite' => $this->dropSqlite(),
            'mysql', 'mariadb' => $this->dropMysql(),
            default => null,
        };
    }

    /**
     * MySQL/MariaDB: يُثبّت المشغّلات، ثم يتحقّق فعلياً من وجودهما. إن تعذّر الإنشاء
     * (نقص صلاحية / binlog) تَفشل الهجرة بخطأ واضح بدل النجاح الصامت.
     */
    private function installMysqlOrFailClearly(): void
    {
        // إن كانت المشغّلات مثبّتة مسبقاً (مثلاً عبر أمر الـ DBA) — الهجرة تنجح idempotently.
        if (AuditLogImmutability::mysqlTriggersExist()) {
            return;
        }

        try {
            AuditLogImmutability::installMysqlTriggers();
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "تعذّر إنشاء مشغّلات حماية سجل الرقابة على MySQL/MariaDB — على الأرجح نقص صلاحية ".
                "(SUPER غير مطلوب) مع تفعيل binary logging.\n".
                "لا تُمنح SUPER لمستخدم التطبيق. بدلاً من ذلك شغّل بحساب DBA/root مخوّل:\n".
                "    php artisan prosthetics:install-audit-guards\n".
                "ثم أعد تشغيل الهجرات (php artisan migrate --force).\n".
                "الخطأ الأصلي: ".$e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }

        // تحقّق صريح: يجب أن يوجد المشغّلان فعلاً بعد الإنشاء، وإلا نَفشل.
        if (! AuditLogImmutability::mysqlTriggersExist()) {
            throw new \RuntimeException(
                'فشل التحقّق: مشغّلات حماية سجل الرقابة غير موجودة بعد محاولة الإنشاء على MySQL/MariaDB. '.
                'شغّل: php artisan prosthetics:install-audit-guards بحساب مخوّل ثم أعد الهجرة.'
            );
        }
    }

    // ── PostgreSQL ──────────────────────────────────────────────────────────
    private function createPostgres(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit_logs_prevent_mutation()
            RETURNS TRIGGER AS $$
            BEGIN
                RAISE EXCEPTION 'audit_logs is append-only: % is not permitted', TG_OP
                    USING ERRCODE = 'check_violation';
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update ON audit_logs;');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_delete ON audit_logs;');

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER audit_logs_no_update
            BEFORE UPDATE ON audit_logs
            FOR EACH ROW EXECUTE FUNCTION audit_logs_prevent_mutation();
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER audit_logs_no_delete
            BEFORE DELETE ON audit_logs
            FOR EACH ROW EXECUTE FUNCTION audit_logs_prevent_mutation();
        SQL);
    }

    private function dropPostgres(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update ON audit_logs;');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_delete ON audit_logs;');
        DB::unprepared('DROP FUNCTION IF EXISTS audit_logs_prevent_mutation();');
    }

    // ── SQLite ──────────────────────────────────────────────────────────────
    private function createSqlite(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update;');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_delete;');

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER audit_logs_no_update
            BEFORE UPDATE ON audit_logs
            BEGIN
                SELECT RAISE(ABORT, 'audit_logs is append-only: UPDATE is not permitted');
            END;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER audit_logs_no_delete
            BEFORE DELETE ON audit_logs
            BEGIN
                SELECT RAISE(ABORT, 'audit_logs is append-only: DELETE is not permitted');
            END;
        SQL);
    }

    private function dropSqlite(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update;');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_delete;');
    }

    // ── MySQL / MariaDB ─────────────────────────────────────────────────────
    // منطق الإنشاء/التحقّق في App\Support\AuditLogImmutability (يُعاد استخدامه من
    // أمر الـ DBA). هنا نستدعي إزالة المشغّلات فقط عند التراجع.
    private function dropMysql(): void
    {
        AuditLogImmutability::dropMysqlTriggers();
    }
};
