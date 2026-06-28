<?php

namespace Tests\Feature\Reports;

use App\Models\AuditLog;
use App\Services\AuditService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class AdminAuditFilterTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_admin_audit_filters_by_tag_search_and_date(): void
    {
        $admin = $this->userWithRole('admin');

        AuditLog::create([
            'user_name'   => 'أحمد محمود',
            'action'      => 'login',
            'description' => 'تسجيل دخول ناجح',
            'tag'         => 'auth',
            'logged_at'   => '2024-05-27 10:00:00',
        ]);

        AuditLog::create([
            'user_name'   => 'سارة فني',
            'action'      => 'update',
            'description' => 'تحديث سجل طبي',
            'tag'         => 'medical',
            'logged_at'   => '2024-06-01 12:00:00',
        ]);

        $this->actingAs($admin);

        $this->get('/admin/audit?tag=auth')
            ->assertOk()
            ->assertSee('تسجيل دخول ناجح')
            ->assertDontSee('تحديث سجل طبي');

        $this->get('/admin/audit?search=سارة')
            ->assertOk()
            ->assertSee('تحديث سجل طبي')
            ->assertDontSee('تسجيل دخول ناجح');

        $this->get('/admin/audit?date_from=2024-05-27&date_to=2024-05-27')
            ->assertOk()
            ->assertSee('تسجيل دخول ناجح')
            ->assertDontSee('تحديث سجل طبي');
    }
}
