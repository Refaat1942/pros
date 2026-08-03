<?php

namespace App\Console\Commands;

use App\Services\StockCatalogService;
use Illuminate\Console\Command;

/**
 * مزامنة حقول الكتالوج (أول/إضافة/خصم) مع رصيد المخزن للأصناف بلا حركات.
 */
class SyncCatalogLedgerCommand extends Command
{
    protected $signature = 'prosthetics:sync-catalog-ledger';

    protected $description = 'Align catalog opening/addition/discount with warehouse qty for items without stock movements';

    public function handle(StockCatalogService $catalog): int
    {
        $result = $catalog->reconcileCatalogLedgerFromWarehouse();

        $this->info("تمت المزامنة: {$result['synced']} صنف.");
        $this->line("تُخطّى (حركات موجودة أو متطابق مسبقاً): {$result['skipped']}.");

        return self::SUCCESS;
    }
}
