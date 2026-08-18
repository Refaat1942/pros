<?php

namespace Tests\Feature\Checklist;

use App\Models\Appointment;
use App\Models\CaseRecord;
use App\Models\Permission;
use App\Models\StockItem;
use App\Services\AppointmentService;
use App\Services\MedicalRecordService;
use App\Services\StockImportService;
use App\Support\CatalogColumns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * تغطية ميزات قائمة العميل: الكشف الاختياري، الرفع الجماعي،
 * الربحية العسكرية، الصلاحيات التفصيلية، وطباعة الباركود.
 */
class ChecklistFeaturesTest extends TestCase
{
    use ProstheticTestHelper;
    use RefreshDatabase;

    /** @return list<string> */
    private function catalogHeaders(): array
    {
        return StockImportService::headers();
    }

    public function test_skip_exam_advances_case_to_technical_without_medical_record(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $visitType = $this->defaultVisitType();

        $appointment = app(AppointmentService::class)->book([
            'patient_id' => $patient->id,
            'appointment_date' => now()->toDateString(),
            'visit_type_id' => $visitType->id,
        ]);

        app(AppointmentService::class)->advanceStatus(
            $appointment,
            Appointment::STATUS_IN_CLINIC,
        );

        $doctor = $this->userWithRole('doctor');
        $this->actingAs($doctor);

        $case = app(MedicalRecordService::class)->skipExam($appointment->fresh());

        $this->assertSame(CaseRecord::STAGE_TECHNICAL, $case->stage_key);
        $this->assertSame(Appointment::STATUS_DONE, $appointment->fresh()->status);
        $this->assertDatabaseMissing('medical_records', ['appointment_id' => $appointment->id]);
    }

    public function test_csv_bulk_import_creates_then_updates_items(): void
    {
        $csv = $this->catalogHeaders();
        $contents = implode(',', $csv)."\r\n"
            ."RM-900,10,خامة اختبار,,9001,متر,15,5,0,20\r\n";

        $file = UploadedFile::fake()->createWithContent('items.csv', $contents);

        $summary = app(StockImportService::class)->import($file);

        $this->assertSame(1, $summary['created']);
        $this->assertDatabaseHas('stock_items', [
            'code' => 'RM-900',
            'catalog_number' => 'RM-900',
            'name' => 'خامة اختبار',
            'page_number' => '10',
            'uom' => 'متر',
            'opening_qty' => 15,
            'addition' => 5,
            'discount' => 0,
            'qty' => 20,
        ]);

        $update = implode(',', $csv)."\r\n"."RM-900,11,خامة محدثة,,9001,قطعة,40,2,5,37\r\n";
        $summary2 = app(StockImportService::class)->import(
            UploadedFile::fake()->createWithContent('items2.csv', $update),
        );

        $this->assertSame(1, $summary2['updated']);
        $this->assertSame(0, $summary2['created']);
        $this->assertSame(1, StockItem::where('code', 'RM-900')->count());
        $this->assertSame(37, (int) StockItem::where('code', 'RM-900')->value('qty'));
        $this->assertSame('قطعة', StockItem::where('code', 'RM-900')->value('uom'));
    }

    public function test_csv_import_keeps_duplicate_catalog_numbers_as_separate_items(): void
    {
        $csv = $this->catalogHeaders();
        $contents = implode(',', $csv)."\r\n"
            ."198,1/198,كف متحرك ستيبير,,9001,عدد,0,0,0,0\r\n"
            ."198,3/198,كرنفال 3 مم الوان,,9002,عدد,0,0,104850,0\r\n";

        $file = UploadedFile::fake()->createWithContent('dup.csv', $contents);

        $summary = app(StockImportService::class)->import($file);

        $this->assertSame(2, $summary['created']);
        $this->assertSame(0, $summary['skipped']);
        $this->assertSame(2, StockItem::query()->where('catalog_number', '198')->count());
        $this->assertDatabaseHas('stock_items', [
            'catalog_number' => '198',
            'page_number' => '1/198',
            'name' => 'كف متحرك ستيبير',
        ]);
        $this->assertDatabaseHas('stock_items', [
            'catalog_number' => '198',
            'page_number' => '3/198',
            'name' => 'كرنفال 3 مم الوان',
            'discount' => 104850,
        ]);
    }

