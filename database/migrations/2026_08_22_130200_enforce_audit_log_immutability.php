<?php

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
 *   - MySQL: مشغّلان BEFORE UPDATE/DELETE مع SIGNAL SQLSTATE.
 *
 * الإدراج (INSERT) يبقى مسموحاً — الجدول append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        match (DB::getDriverName()) {
            'pgsql' => $this->createPostgres(),
            'sqlite' => $this->createSqlite(),
            'mysql', 'mariadb' => $this->createMysql(),
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
    private function createMysql(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update;');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_delete;');

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER audit_logs_no_update
            BEFORE UPDATE ON audit_logs
            FOR EACH ROW
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'audit_logs is append-only: UPDATE is not permitted';
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER audit_logs_no_delete
            BEFORE DELETE ON audit_logs
            FOR EACH ROW
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'audit_logs is append-only: DELETE is not permitted';
        SQL);
    }

    private function dropMysql(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update;');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_delete;');
    }
};
