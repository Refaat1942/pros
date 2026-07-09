<?php

namespace App\Console\Commands;

use App\Services\PatientDataPurgeService;
use Illuminate\Console\Command;

/**
 * حذف كل بيانات المرضى والحالات — للتجربة على بيئة نظيفة.
 */
class PurgePatientDataCommand extends Command
{
    protected $signature = 'prosthetics:purge-patient-data
                            {--force : تنفيذ بدون تأكيد}
                            {--keep-debts : الإبقاء على مديونيات الجهات المتعاقدة}
                            {--skip-stock-sync : عدم إعادة مزامنة كميات المخزن بعد حذف الصرف}';

    protected $description = 'Delete all patients, cases, appointments, and related pipeline data (keeps users, settings, catalog)';

    public function handle(PatientDataPurgeService $purge): int
    {
        if (! $purge->hasPatientData()) {
            $this->info('لا توجد بيانات مرضى للحذف.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('سيتم حذف كل المرضى والحالات والمواعيد والعروض والمدفوعات. هل أنت متأكد؟', false)) {
            $this->warn('تم الإلغاء.');

            return self::SUCCESS;
        }

        $counts = $purge->purge(
            resetContractDebts: ! $this->option('keep-debts'),
            syncStock: ! $this->option('skip-stock-sync'),
        );

        $this->info('تم مسح بيانات المرضى.');
        $this->table(
            ['الجدول / الإجراء', 'العدد'],
            collect($counts)->map(fn (int $count, string $key) => [$key, $count])->values()->all(),
        );

        $this->newLine();
        $this->line('✅ محفوظ: المستخدمون — الأدوار — المخزن — الموردون — الجهات — إعدادات المسار والتكاليف');

        return self::SUCCESS;
    }
}
