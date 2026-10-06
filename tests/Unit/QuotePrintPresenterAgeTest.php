<?php

namespace Tests\Unit;

use App\Support\QuotePrintPresenter;
use PHPUnit\Framework\TestCase;

class QuotePrintPresenterAgeTest extends TestCase
{
    public function test_age_is_derived_from_egyptian_national_id(): void
    {
        $at = new \DateTimeImmutable('2026-07-13');

        $this->assertSame(36, QuotePrintPresenter::ageFromNationalId('29001011234567', $at));
        $this->assertSame(25, QuotePrintPresenter::ageFromNationalId('30105150100000', $at));
        $this->assertSame(36, QuotePrintPresenter::ageFromNationalId('٢٩٠٠١٠١١٢٣٤٥٦٧', $at));
    }

    public function test_invalid_national_id_gives_no_age(): void
    {
        $this->assertNull(QuotePrintPresenter::ageFromNationalId(''));
        $this->assertNull(QuotePrintPresenter::ageFromNationalId('12345'));
        $this->assertNull(QuotePrintPresenter::ageFromNationalId('29013451234567'));
    }
}
