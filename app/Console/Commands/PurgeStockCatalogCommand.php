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
            $this->warn('تحذير: توجد حالات أو قوائم مواد أو طلبات تسعير مرتبطة بأكواد الأصناف الحالية.');
            $this->line('ستبقى الأكواد في التوصيف وقوائم المواد كنص — يُفضَّل مسح بيانات المرضى أولاً: prosthetics:purge-patient-data');
        }

        if (! $this->option('force') && ! $this->confirm(
            'سيتم حذف: كل الأصناف — أسعارها — حركات المخزن — طلبات التوريد — ربط الموردين. محفوظ: الفئات — الموردون — المستخدمون — الإعدادات. متابعة؟',
            false
        )) {
            $this->warn('تم الإلغاء.');

            return self::SUCCESS;
        }

        $counts = $purge->purge();

        $this->info('تم مسح كتالوج الأصناف.');
        $this->table(
            ['الجدول', 'عدد المحذوف'],
            collect($counts)->map(fn (int $count, string $key) => [$key, $count])->values()->all(),
        );

        $this->newLine();
        $this->line('✅ جاهز لرفع/import الأصناف الجديدة من صفحة الأصناف والأسعار.');
        $this->line('💡 لمسح الحالات والأصناف معاً: php artisan prosthetics:reset-offline --force');

        return self::SUCCESS;
    }
}
