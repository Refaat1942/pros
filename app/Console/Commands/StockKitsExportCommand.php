<?php

namespace App\Console\Commands;

use App\Services\StockKitTransferService;
use Illuminate\Console\Command;

/** تصدير «عمل المجموعات» لملف JSON — يُنقل للسيرفر الأوفلاين ويُستورد هناك. */
class StockKitsExportCommand extends Command
{
    protected $signature = 'prosthetics:kits-export {path? : مسار الملف (افتراضي storage/app/stock-kits-التاريخ.json)}';

    protected $description = 'Export all stock kits (with item codes) to a JSON file for another server';

    public function handle(StockKitTransferService $transfer): int
    {
        $path = $this->argument('path') ?: storage_path('app/stock-kits-'.now()->format('Y-m-d_His').'.json');
        $data = $transfer->export();

        file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->info(sprintf('تم تصدير %d مجموعة → %s', count($data['kits']), $path));

        return self::SUCCESS;
    }
}
