<?php

namespace Tests\Unit;

use App\Support\ArabicAmount;
use Tests\TestCase;

class ArabicAmountTest extends TestCase
{
    public function test_split_separates_pounds_and_piasters(): void
    {
        $this->assertSame(['pounds' => 150, 'piasters' => 50], ArabicAmount::split(150.50));
        $this->assertSame(['pounds' => 55000, 'piasters' => 0], ArabicAmount::split(55000.00));
    }

    public function test_tafqeet_produces_arabic_phrase(): void
    {
        $text = ArabicAmount::tafqeet(150.00);

        $this->assertStringStartsWith('فقط ', $text);
        $this->assertStringEndsWith(' لا غير', $text);
        $this->assertStringContainsString('مائة', $text);
        $this->assertStringContainsString('خمسون', $text);
    }
}
