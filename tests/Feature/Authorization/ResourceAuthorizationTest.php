<?php

namespace Tests\Feature\Authorization;

use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Quote;
use App\Models\Role;
use App\Models\User;
use App\Models\AuditLog;
use App\Services\CashierPaymentService;
use App\Services\PermissionCatalogService;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class ResourceAuthorizationTest extends TestCase
{
    use ProstheticTestHelper;

    // ── Payment receipt ─────────────────────────────────────────────────────

    public function test_payment_receipt_requires_cashier_dashboard_access(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $payment = $this->cashPayment();
        $doctor = $this->userWithRole('doctor');
        $doctor->role->permissions()->detach(
            Permission::query()->where('dashboard', 'cashier')->pluck('id')
        );

        $this->actingAs($doctor->fresh())
            ->get('/cashier/payments/'.$payment->id.'/receipt')
            ->assertStatus(403);
    }

    public function test_payment_receipt_allowed_for_cashier_scope(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $payment = $this->cashPayment();

        $this->actingAs($this->userWithRole('cashier'))
            ->get('/cashier/payments/'.$payment->id.'/receipt')
            ->assertOk()
            ->assertSee($payment->payment_no, false);
    }

    public function test_payment_receipt_rejects_out_of_scope_payment(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->operationsReadyCase($patient);
        $quote = Quote::where('case_id', $case->id)->firstOrFail();

        $payment = Payment::create([
            'payment_no' => 'PAY-OOS-0001',
            'installment_no' => 1,
            'case_id' => $case->id,
            'quote_id' => $quote->id,
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'amount' => 100,
            'method' => 'cash',
            'received_at' => now(),
        ]);

        $this->actingAs($this->userWithRole('cashier'))
            ->get('/cashier/payments/'.$payment->id.'/receipt')
            ->assertStatus(403);
    }

    // ── Case payment history ────────────────────────────────────────────────

    public function test_case_payment_history_allowed_for_cashier_scope(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $case = $this->cashierAwaitingCase();

        $this->actingAs($this->userWithRole('cashier'))
            ->getJson('/cashier/payments/'.$case->id.'/history')
            ->assertOk();
    }

    public function test_case_payment_history_rejects_out_of_scope_case(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_OPERATIONS);

        $this->actingAs($this->userWithRole('cashier'))
            ->getJson('/cashier/payments/'.$case->id.'/history')
            ->assertStatus(403);
    }

    // ── Cash payment confirm (write path) ───────────────────────────────────

    public function test_cashier_confirm_payment_allowed_for_civilian_cash_at_cashier_stage(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $case = $this->cashierAwaitingCase();

        $this->actingAs($this->userWithRole('cashier'))
            ->postJson('/cashier/payments/'.$case->id.'/confirm', ['method' => 'cash'])
            ->assertOk()
            ->assertJsonPath('payment.method', 'cash');

        $this->assertSame(1, Payment::where('case_id', $case->id)->count());
    }

    /**
     * Abnormal setup: military case forced to cashier stage — proves case-type gate
     * is independent of workflow stage alone (normal workflow never routes here).
     */
    public function test_cashier_confirm_rejects_military_case_at_cashier_stage(): void
    {
        $company = $this->militaryCompany();
        $patient = $this->militaryPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_CASHIER);
        $paidBefore = (float) $case->paid;
        $financialAuditsBefore = AuditLog::query()->where('tag', 'financial')->count();

        $this->actingAs($this->userWithRole('cashier'))
            ->postJson('/cashier/payments/'.$case->id.'/confirm', ['method' => 'cash'])
            ->assertStatus(403);

        $fresh = $case->fresh();
        $this->assertSame(0, Payment::where('case_id', $case->id)->count());
        $this->assertEqualsWithDelta($paidBefore, (float) $fresh->paid, 0.001);
        $this->assertSame(CaseRecord::STAGE_CASHIER, $fresh->stage_key);
        $this->assertSame(
            $financialAuditsBefore,
            AuditLog::query()->where('tag', 'financial')->count(),
        );
    }

    /**
     * Abnormal setup: contracted civilian forced to cashier stage — same boundary as military test.
     */
    public function test_cashier_confirm_rejects_contract_civilian_at_cashier_stage(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_CASHIER);
        $paidBefore = (float) $case->paid;
        $financialAuditsBefore = AuditLog::query()->where('tag', 'financial')->count();

        $this->actingAs($this->userWithRole('cashier'))
            ->postJson('/cashier/payments/'.$case->id.'/confirm', ['method' => 'cash'])
            ->assertStatus(403);

        $fresh = $case->fresh();
        $this->assertSame(0, Payment::where('case_id', $case->id)->count());
        $this->assertEqualsWithDelta($paidBefore, (float) $fresh->paid, 0.001);
        $this->assertSame(CaseRecord::STAGE_CASHIER, $fresh->stage_key);
        $this->assertSame(
            $financialAuditsBefore,
            AuditLog::query()->where('tag', 'financial')->count(),
        );
    }

    public function test_cashier_confirm_requires_confirm_cash_payment_permission(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $case = $this->cashierAwaitingCase();
        $cashier = $this->userWithRole('cashier');
        $cashier->role->permissions()->detach(
            Permission::query()->where('slug', 'confirm-cash-payment')->pluck('id')
        );

        $this->actingAs($cashier->fresh())
            ->postJson('/cashier/payments/'.$case->id.'/confirm', ['method' => 'cash'])
            ->assertStatus(403);

        $this->assertSame(0, Payment::where('case_id', $case->id)->count());
    }

    // ── Patient show ────────────────────────────────────────────────────────

    public function test_patient_show_requires_reception_patients_page(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());
        $cashier = $this->userWithRole('cashier');
        $cashier->role->permissions()->detach(
            Permission::query()->where('dashboard', 'reception')->pluck('id')
        );

        $this->actingAs($cashier->fresh())
            ->getJson('/reception/patients/'.$patient->id)
            ->assertStatus(403);
    }

    public function test_patient_show_allowed_for_reception_patients_page(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());

        $this->actingAs($this->userWithRole('reception'))
            ->getJson('/reception/patients/'.$patient->id)
            ->assertOk()
            ->assertJsonPath('id', $patient->id);
    }

    public function test_patient_show_denied_without_patients_page_permission(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());
        $admin = $this->limitedAdminWithPages(['reception.appointments.view']);

        $this->actingAs($admin)
            ->getJson('/reception/patients/'.$patient->id)
            ->assertStatus(403);
    }

    // ── Patient update ──────────────────────────────────────────────────────

    public function test_patient_update_allowed_for_active_patient(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());

        $this->actingAs($this->userWithRole('reception'))
            ->putJson('/reception/patients/'.$patient->id, ['phone' => '01012345678'])
            ->assertOk()
            ->assertJsonPath('phone', '01012345678');
    }

    public function test_patient_update_rejects_archived_patient(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());
        $patient->update([
            'archived_at' => now(),
            'status' => Patient::STATUS_DONE,
        ]);

        $this->actingAs($this->userWithRole('reception'))
            ->putJson('/reception/patients/'.$patient->id, ['phone' => '01099999999'])
            ->assertStatus(403);

        $this->assertSame(
            $patient->fresh()->phone,
            $patient->phone,
            'Archived patient phone must not change after denied update.',
        );
    }

    // ── Patient initiate case ───────────────────────────────────────────────

    public function test_patient_case_creation_allowed_for_active_patient(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());

        $this->actingAs($this->userWithRole('reception'))
            ->postJson('/reception/patients/'.$patient->id.'/cases')
            ->assertStatus(201);

        $this->assertGreaterThan(0, $patient->cases()->count());
    }

    public function test_patient_case_creation_rejects_archived_patient(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());
        $beforeCount = $patient->cases()->count();
        $patient->update([
            'archived_at' => now(),
            'status' => Patient::STATUS_DONE,
        ]);

        $this->actingAs($this->userWithRole('reception'))
            ->postJson('/reception/patients/'.$patient->id.'/cases')
            ->assertStatus(403);

        $this->assertSame($beforeCount, $patient->cases()->count());
    }

    // ── Military debt delete ────────────────────────────────────────────────

    public function test_military_debt_delete_requires_delete_action_permission(): void
    {
        $debt = $this->makeMilitaryDebtRecord();
        $admin = $this->limitedAdminWithPages(['admin.military-debts.view']);

        $this->actingAs($admin)
            ->deleteJson('/admin/military-debts/'.$debt->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('military_debts', ['id' => $debt->id]);
    }

    public function test_military_debt_delete_allowed_with_delete_permission(): void
    {
        app(PermissionCatalogService::class)->syncToDatabase();
        $debt = $this->makeMilitaryDebtRecord();
        $admin = $this->limitedAdminWithPages([
            'admin.military-debts.view',
            'delete-military-debt',
        ]);

        $this->actingAs($admin)
            ->deleteJson('/admin/military-debts/'.$debt->id)
            ->assertOk();

        $this->assertDatabaseMissing('military_debts', ['id' => $debt->id]);
    }

    public function test_military_debt_delete_denied_for_non_admin_dashboard(): void
    {
        $debt = $this->makeMilitaryDebtRecord();

        $this->actingAs($this->userWithRole('reception'))
            ->deleteJson('/admin/military-debts/'.$debt->id)
            ->assertStatus(403);
    }

    // ── Quote print ─────────────────────────────────────────────────────────

    public function test_quote_print_reception_allowed_for_issued_civilian_quote(): void
    {
        $quote = $this->issued_civilian_quote();

        $this->actingAs($this->userWithRole('reception'))
            ->get('/reception/quote/'.$quote->id.'/print')
            ->assertOk();
    }

    public function test_quote_print_reception_rejects_pending_quote(): void
    {
        $quote = $this->pending_civilian_quote();

        $this->actingAs($this->userWithRole('reception'))
            ->get('/reception/quote/'.$quote->id.'/print')
            ->assertStatus(403);
    }

    public function test_quote_print_reception_denied_without_quote_page_permission(): void
    {
        $quote = $this->issued_civilian_quote();
        $reception = $this->userWithRole('reception');
        $reception->role->permissions()->detach(
            Permission::query()->where('slug', 'reception.quote.view')->pluck('id')
        );

        $this->actingAs($reception->fresh())
            ->get('/reception/quote/'.$quote->id.'/print')
            ->assertStatus(403);
    }

    public function test_quote_print_cashier_allowed_for_issued_civilian_quote(): void
    {
        $quote = $this->issued_civilian_quote();

        $this->actingAs($this->userWithRole('cashier'))
            ->get('/cashier/quote/'.$quote->id.'/print')
            ->assertOk();
    }

    public function test_quote_print_cashier_denied_without_payments_page_permission(): void
    {
        $quote = $this->issued_civilian_quote();
        $cashier = $this->userWithRole('cashier');
        $cashier->role->permissions()->detach(
            Permission::query()->where('slug', 'cashier.payments.view')->pluck('id')
        );

        $this->actingAs($cashier->fresh())
            ->get('/cashier/quote/'.$quote->id.'/print')
            ->assertStatus(403);
    }

    public function test_quote_print_operations_allowed_for_issued_civilian_quote(): void
    {
        $quote = $this->issued_civilian_quote();

        $this->actingAs($this->userWithRole('operations'))
            ->get('/operations/quote/'.$quote->id.'/print')
            ->assertOk();
    }

    public function test_quote_print_operations_denied_without_pending_page_permission(): void
    {
        $quote = $this->issued_civilian_quote();
        $ops = $this->userWithRole('operations');
        $ops->role->permissions()->detach(
            Permission::query()->where('slug', 'operations.pending.view')->pluck('id')
        );

        $this->actingAs($ops->fresh())
            ->get('/operations/quote/'.$quote->id.'/print')
            ->assertStatus(403);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function issued_civilian_quote(): Quote
    {
        $this->stockItem('RM-001', qty: 10);
        $case = $this->operationsReadyCase($this->civilianPatient($this->civilianCompany()));
        $quote = Quote::where('case_id', $case->id)->firstOrFail();

        if ($quote->status !== Quote::STATUS_ISSUED) {
            app(\App\Services\QuoteService::class)->markIssued($quote);
            $quote = $quote->fresh();
        }

        return $quote;
    }

    private function pending_civilian_quote(): Quote
    {
        $this->stockItem('RM-001', qty: 10);
        $case = $this->operationsReadyCase($this->civilianPatient($this->civilianCompany()));
        $quote = Quote::where('case_id', $case->id)->firstOrFail();
        $quote->update(['status' => Quote::STATUS_PENDING, 'status_label' => 'معلق']);

        return $quote->fresh();
    }

    private function cashPayment(): Payment
    {
        $case = $this->cashierAwaitingCase();

        return app(CashierPaymentService::class)->confirmPayment($case, [
            'method' => 'cash',
        ])['payment'];
    }

    private function cashierAwaitingCase(): CaseRecord
    {
        $case = $this->operationsReadyCase($this->cashPatient());

        return $case->fresh();
    }

    private function makeMilitaryDebtRecord(): \App\Models\MilitaryDebt
    {
        $company = $this->militaryCompany();
        $patient = $this->militaryPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_DELIVERED);

        return \App\Models\MilitaryDebt::create([
            'case_id' => $case->id,
            'work_order_no' => 'WO-AUTH-'.uniqid(),
            'patient_name' => $patient->name,
            'sovereign_entity' => 'القوات المسلحة',
            'total_cost' => 1000,
            'collected' => 0,
            'delivered_at' => now()->toDateString(),
            'status' => \App\Models\MilitaryDebt::STATUS_PENDING,
        ]);
    }

    /** @param list<string> $permissionSlugs */
    private function limitedAdminWithPages(array $permissionSlugs): User
    {
        app(PermissionCatalogService::class)->syncToDatabase();

        $role = Role::firstOrCreate(
            ['slug' => 'limited_admin_test'],
            ['label_ar' => 'أدمن محدود للاختبار'],
        );

        $ids = Permission::query()
            ->whereIn('slug', $permissionSlugs)
            ->pluck('id')
            ->all();

        $role->permissions()->sync($ids);

        return User::query()->updateOrCreate(
            ['username' => 'limited_admin_test'],
            [
                'role_id' => $role->id,
                'password' => Hash::make('password'),
                'status' => User::STATUS_ACTIVE,
                'name' => 'أدمن محدود',
            ],
        );
    }
}
