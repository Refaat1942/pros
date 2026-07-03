<?php

namespace Tests\Unit;

use App\Enums\CaseStage;
use Tests\TestCase;

class CaseStageLegacyLabelTest extends TestCase
{
    public function test_legacy_admin_approval_label_is_arabic(): void
    {
        $this->assertSame('انتظار موافقة الأدمن', CaseStage::labelFor('admin_approval'));
    }

    public function test_legacy_waiting_return_label_is_arabic(): void
    {
        $this->assertSame('بانتظار موافقة الجهة', CaseStage::labelFor('waiting_return'));
    }
}
