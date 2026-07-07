<?php

namespace Tests\Unit;

use App\Support\PaymentReceiptPresenter;
use PHPUnit\Framework\TestCase;

class PaymentReceiptWordsTest extends TestCase
{
    public function test_zero_amount(): void
    {
        $this->assertSame('صفر جنيهاً مصرياً فقط لا غير.', PaymentReceiptPresenter::amountInWords(0));
    }

    public function test_whole_pounds(): void
    {
        $this->assertStringContainsString('جنيهاً مصرياً', PaymentReceiptPresenter::amountInWords(1325));
        $this->assertStringContainsString('فقط لا غير.', PaymentReceiptPresenter::amountInWords(1325));
    }

    public function test_pounds_and_piasters(): void
    {
        $words = PaymentReceiptPresenter::amountInWords(1000.50);
        $this->assertStringContainsString('ألف', $words);
        $this->assertStringContainsString('قرشاً', $words);
    }

    public function test_millions(): void
    {
        $words = PaymentReceiptPresenter::amountInWords(2500000);
        $this->assertStringContainsString('مليون', $words);
    }
}
