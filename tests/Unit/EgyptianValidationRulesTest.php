<?php

namespace Tests\Unit;

use App\Rules\EgyptianMobile;
use App\Rules\EgyptianNationalId;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class EgyptianValidationRulesTest extends TestCase
{
    public function test_valid_egyptian_mobile_passes(): void
    {
        $this->assertTrue($this->validateMobile('01012345678'));
        $this->assertTrue($this->validateMobile('01112345678'));
        $this->assertTrue($this->validateMobile('01212345678'));
        $this->assertTrue($this->validateMobile('01512345678'));
    }

    public function test_nullable_mobile_passes_when_empty(): void
    {
        $this->assertTrue($this->validateMobile(null));
        $this->assertTrue($this->validateMobile(''));
    }

    public function test_invalid_mobile_fails(): void
    {
        $this->assertFalse($this->validateMobile('02123456789'));
        $this->assertFalse($this->validateMobile('0101234567'));
        $this->assertFalse($this->validateMobile('abc'));
        $this->assertFalse($this->validateMobile('بشيسيسش'));
    }

    public function test_valid_national_id_passes(): void
    {
        $this->assertTrue($this->validateNationalId('29901010100001'));
        $this->assertTrue($this->validateNationalId('39801010200002'));
    }

    public function test_nullable_national_id_passes_when_empty(): void
    {
        $this->assertTrue($this->validateNationalId(null));
        $this->assertTrue($this->validateNationalId(''));
    }

    public function test_invalid_national_id_fails(): void
    {
        $this->assertFalse($this->validateNationalId('19901010100001'));
        $this->assertFalse($this->validateNationalId('2990101010000'));
        $this->assertFalse($this->validateNationalId('abcdefghijklmn'));
        $this->assertFalse($this->validateNationalId('بشيسيسش'));
    }

    private function validateMobile(?string $value): bool
    {
        return ! Validator::make(
            ['phone' => $value],
            ['phone' => ['nullable', new EgyptianMobile()]]
        )->fails();
    }

    private function validateNationalId(?string $value): bool
    {
        return ! Validator::make(
            ['national_id' => $value],
            ['national_id' => ['nullable', new EgyptianNationalId()]]
        )->fails();
    }
}
