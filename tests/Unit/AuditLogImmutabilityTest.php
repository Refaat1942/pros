<?php

namespace Tests\Unit;

use App\Models\AuditLog;
use App\Services\AuditService;
use LogicException;
use Tests\TestCase;

/**
 * Unit — AuditLog immutability (الـ Post-Credit Scene: Append-Only Audit Trail).
 *
 * The audit log is a legal document — update and delete are permanently forbidden.
 */
class AuditLogImmutabilityTest extends TestCase
{
    public function test_audit_log_can_be_created(): void
    {
        AuditService::log('create', 'اختبار الإنشاء', 'auth');

        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_audit_log_update_throws_logic_exception(): void
    {
        AuditService::log('create', 'إدخال أولي', 'auth');
        $log = AuditLog::first();

        $this->expectException(LogicException::class);

        $log->description = 'محاولة تعديل غير مشروعة';
        $log->save();
    }

    public function test_audit_log_delete_throws_logic_exception(): void
    {
        AuditService::log('create', 'إدخال للحذف', 'auth');
        $log = AuditLog::first();

        $this->expectException(LogicException::class);

        $log->delete();
    }

    public function test_audit_log_captures_before_and_after_payload(): void
    {
        AuditService::log(
            action:      'update',
            description: 'تعديل حالة',
            tag:         'medical',
            before:      ['stage_key' => 'reception'],
            after:       ['stage_key' => 'technical'],
        );

        $log = AuditLog::latest('logged_at')->first();

        $this->assertEquals(['stage_key' => 'reception'], $log->payload_before);
        $this->assertEquals(['stage_key' => 'technical'], $log->payload_after);
    }

    public function test_audit_log_accepts_null_payloads(): void
    {
        AuditService::log('login', 'تسجيل دخول', 'auth');

        $log = AuditLog::latest('logged_at')->first();

        $this->assertNull($log->payload_before);
        $this->assertNull($log->payload_after);
    }

    public function test_multiple_audit_entries_are_all_preserved(): void
    {
        AuditService::log('create', 'حركة 1', 'auth');
        AuditService::log('update', 'حركة 2', 'medical');
        AuditService::log('deliver', 'حركة 3', 'delivery');

        $this->assertDatabaseCount('audit_logs', 3);
    }
}
