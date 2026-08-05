<?php

namespace App\Console\Commands;

use App\Services\StockCatalogService;
use Illuminate\Console\Command;

class BackfillOperationalCodesCommand extends Command
{
    protected $signature = 'prosthetics:backfill-operational-codes';

    protected $description = 'Report items missing operational codes (codes must be uploaded via Excel)';

    public function handle(StockCatalogService $catalog): int
    {
        $missing = $catalog->countMissingOperationalCodes();

        if ($missing === 0) {
            $this->info('جميع الأصناف لديها أكواد تشغيلية.');

            return self::SUCCESS;
        }

        $this->warn("{$missing} صنفاً بلا كود تشغيلي.");
        $this->line('لا يُولَّد كود تلقائياً — حمّل قالب Excel، املأ عمود «الأكواد»، ثم ارفع الملف من صفحة الكتالوج.');

        return self::SUCCESS;
    }
}
