<?php

namespace Tests\Unit;

use App\Support\ArabicAmountInWords;
use PHPUnit\Framework\TestCase;

class ArabicAmountInWordsTest extends TestCase
{
    public function test_amounts_in_words(): void
    {
        $this->assertSame('فقط ثمانية آلاف وأربعمائة وأربعة وعشرون جنيهًا مصريًا لا غير', ArabicAmountInWords::egp(8424));
        $this->assertSame('فقط ألف جنيه مصري لا غير', ArabicAmountInWords::egp(1000));
        $this->assertSame('فقط ألفان وخمسمائة جنيه مصري لا غير', ArabicAmountInWords::egp(2500));
        $this->assertSame('فقط خمسة جنيهات مصرية لا غير', ArabicAmountInWords::egp(5));
        $this->assertSame('فقط أحد عشر ألف جنيه مصري لا غير', ArabicAmountInWords::egp(11000));
        $this->assertSame('فقط مائة وخمسون جنيهًا مصريًا وخمسة وعشرون قرشًا لا غير', ArabicAmountInWords::egp(150.25));
        $this->assertSame('فقط مليون ومائتان وخمسون ألف جنيه مصري لا غير', ArabicAmountInWords::egp(1250000));
        $this->assertSame('فقط تسعة آلاف وثلاثمائة وستون جنيهًا مصريًا لا غير', ArabicAmountInWords::egp(9360));
        $this->assertSame('فقط صفر جنيه مصري لا غير', ArabicAmountInWords::egp(0));
    }
}
