<?php

namespace Tests\Feature\Stock;

use App\Models\StockItem;
use App\Services\StockImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * الرفع الجماعي = دمج: لا حذف، لا مسح بخلايا فارغة، لا كتابة فوق الرصيد إلا لو مكتوب.
 */
class CatalogImportMergeTest extends TestCase
{
    use ProstheticTestHelper;
    use RefreshDatabase;

    private const HEADERS = ['رقم الصنف', 'رقم الصفحة', 'اسم الصنف', 'أكواد', 'الماركة', 'الوحدة', 'رصيد أول المده', 'الاضافة', 'الخصم', 'الرصيد', 'السعر'];

    public function test_existing_item_keeps_values_for_empty_cells_and_live_balance(): void
    {
        $item = $this->importCsv([
            ['1', '1/1', 'قدم أوتوبوك', '1H38', 'أوتوبوك', 'عدد', '10', '0', '0', '10', '500'],
        ]);
        $this->assertSame(1, $item['created']);

        // صرف من المخزون بعد الرفع الأول — الرصيد الحي صار 7.
        StockItem::query()->where('alt_codes', '1H38')->update(['qty' => 7]);

        // رفع ثانٍ: ماركة فارغة، رصيد فارغ، سعر جديد.
        $summary = $this->importCsv([
            ['1', '1/1', 'قدم أوتوبوك', '1H38', '', '', '', '', '', '', '650'],
        ]);

        $this->assertSame(['created' => 0, 'updated' => 1], array_intersect_key($summary, ['created' => 0, 'updated' => 0]));

        $fresh = StockItem::query()->where('alt_codes', '1H38')->firstOrFail();
        $this->assertSame('أوتوبوك', $fresh->brand);
        $this->assertSame(7, (int) $fresh->qty);
        $this->assertEqualsWithDelta(650, (float) $fresh->price, 0.01);
    }

    public function test_items_missing_from_new_file_are_not_deleted_and_same_file_is_unchanged(): void
    {
        $this->importCsv([
            ['1', '1/1', 'قدم', 'A1', 'X', 'عدد', '5', '0', '0', '5', '10'],
            ['2', '1/2', 'ركبة', 'B2', 'X', 'عدد', '3', '0', '0', '3', '20'],
        ]);

        $summary = $this->importCsv([
            ['1', '1/1', 'قدم', 'A1', 'X', 'عدد', '5', '0', '0', '5', '10'],
        ]);

        $this->assertSame(2, StockItem::query()->count());
        $this->assertSame(1, $summary['unchanged']);
        $this->assertSame(0, $summary['updated']);
    }

    public function test_decimal_balance_is_kept_and_page_only_rows_are_not_merged(): void
    {
        $this->importCsv([
            ['', '7', 'قماش ستوكينيت', '', 'X', 'متر', '', '', '', '2.5', ''],
            ['', '7', 'شريط لاصق', '', 'X', 'متر', '', '', '', '4', ''],
        ]);

        // نفس رقم الصفحة بدون رقم صنف/كود ← صنفان منفصلان وليس صنفاً واحداً.
        $this->assertSame(2, StockItem::query()->where('page_number', '7')->count());
        $this->assertEqualsWithDelta(2.5, (float) StockItem::query()->where('name', 'قماش ستوكينيت')->value('qty'), 0.0001);
    }

    public function test_exported_catalog_reimports_without_changing_decimal_balances(): void
    {
        $this->importCsv([
            ['1', '1/1', 'قماش', 'D1', 'X', 'متر', '', '', '', '2.5', '10'],
        ]);

        $service = app(StockImportService::class);
        $xlsx = $service->exportBinary(app(\App\Services\StockCatalogService::class)->listForExport(null, null));

        $path = tempnam(sys_get_temp_dir(), 'exp').'.xlsx';
        file_put_contents($path, $xlsx);
        $summary = $service->import(new UploadedFile($path, 'export.xlsx', null, null, true));
        @unlink($path);

        $this->assertSame(0, $summary['updated']);
        $this->assertSame(1, $summary['unchanged']);
        $this->assertEqualsWithDelta(2.5, (float) StockItem::query()->where('alt_codes', 'D1')->value('qty'), 0.0001);
    }

    public function test_csv_quoted_cells_with_commas_and_newlines_are_read(): void
    {
        $contents = implode(',', self::HEADERS)."\r\n"
            ."1,1/1,\"سوكيت، كربون\nمستورد\",S1,X,عدد,1,0,0,1,0\r\n"
            ."2,1/2,ركبة,K2,X,عدد,1,0,0,1,0\r\n";

        $this->actingAs($this->userWithRole('admin'))->post(route('admin.catalog.import'), [
            'file' => UploadedFile::fake()->createWithContent('quoted.csv', $contents),
        ])->assertRedirect();

        $this->assertSame(2, StockItem::query()->count());
        $this->assertDatabaseHas('stock_items', ['alt_codes' => 'K2']);
    }

    public function test_every_sheet_of_the_workbook_is_imported(): void
    {
        $this->actingAs($this->userWithRole('admin'));

        $path = tempnam(sys_get_temp_dir(), 'multi').'.xlsx';
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName(StockImportService::SHEET_ITEMS);
        $writer->addRow(Row::fromValues(self::HEADERS));
        $writer->addRow(Row::fromValues(['1', '1/1', 'قدم', 'A1', 'X', 'عدد', '1', '0', '0', '1', '0']));
        $writer->addNewSheetAndMakeItCurrent()->setName('أوتوبوك');
        $writer->addRow(Row::fromValues(self::HEADERS));
        $writer->addRow(Row::fromValues(['2', '2/1', 'ركبة', 'B2', 'X', 'عدد', '1', '0', '0', '1', '0']));
        $writer->addRow(Row::fromValues(['3', '2/2', 'سوكيت', 'C3', 'X', 'عدد', '1', '0', '0', '1', '0']));
        $writer->addNewSheetAndMakeItCurrent()->setName('ملخص');
        $writer->addRow(Row::fromValues(['إجمالي', '3']));
        $writer->close();

        $file = new UploadedFile($path, 'items.xlsx', null, null, true);
        $summary = app(StockImportService::class)->import($file);
        @unlink($path);

        $this->assertSame(3, $summary['created']);
        $this->assertSame(3, StockItem::query()->count());
    }

    /**
     * @param  list<list<string>>  $rows
     * @return array<string, mixed>
     */
    private function importCsv(array $rows): array
    {
        $this->actingAs($this->userWithRole('admin'));

        $contents = implode(',', self::HEADERS)."\r\n";
        foreach ($rows as $row) {
            $contents .= implode(',', $row)."\r\n";
        }

        $path = tempnam(sys_get_temp_dir(), 'imp');
        file_put_contents($path, $contents);

        return app(StockImportService::class)->import(new UploadedFile($path, 'items.csv', 'text/csv', null, true));
    }
}