    public function test_csv_import_defaults_unit_when_blank(): void
    {
        $contents = "RM-901,,صنف بلا وحدة,,,5,0,0,5\r\n";

        $summary = app(StockImportService::class)->import(
            UploadedFile::fake()->createWithContent('nouom.csv', $contents),
        );

        $this->assertSame(1, $summary['created']);
        $this->assertSame('قطعة', StockItem::where('code', 'RM-901')->value('uom'));
    }

    public function test_csv_import_handles_excel_semicolon_delimiter(): void
    {
        $contents = "كود الصنف;اسم الصنف;الوحدة;الكمية;الحد الأدنى للطلب\r\n"
            ."RM-902;صنف اكسيل;طقم;3;1\r\n";

        $summary = app(StockImportService::class)->import(
            UploadedFile::fake()->createWithContent('excel.csv', $contents),
        );

        $this->assertSame(1, $summary['created']);

        $item = StockItem::where('code', 'RM-902')->firstOrFail();
        $this->assertSame('طقم', $item->uom);
        $this->assertSame(3, (int) $item->qty);
    }

    public function test_csv_import_converts_windows_1256_arabic_to_utf8(): void
    {
        $arabicName = 'مفصل ركبة ميكانيكي';
        $encodedName = iconv('UTF-8', 'CP1256', $arabicName);
        $this->assertNotFalse($encodedName);
        $contents = "RM-903,,{$encodedName},,قطعة,5,0,0,5\r\n";

        $summary = app(StockImportService::class)->import(
            UploadedFile::fake()->createWithContent('win1256.csv', $contents),
        );

        $this->assertSame(1, $summary['created']);
        $this->assertDatabaseHas('stock_items', [
            'code' => 'RM-903',
            'name' => $arabicName,
            'qty' => 5,
        ]);
    }

    public function test_csv_import_prefers_cp1256_over_false_utf8_for_arabic_excel(): void
    {
        $arabicName = 'مفصل ركبه عرفه';
        $encodedName = iconv('UTF-8', 'CP1256', $arabicName);
        $this->assertNotFalse($encodedName);

        $header = iconv('UTF-8', 'CP1256', implode(';', $this->catalogHeaders()));
        $this->assertNotFalse($header);

        $contents = $header."\r\n"
            .'RM-560;;'.$encodedName.';;قطعة;3;0;0;3'."\r\n";

        $summary = app(StockImportService::class)->import(
            UploadedFile::fake()->createWithContent('excel-ar.csv', $contents),
        );

        $this->assertSame(1, $summary['created']);
        $this->assertDatabaseHas('stock_items', [
            'code' => 'RM-560',
            'name' => $arabicName,
        ]);
        $this->assertStringNotContainsString('?', StockItem::where('code', 'RM-560')->value('name'));
    }

    public function test_xlsx_template_has_single_items_sheet_with_eleven_columns_including_brand(): void
    {
        $bytes = app(StockImportService::class)->templateBinary();

        $this->assertStringStartsWith('PK', $bytes);

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'stock_tpl_test_'.uniqid('', true).'.xlsx';
        file_put_contents($path, $bytes);

        $reader = new Reader;
        $reader->open($path);

        $sheetNames = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $sheetNames[] = $sheet->getName();
        }

        $reader->close();
        @unlink($path);

