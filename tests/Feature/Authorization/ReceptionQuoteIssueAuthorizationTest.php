<?php

namespace Tests\Feature\Authorization;

use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\Permission;
use App\Models\Quote;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class ReceptionQuoteIssueAuthorizationTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_reception_cannot_issue_pending_quote_before_operations_release(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->operationsReadyCase($patient);
        $quote = Quote::where('case_id', $case->id)->firstOrFail();

        $this->assertSame(Quote::STATUS_PENDING, $quote->status);
        $this->assertSame(CaseRecord::STAGE_OPERATIONS, $case->stage_key);

        $quotesAuditsBefore = AuditLog::query()->where('tag', 'quotes')->count();

        $this->actingAs($this->userWithRole('reception'))
            ->postJson('/reception/quote/'.$quote->id.'/issue')
            ->assertStatus(403);

        $quote->refresh();
        $case->refresh();

        $this->assertSame(Quote::STATUS_PENDING, $quote->status);
        $this->assertSame(CaseRecord::STAGE_OPERATIONS, $case->stage_key);
        $this->assertSame(
            $quotesAuditsBefore,
            AuditLog::query()->where('tag', 'quotes')->count(),
        );
    }

    public function test_reception_can_issue_quote_after_operations_release(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->operationsReadyCase($patient);
        $quote = Quote::where('case_id', $case->id)->firstOrFail();
        $ops = $this->userWithRole('operations');

        $this->actingAs($ops)
            ->postJson("/operations/pending/{$case->id}/release-quote")
            ->assertOk()
            ->assertJsonPath('quote.status', Quote::STATUS_ISSUED);

        $quote->refresh();
        $this->assertSame(Quote::STATUS_ISSUED, $quote->status);

        $this->actingAs($this->userWithRole('reception'))
            ->postJson('/reception/quote/'.$quote->id.'/issue')
            ->assertOk()
            ->assertJsonPath('quote.status', Quote::STATUS_ISSUED);
    }

    public function test_existing_issued_quote_behavior_is_preserved(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);

        $quote = Quote::create([
            'quote_no' => 'QT-REISSUE-0001',
            'case_id' => $case->id,
            'order_ref' => $case->order_ref,
            'patient_name' => $patient->name,
            'company_name' => $company->name,
            'quote_date' => now()->toDateString(),
            'status' => Quote::STATUS_ISSUED,
            'status_label' => 'صادر للجهة — بانتظار خطاب الموافقة',
            'total' => 400.00,
        ]);

        $this->actingAs($this->userWithRole('reception'))
            ->postJson('/reception/quote/'.$quote->id.'/issue')
            ->assertOk()
            ->assertJsonPath('quote.status', Quote::STATUS_ISSUED);

        $this->assertSame(Quote::STATUS_ISSUED, $quote->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'issue',
            'tag' => 'quotes',
        ]);
    }

    public function test_user_without_reception_quote_permission_cannot_issue(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);

        $quote = Quote::create([
            'quote_no' => 'QT-NO-PERM-0001',
            'case_id' => $case->id,
            'order_ref' => $case->order_ref,
            'patient_name' => $patient->name,
            'company_name' => $company->name,
            'quote_date' => now()->toDateString(),
            'status' => Quote::STATUS_ISSUED,
            'total' => 300.00,
        ]);

        $cashier = $this->userWithRole('cashier');
        $cashier->role->permissions()->detach(
            Permission::query()->where('dashboard', 'reception')->pluck('id')
        );

        $this->actingAs($cashier->fresh())
            ->postJson('/reception/quote/'.$quote->id.'/issue')
            ->assertStatus(403);

        $this->assertSame(Quote::STATUS_ISSUED, $quote->fresh()->status);
    }

    public function test_pending_quote_cannot_bypass_operations_via_approval_letter_after_denied_issue(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->operationsReadyCase($patient);
        $quote = Quote::where('case_id', $case->id)->firstOrFail();

        $reception = $this->userWithRole('reception');

        $this->actingAs($reception)
            ->postJson('/reception/quote/'.$quote->id.'/issue')
            ->assertStatus(403);

        $this->actingAs($reception)
            ->postJson('/reception/approval-letter/confirm', [
                'quote_no' => $quote->quote_no,
                'patient_name' => $patient->name,
                'approved_amount' => (float) $quote->total,
                'company_name' => $company->name,
            ])
            ->assertStatus(422);

        $quote->refresh();
        $case->refresh();

        $this->assertSame(Quote::STATUS_PENDING, $quote->status);
        $this->assertSame(CaseRecord::STAGE_OPERATIONS, $case->stage_key);
        $this->assertNotSame(Quote::STATUS_APPROVED, $quote->status);
    }
}
