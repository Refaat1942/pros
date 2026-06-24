<?php

namespace Tests\Unit;

use App\Support\OcrLetterParser;
use Tests\TestCase;

class OcrLetterParserTest extends TestCase
{
    public function test_parses_patient_amount_ref_and_date_from_arabic_letter(): void
    {
        $text = <<<'TXT'
        السادة / التأمين الصحي
        بعد التحية،،،
        اسم المريض: عرفه بنتيست الفاتورة
        المبلغ: 72,000 جنيه
        خطاب رقم (445/2026)
        التاريخ: 24/06/2026
        TXT;

        $parsed = OcrLetterParser::parse($text, [
            'patient_hint' => 'عرفه بنتيست الفاتورة',
            'amount_hint'  => 72000.0,
            'company_hint' => 'التأمين الصحي',
        ]);

        $this->assertSame('عرفه بنتيست الفاتورة', $parsed['patient_name']);
        $this->assertEquals(72000.0, $parsed['approved_amount']);
        $this->assertSame('445/2026', $parsed['letter_ref']);
        $this->assertSame('2026-06-24', $parsed['letter_date']);
    }

    public function test_amount_prefers_value_matching_quote_hint(): void
    {
        $text = 'المبلغ 55,000 جنيه وملاحظة أخرى 1200';

        $parsed = OcrLetterParser::parse($text, ['amount_hint' => 55000.0]);

        $this->assertEquals(55000.0, $parsed['approved_amount']);
    }

    public function test_normalizes_eastern_arabic_digits(): void
    {
        $text = 'المبلغ: ٧٢٠٠٠ جنيه';

        $parsed = OcrLetterParser::parse($text, ['amount_hint' => 72000.0]);

        $this->assertEquals(72000.0, $parsed['approved_amount']);
    }
}
