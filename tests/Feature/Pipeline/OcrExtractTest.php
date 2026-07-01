<?php

namespace Tests\Feature\Pipeline;

use App\Models\Quote;
use App\Services\OcrLetterExtractionService;
use Illuminate\Http\UploadedFile;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class OcrExtractTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_extract_endpoint_parses_embedded_arabic_from_uploaded_pdf(): void
    {
        $item = $this->stockItem('RM-001', qty: 10, wac: 100.00);
        app(\App\Services\StockPriceService::class)->addBatch(
            $item, 10, 200.00, $this->makeSupplier(), 'INV-001', now()
        );

        $patient = $this->civilianPatient($this->civilianCompany());
        $case    = $this->operationsReadyCase($patient);
        $ops     = $this->userWithRole('operations');

        $this->actingAs($ops)
            ->postJson("/operations/pending/{$case->id}/release-quote")
            ->assertOk();

        $quote = Quote::where('case_id', $case->id)->firstOrFail();
        $recep = $this->userWithRole('reception');

        $pdfBody = '%PDF-1.4' . "\n" .
            'اسم المريض: ' . $patient->name . "\n" .
            'المبلغ: ' . number_format((float) $quote->total, 0, '.', ',') . ' جنيه' . "\n" .
            'خطاب رقم (445/2026)' . "\n" .
            'التاريخ: 24/06/2026';

        $file = UploadedFile::fake()->createWithContent('letter.pdf', $pdfBody);

        $response = $this->actingAs($recep)
            ->postJson('/reception/ocr/extract', [
                'quote_no'    => $quote->quote_no,
                'letter_file' => $file,
            ])
            ->assertOk()
            ->assertJsonStructure([
                'stored_path',
                'extracted' => ['patient_name', 'approved_amount', 'company_name', 'letter_ref', 'letter_date'],
            ]);

        $this->assertSame($patient->name, $response->json('extracted.patient_name'));
        $this->assertEquals((float) $quote->total, (float) $response->json('extracted.approved_amount'));
        $this->assertSame('445/2026', $response->json('extracted.letter_ref'));
        $this->assertSame('2026-06-24', $response->json('extracted.letter_date'));
    }

    public function test_ocr_extract_uses_net_amount_when_company_has_discount(): void
    {
        $company = $this->civilianCompany('التأمين الصحي');
        $company->update(['discount_percent' => 10]);

        $item = $this->stockItem('RM-001', qty: 10, wac: 100.00);
        app(\App\Services\StockPriceService::class)->addBatch(
            $item, 10, 200.00, $this->makeSupplier(), 'INV-001', now()
        );

        $patient = $this->civilianPatient($company);
        $case    = $this->operationsReadyCase($patient);
        $case->update(['contract_company_id' => $company->id]);

        $ops = $this->userWithRole('operations');
        $this->actingAs($ops)
            ->postJson("/operations/pending/{$case->id}/release-quote")
            ->assertOk();

        $quote = Quote::where('case_id', $case->id)->firstOrFail();
        $quote->update(['total' => 4000]);

        $recep = $this->userWithRole('reception');
        $file  = UploadedFile::fake()->image('letter.webp');

        $response = $this->actingAs($recep)
            ->postJson('/reception/ocr/extract', [
                'quote_no'    => $quote->quote_no,
                'letter_file' => $file,
            ])
            ->assertOk();

        $this->assertEquals(3600.0, (float) $response->json('extracted.approved_amount'));
        $this->assertEquals(3600.0, (float) $response->json('quote.display_total'));
    }

    public function test_extract_endpoint_accepts_webp_image(): void
    {
        $item = $this->stockItem('RM-001', qty: 10, wac: 100.00);
        app(\App\Services\StockPriceService::class)->addBatch(
            $item, 10, 200.00, $this->makeSupplier(), 'INV-001', now()
        );

        $patient = $this->civilianPatient($this->civilianCompany());
        $case    = $this->operationsReadyCase($patient);
        $ops     = $this->userWithRole('operations');

        $this->actingAs($ops)
            ->postJson("/operations/pending/{$case->id}/release-quote")
            ->assertOk();

        $quote = Quote::where('case_id', $case->id)->firstOrFail();
        $recep = $this->userWithRole('reception');

        $file = UploadedFile::fake()->image('letter.webp');

        $this->actingAs($recep)
            ->postJson('/reception/ocr/extract', [
                'quote_no'    => $quote->quote_no,
                'letter_file' => $file,
            ])
            ->assertOk()
            ->assertJsonStructure(['stored_path', 'extracted']);
    }

    public function test_loose_binary_extractor_finds_arabic_fragments(): void
    {
        $service = app(OcrLetterExtractionService::class);
        $text    = $service->extractLooseTextFromBinary(
            'binary noise اسم المريض: أحمد علي المبلغ 50000 جنيه more noise'
        );

        $this->assertStringContainsString('اسم المريض', $text);
        $this->assertStringContainsString('أحمد علي', $text);
    }
}
