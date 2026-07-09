<?php

namespace App\Console\Commands;

use App\Services\PatientDataPurgeService;
use Illuminate\Console\Command;

/**
 * مسح كل ما له علاقة بالمرضى — للتجربة على بيئة نظيفة.
 */
class PurgePatientDataCommand extends Command
{
    protected $signature = 'prosthetics:purge-patient-data
                            {--force : تنفيذ بدون تأكيد}
                            {--keep-debts : الإبقاء على مديونيات الجهات المتعاقدة}
                            {--skip-stock-sync : عدم إعادة مزامنة كميات المخزن بعد حذف الصرف}';

    protected $description = 'Delete all patient-related data (patients, cases, appointments, quotes, payments) — keeps system core';

    public function handle(PatientDataPurgeService $purge): int
    {
        if (! $purge->hasPatientRelatedData()) {
            $this->info('لا توجد بيانات مرتبطة بالمرضى للحذف.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm(
            'سيتم حذف: المرضى — الحالات — المواعيد — الكشوف — العروض — المدفوعات — BOM — إشعارات المسار. الكور (مستخدمون، مخزن، إعدادات) يبقى. متابعة؟',
            false
        )) {
            $this->warn('تم الإلغاء.');

            return self::SUCCESS;
        }

        $counts = $purge->purge(
            resetContractDebts: ! $this->option('keep-debts'),
            syncStock: ! $this->option('skip-stock-sync'),
        );

        $this->info('تم مسح كل ما له علاقة بالمرضى.');
        $this->table(
            ['الجدول / الإجراء', 'العدد'],
            collect($counts)->map(fn (int $count, string $key) => [$key, $count])->values()->all(),
        );

        $this->newLine();
        $this->line('✅ محفوظ: المستخدمون — الأدوار — المخزن والأصناف — الموردون — الجهات — الرتب — إعدادات المسار والتكاليف');

        return self::SUCCESS;
    }
}
