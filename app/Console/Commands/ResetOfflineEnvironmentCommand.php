<?php

namespace App\Console\Commands;

use App\Services\OfflineEnvironmentResetService;
use Illuminate\Console\Command;

/**
 * أمر واحد لمسح بيانات التشغيل على بيئة أوفلاين — حالات + أصناف.
 */
class ResetOfflineEnvironmentCommand extends Command
{
    protected $signature = 'prosthetics:reset-offline
                            {--force : تنفيذ بدون تأكيد}
                            {--keep-debts : الإبقاء على مديونيات الجهات المتعاقدة}';

    protected $description = 'Wipe patient cases and stock catalog for a clean offline client start';

    public function handle(OfflineEnvironmentResetService $reset): int
    {
        if (! $reset->hasDataToReset()) {
            $this->info('لا توجد حالات مسجّلة ولا أصناف — البيئة نظيفة بالفعل.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm(
            'سيتم حذف: المرضى — الحالات — المواعيد — BOM — الأصناف — حركات المخزن — طلبات التوريد. '
            .'يبقى: المستخدمون — الموردون — الأقسام — الإعدادات — سجل الرقابة. متابعة؟',
            false,
        )) {
            $this->warn('تم الإلغاء.');

            return self::SUCCESS;
        }

        $counts = $reset->reset(resetContractDebts: ! $this->option('keep-debts'));

        $this->info('تم مسح بيانات التشغيل — البيئة جاهزة لبداية العميل.');
        $this->table(
            ['الجدول / الإجراء', 'العدد'],
            collect($counts)->map(fn (int $count, string $key) => [$key, $count])->values()->all(),
        );
        $this->newLine();
        $this->line('✅ يمكنك الآن رفع الأصناف من Excel ثم بدء تسجيل المرضى من جديد.');

        return self::SUCCESS;
    }
}
