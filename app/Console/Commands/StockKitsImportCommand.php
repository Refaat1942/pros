<?php

namespace App\Console\Commands;

use App\Services\StockKitTransferService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/** استيراد ملف «عمل المجموعات» المصدَّر من سيرفر آخر — الأصناف تُربط بالكود. */
class StockKitsImportCommand extends Command
{
    protected $signature = 'prosthetics:kits-import {path : ملف JSON المصدَّر بأمر prosthetics:kits-export}';

    protected $description = 'Import stock kits from a JSON file exported on another server';

    public function handle(StockKitTransferService $transfer): int
    {
        $path = (string) $this->argument('path');
        if (! is_file($path)) {
            $this->error("الملف غير موجود: {$path}");

            return self::FAILURE;
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            $this->error('الملف ليس JSON صالح.');

            return self::FAILURE;
        }

        try {
            $report = $transfer->import($data);
        } catch (ValidationException $e) {
            $this->error(collect($e->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }

        $this->info($transfer->summary($report));
        foreach ($report['missing_items'] as $missing) {
            $this->warn("  • {$missing['kit']}: الصنف {$missing['item_code']} ({$missing['name']}) غير موجود هنا");
        }
        foreach ($report['skipped'] as $skip) {
            $this->warn("  • اتخطّت {$skip['code']}: {$skip['reason']}");
        }

        return self::SUCCESS;
    }
}
