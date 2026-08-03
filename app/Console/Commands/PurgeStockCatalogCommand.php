<?php

namespace App\Console\Commands;

use App\Services\StockCatalogPurgeService;
use Illuminate\Console\Command;

/**
 * مسح كل الأصناف وحركاتها — للاستعداد لرفع/import كatalog جديد.
 */
class PurgeStockCatalogCommand extends Command
{
    protected $signature = 'prosthetics:purge-stock-catalog
                            {--force : تنفيذ بدون تأكيد}';

    protected $description = 'Delete all stock catalog items, prices, and movements — keeps categories and suppliers';

    public function handle(StockCatalogPurgeService $purge): int
    {
        if (! $purge->hasStockItems()) {
            $this->info('لا توجد أصناف في الكatalog للحذف.');

            return self::SUCCESS;
        }

        if ($purge->hasBlockingReferences()) {
            $this->warn('تحذير: فيه حالات أو BOM أو طلبات تسعير مرتبطة بأكواد الأصناف الحالية.');
            $this->line('الأكواد في التوصيف/BOM هتفضل موجودة كنص — الأفضل تمسح بيانات المرضى الأول: prosthetics:purge-patient-data');
        }

        if (! $this->option('force') && ! $this->confirm(
            'سيتم حذف: كل الأصناف — أسعارها — حركات المخزن — ربط الموردين. محفوظ: الفئات — الموردون — المستخدمون — الإعدادات. متابعة؟',
            false
        )) {
            $this->warn('تم الإلغاء.');

            return self::SUCCESS;
        }

        $counts = $purge->purge();

        $this->info('تم مسح كatalog الأصناف.');
        $this->table(
            ['الجدول', 'عدد المحذوف'],
            collect($counts)->map(fn (int $count, string $key) => [$key, $count])->values()->all(),
        );

        $this->newLine();
        $this->line('✅ جاهز لرفع/import الأصناف الجديدة من صفحة الأصناف والأسعار.');

        return self::SUCCESS;
    }
}
