<?php

namespace App\Console\Commands;

use App\Support\AuditLogImmutability;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * C-5 (DBA): تثبيت مشغّلات حماية سجل الرقابة على MySQL/MariaDB بحساب مخوّل.
 *
 * لماذا أمر منفصل؟ على MySQL مع binary logging، إنشاء المشغّلات يتطلب صلاحية
 * (SUPER أو log_bin_trust_function_creators=1). لا نمنح SUPER لمستخدم التطبيق،
 * لذا يشغّل الـ DBA/root هذا الأمر مرة واحدة بحساب مخوّل، ثم تنجح الهجرة idempotently.
 *
 * idempotent: إن كانت المشغّلات موجودة يبلّغ ولا يفعل شيئاً.
 * لا يمسّ أي بيانات — يُنشئ المشغّلات فقط، ويتحقّق فعلياً من وجود UPDATE و DELETE.
 * أوفلاين بالكامل — لا اتصال بالإنترنت.
 *
 * PostgreSQL/SQLite: المشغّلات تُثبَّت تلقائياً عبر الهجرة (لا تحتاج صلاحيات خاصة)،
 * لذا الأمر يبلّغ فقط بحالتها دون فعل إضافي.
 */
class InstallAuditGuardsCommand extends Command
{
    protected $signature = 'prosthetics:install-audit-guards';

    protected $description = 'تثبيت مشغّلات حماية سجل الرقابة (append-only) على قاعدة البيانات — للـ DBA/root';

    public function handle(): int
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->info("المحرك الحالي: {$driver} — مشغّلات سجل الرقابة تُدار عبر الهجرة (لا حاجة لهذا الأمر).");

            return self::SUCCESS;
        }

        if (AuditLogImmutability::mysqlTriggersExist()) {
            $this->info('✅ مشغّلات حماية سجل الرقابة موجودة بالفعل — لا حاجة لأي إجراء.');

            return self::SUCCESS;
        }

        $this->line('تثبيت مشغّلات حماية سجل الرقابة على MySQL/MariaDB…');

        try {
            AuditLogImmutability::installMysqlTriggers();
        } catch (\Throwable $e) {
            $this->error('❌ تعذّر إنشاء المشغّلات — على الأرجح نقص صلاحية مع تفعيل binary logging.');
            $this->line('شغّل هذا الأمر بحساب DBA/root مخوّل. الخيارات (دون منح SUPER لمستخدم التطبيق):');
            $this->line('  • فعّل مؤقتاً: SET GLOBAL log_bin_trust_function_creators = 1;  ثم أعد المحاولة.');
            $this->line('  • أو نفّذ عبارات CREATE TRIGGER يدوياً بحساب مخوّل.');
            $this->line('الخطأ الأصلي: '.$e->getMessage());

            return self::FAILURE;
        }

        // تحقّق صريح: يجب أن يوجد المشغّلان فعلاً — وإلا نُبلّغ بالفشل.
        if (! AuditLogImmutability::mysqlTriggersExist()) {
            $this->error('❌ فشل التحقّق: المشغّلان غير موجودين بعد الإنشاء.');

            return self::FAILURE;
        }

        $this->info('✅ تم تثبيت مشغّلي حماية سجل الرقابة (UPDATE + DELETE) والتحقّق من وجودهما.');
        $this->line('يمكنك الآن تشغيل: php artisan migrate --force (ستنجح هجرة الحماية idempotently).');

        return self::SUCCESS;
    }
}
