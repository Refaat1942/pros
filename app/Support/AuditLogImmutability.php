<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * C-5: منطق مشغّلات عدم قابلية تعديل/حذف سجل الرقابة على MySQL/MariaDB.
 *
 * مُجمّع في مكان واحد ليُعاد استخدامه من:
 *   - الهجرة 2026_08_22_130200_enforce_audit_log_immutability
 *   - أمر الـ DBA prosthetics:install-audit-guards
 *
 * لا يمسّ أي بيانات — يُنشئ/يتحقّق/يُزيل المشغّلات فقط. الإدراج يبقى مسموحاً.
 * PostgreSQL و SQLite يُدارَان داخل الهجرة مباشرةً (لا يحتاجان صلاحيات خاصة).
 */
final class AuditLogImmutability
{
    public const TRIGGER_UPDATE = 'audit_logs_no_update';

    public const TRIGGER_DELETE = 'audit_logs_no_delete';

    /**
     * يُنشئ مشغّلي MySQL/MariaDB (قد يرمي استثناءً عند نقص الصلاحية — يتعامل معه المنادي).
     */
    public static function installMysqlTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS '.self::TRIGGER_UPDATE.';');
        DB::unprepared('DROP TRIGGER IF EXISTS '.self::TRIGGER_DELETE.';');

        DB::unprepared(
            'CREATE TRIGGER '.self::TRIGGER_UPDATE.' BEFORE UPDATE ON audit_logs '.
            "FOR EACH ROW SIGNAL SQLSTATE '45000' ".
            "SET MESSAGE_TEXT = 'audit_logs is append-only: UPDATE is not permitted';"
        );

        DB::unprepared(
            'CREATE TRIGGER '.self::TRIGGER_DELETE.' BEFORE DELETE ON audit_logs '.
            "FOR EACH ROW SIGNAL SQLSTATE '45000' ".
            "SET MESSAGE_TEXT = 'audit_logs is append-only: DELETE is not permitted';"
        );
    }

    public static function dropMysqlTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS '.self::TRIGGER_UPDATE.';');
        DB::unprepared('DROP TRIGGER IF EXISTS '.self::TRIGGER_DELETE.';');
    }

    /**
     * هل يوجد المشغّلان فعلاً على MySQL/MariaDB؟ (تحقّق حقيقي من information_schema).
     */
    public static function mysqlTriggersExist(): bool
    {
        $names = self::existingMysqlTriggerNames();

        return in_array(self::TRIGGER_UPDATE, $names, true)
            && in_array(self::TRIGGER_DELETE, $names, true);
    }

    /**
     * @return list<string> أسماء مشغّلات audit_logs الموجودة في قاعدة البيانات الحالية.
     */
    public static function existingMysqlTriggerNames(): array
    {
        $rows = DB::select(
            'SELECT TRIGGER_NAME AS name FROM information_schema.TRIGGERS '.
            'WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE = ?',
            ['audit_logs']
        );

        return array_map(static fn ($r) => (string) ($r->name ?? ''), $rows);
    }
}
