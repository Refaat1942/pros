<?php

namespace Tests\Feature\Stock;

use App\Support\CatalogImportValidator;
use Illuminate\Http\UploadedFile;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class CatalogImportValidationTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_xlsx_with_zip_signature_passes_extension_validation(): void
    {
        $contents = 'PK'."\x03\x04".str_repeat("\0", 64);
        $file = UploadedFile::fake()->createWithContent('items.xlsx', $contents);

        $this->assertTrue(CatalogImportValidator::isAllowed($file));
    }

    public function test_renamed_non_xlsx_file_is_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent('items.xlsx', 'not-a-spreadsheet');

        $this->assertFalse(CatalogImportValidator::isAllowed($file));
    }

    public function test_catalog_import_accepts_xlsx_without_strict_mime_rule(): void
    {
        $admin = $this->userWithRole('admin');
        $contents = 'PK'."\x03\x04".str_repeat("\0", 64);
        $file = UploadedFile::fake()->createWithContent('items.xlsx', $contents);

        $this->actingAs($admin)
            ->post(route('admin.catalog.import'), ['file' => $file])
            ->assertSessionDoesntHaveErrors('file');
    }

    public function test_catalog_import_rejects_wrong_extension(): void
    {
        $admin = $this->userWithRole('admin');
        $file = UploadedFile::fake()->createWithContent('items.pdf', '%PDF-1.4');

        $this->actingAs($admin)
            ->from(route('admin.catalog'))
            ->post(route('admin.catalog.import'), ['file' => $file])
            ->assertSessionHasErrors('file');
    }
}
