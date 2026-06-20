<?php

namespace Tests\Unit;

use App\Support\AuditLogLabel;
use Tests\TestCase;

class AuditLogLabelTest extends TestCase
{
    public function test_translates_common_auth_actions(): void
    {
        $this->assertSame('تسجيل دخول · مصادقة', AuditLogLabel::badge('login', 'auth'));
        $this->assertSame('تسجيل خروج · مصادقة', AuditLogLabel::badge('logout', 'auth'));
    }

    public function test_translates_financial_update(): void
    {
        $this->assertSame('تحديث · مالي', AuditLogLabel::badge('update', 'financial'));
    }

    public function test_falls_back_to_raw_key_for_unknown_values(): void
    {
        $this->assertSame('custom_action · custom_tag', AuditLogLabel::badge('custom_action', 'custom_tag'));
    }
}
