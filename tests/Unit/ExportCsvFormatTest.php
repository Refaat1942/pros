<?php

namespace Tests\Unit;

use App\Support\ExportCsvFormat;
use Tests\TestCase;

class ExportCsvFormatTest extends TestCase
{
    public function test_strips_trailing_currency_suffix(): void
    {
        $this->assertSame('2,000.00', ExportCsvFormat::cell('2,000.00 ج.م'));
        $this->assertSame('53,000.00', ExportCsvFormat::cell('53,000.00 ج.م'));
    }

    public function test_strips_currency_from_header_labels(): void
    {
        $this->assertSame('المستحق', ExportCsvFormat::cell('المستحق (ج.م)'));
    }

    public function test_row_strips_all_cells(): void
    {
        $this->assertSame(
            ['عبدالله', 'WO-001', '10,400.00'],
            ExportCsvFormat::row(['عبدالله', 'WO-001', '10,400.00 ج.م']),
        );
    }
}