        $this->assertSame([StockImportService::SHEET_ITEMS], $sheetNames);
        $this->assertSame(
            CatalogColumns::templateHeaders(),
            StockImportService::headers(),
        );
        $this->assertContains('الماركة', StockImportService::headers());
        $this->assertCount(11, StockImportService::headers());
    }

    public function test_csv_import_stores_brand_column(): void
    {
        $csv = $this->catalogHeaders();
        $contents = implode(',', $csv)."\r\n"
            ."RM-BRD,20,مفصل ركبة,Ottobock,4821,قطعة,5,0,0,5,120\r\n";

        $summary = app(StockImportService::class)->import(
            UploadedFile::fake()->createWithContent('brand.csv', $contents),
        );

        $this->assertSame(1, $summary['created']);
        $this->assertDatabaseHas('stock_items', [
            'code' => 'RM-BRD',
            'name' => 'مفصل ركبة',
            'brand' => 'Ottobock',
        ]);
    }

    public function test_military_markup_engine_computes_selling_price_and_percentage(): void
    {
        $company = $this->militaryCompany();
        $patient = $this->militaryPatient($company);

        $item = $this->stockItem('RM-MIL', qty: 20, wac: 100.00);
        $item->update(['price' => 300]);

        $case = $this->operationsReadyCase($patient, ['RM-MIL']);

        $this->assertEqualsWithDelta(300.0, (float) $case->military_selling_price, 0.01);
        $this->assertEqualsWithDelta(200.0, (float) $case->military_markup_pct, 0.01);
        $this->assertEqualsWithDelta(100.0, (float) $case->internal_cost, 0.01);
    }

    public function test_role_permission_gate_grants_and_denies(): void
    {
        $permission = Permission::where('slug', 'approve-pricing')->firstOrFail();

        $operations = $this->userWithRole('operations');
        $operations->role->permissions()->sync([$permission->id]);

        $spec = $this->userWithRole('spec');
        $spec->role->permissions()->detach([$permission->id]);

        $this->assertTrue(Gate::forUser($operations->fresh())->allows('approve-pricing'));
        $this->assertFalse(Gate::forUser($spec->fresh())->allows('approve-pricing'));
    }

    public function test_admin_always_passes_every_gate(): void
    {
        $admin = $this->userWithRole('admin');

        $this->assertTrue(Gate::forUser($admin->fresh())->allows('approve-pricing'));
        $this->assertTrue(Gate::forUser($admin->fresh())->allows('view-military-profit'));
    }

    public function test_catalog_store_accepts_core_attributes_only(): void
    {
        $admin = $this->userWithRole('admin');
        $supplier = $this->makeSupplier();

        $response = $this->actingAs($admin)->postJson(route('admin.catalog.store'), [
            'name' => 'صنف مبسّط',
            'opening_qty' => 12,
            'addition' => 0,
            'discount' => 0,
            'balance' => 12,
            'price' => 150,
            'supplier_ids' => [$supplier->id],
            'prices' => [
                ['label' => 'سعر مورد آخر', 'amount' => 175],
            ],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('stock_items', [
            'name' => 'صنف مبسّط',
            'qty' => 12,
            'opening_qty' => 12,
            'price' => 150,
        ]);
        $item = StockItem::where('name', 'صنف مبسّط')->firstOrFail();
        $this->assertSame(1, $item->prices()->count());
        $this->assertEqualsWithDelta(175.0, (float) $item->prices()->value('amount'), 0.01);
    }

    public function test_barcode_labels_view_renders_with_copies(): void
    {
        $admin = $this->userWithRole('admin');

        $item = StockItem::create([
            'code' => 'RM-LBL',
            'name' => 'صنف ملصق',
            'barcode' => 'BC-RM-LBL',
            'qty' => 5,
            'reserved' => 0,
            'wac' => 50,
            'status' => 'ok',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.catalog.labels', $item).'?copies=4');

        $response->assertOk();
        $response->assertSee('BC-RM-LBL');
        $response->assertSee('data:image/svg+xml;base64,', false);
    }

    public function test_bulk_barcode_labels_render_multiple_items_with_settings(): void
    {
        $admin = $this->userWithRole('admin');

        $a = StockItem::create(['code' => 'RM-A', 'name' => 'صنف أ', 'barcode' => 'BC-RM-A', 'qty' => 5, 'reserved' => 0, 'wac' => 10, 'status' => 'ok']);
        $b = StockItem::create(['code' => 'RM-B', 'name' => 'صنف ب', 'barcode' => 'BC-RM-B', 'qty' => 5, 'reserved' => 0, 'wac' => 10, 'status' => 'ok']);

        $response = $this->actingAs($admin)->get(
            route('admin.catalog.labels.bulk').'?ids='.$a->id.','.$b->id.'&copies=2&offset_x=3&page_margin=6',
        );

        $response->assertOk();
        $response->assertSee('BC-RM-A');
        $response->assertSee('BC-RM-B');
        $response->assertSee('4 ملصق');
        $response->assertSee('label_width_mm', false);
        $response->assertSee('margin_left_mm', false);
        $response->assertSee('--offset-x: 3mm', false);
    }
}
