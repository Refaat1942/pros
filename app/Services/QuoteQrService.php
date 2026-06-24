<?php

namespace App\Services;

use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * QR لعرض السعر — يُرمَز برقم العرض (quote_no) لمسح الموافقة عند الاستقبال.
 */
class QuoteQrService
{
    public function svg(string $quoteNo, int $size = 140): string
    {
        return (string) QrCode::size($size)->generate($quoteNo);
    }
}
