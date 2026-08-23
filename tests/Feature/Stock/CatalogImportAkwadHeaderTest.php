<?php

namespace Tests\Feature\Stock;

use App\Models\StockItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class CatalogImportAkwadHeaderTest extends TestCase
{
    use ProstheticTestHelper;
    use RefreshDatabase;

    public function test_import_maps_akwad_header_without_al_prefix(): void
    {
        $admin = $this->userWithRole('admin');
        $headers = [
            'رقم الصنف',
            'رقم الصفحة',
            'اسم الصنف',
            'أكواد',
            'الماركة',
            'الوحدة',
            'رصيد أول المده',
            'الاضافة',
            'الخصم',
            'الرصيد',
        ];

        $row = ['1', '1/1', 'قدم محور واحد أوتوبوك 1H38', '1H38', 'أوتوبوك', 'عدد', '0', '0', '0', '0'];
        $contents = implode(',', $headers)."\r\n".implode(',', $row)."\r\n";

        $this->actingAs($admin)
            ->post(route('admin.catalog.import'), [
                'file' => UploadedFile::fake()->createWithContent('ottobock.csv', $contents),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('stock_items', [
            'catalog_number' => '1',
            'page_number' => '1/1',
            'name' => 'قدم محور واحد أوتوبوك 1H38',
            'alt_codes' => '1H38',
            'barcode' => StockItem::barcodeForOperationalCode('1H38'),
        ]);
    }

    public function test_import_full_ottobock_style_sheet_without_price_column(): void
    {
        $admin = $this->userWithRole('admin');
        $headers = [
            'رقم الصنف',
            'رقم الصفحة',
            'اسم الصنف',
            'أكواد',
            'الماركة',
            'الوحدة',
            'رصيد أول المده',
            'الاضافة',
            'الخصم',
            'الرصيد',
        ];

        $rows = [
            ['1', '1/1', 'قدم محور واحد أوتوبوك 1H38', '1H38', 'أوتوبوك', 'عدد', '0', '0', '0', '0'],
            ['2', '1/2', 'قدم محور واحد أوتوبوك 1S101', '1S101', 'أوتوبوك', 'عدد', '5', '0', '0', '5'],
            ['3', '1/3', 'ركبة 2R10', '2R10', 'أوتوبوك', 'عدد', '2', '1', '0', '3'],
        ];

        $contents = implode(',', $headers)."\r\n";
        foreach ($rows as $row) {
            $contents .= implode(',', $row)."\r\n";
        }

        $this->actingAs($admin)
            ->post(route('admin.catalog.import'), [
                'file' => UploadedFile::fake()->createWithContent('ottobock-full.csv', $contents),
            ])
            ->assertRedirect();

        $this->assertSame(3, StockItem::query()->count());
        $this->assertDatabaseHas('stock_items', ['alt_codes' => '1S101', 'brand' => 'أوتوبوك']);
        $this->assertDatabaseHas('stock_items', ['alt_codes' => '2R10', 'qty' => 3]);
    }
}
