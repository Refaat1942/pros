<?php

namespace Tests\Feature\Finance;

use App\Models\ContractCompany;
use App\Models\ContractCompanyDebt;
use App\Services\ContractCompanyImportService;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class ContractCompanyImportTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_admin_can_download_companies_template(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.companies.template'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_admin_can_export_companies_to_excel(): void
    {
        $admin = $this->userWithRole('admin');
        $this->civilianCompany('مصر للتأمين');

        $this->actingAs($admin)
            ->get(route('admin.companies.export'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_import_creates_and_updates_companies_from_xlsx(): void
    {
        $admin = $this->userWithRole('admin');
        $existing = $this->civilianCompany('التأمين الصحي');
        $existing->update(['discount_percent' => 5]);

        $file = $this->buildCompaniesXlsx([
            ['صندوق إعاقة', 'متعاقدة', '20'],
            ['التأمين الصحي', 'متعاقدة', '15'],
            ['شركة خاصة', 'غير متعاقدة', '0'],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.companies.import'), ['file' => $file])
            ->assertRedirect(route('admin.companies'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('contract_companies', [
            'name' => 'صندوق إعاقة',
            'is_contracted' => true,
            'discount_percent' => 20,
        ]);

        $this->assertSame('15.00', $existing->fresh()->discount_percent);

        $nonContracted = ContractCompany::where('name', 'شركة خاصة')->first();
        $this->assertNotNull($nonContracted);
        $this->assertFalse($nonContracted->is_contracted);
        $this->assertSame('0.00', $nonContracted->discount_percent);

        $debt = ContractCompanyDebt::where('contract_company_id', $existing->fresh()->id)->first();
        $this->assertNotNull($debt);
    }

    public function test_import_via_json_returns_summary(): void
    {
        $admin = $this->userWithRole('admin');
        $file = $this->buildCompaniesXlsx([
            ['جهة JSON', 'متعاقدة', '10'],
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.companies.import'), ['file' => $file])
            ->assertOk()
            ->assertJsonPath('summary.created', 1);

        $this->assertDatabaseHas('contract_companies', ['name' => 'جهة JSON']);
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function buildCompaniesXlsx(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'companies_import_').'.xlsx';
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('جهات التعاقد');
        $writer->addRow(Row::fromValues(['اسم الجهة', 'نوع الهيئة', 'نسبة الخصم %']));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return new UploadedFile($path, 'companies.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
