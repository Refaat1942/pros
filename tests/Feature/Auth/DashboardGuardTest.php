<?php

namespace Tests\Feature\Auth;

use App\Models\Permission;
use App\Models\User;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * Feature — Dashboard guard & role isolation.
 *
 * Rule from system design: each employee logs in only at their
 * designated dashboard URL. Wrong-role login must be rejected.
 * Cross-dashboard access after login must be blocked by DashboardGuardMiddleware.
 */
class DashboardGuardTest extends TestCase
{
    use ProstheticTestHelper;

    // ── Login ────────────────────────────────────────────────────────────────

    public function test_reception_user_can_login_at_reception_dashboard(): void
    {
        $user = $this->userWithRole('reception');

        $response = $this->post('/reception/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('reception.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_user_can_login_at_admin_dashboard(): void
    {
        $user = $this->userWithRole('admin');

        $response = $this->post('/admin/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
    }

    /** Admin with cross-dashboard permissions can login at reception */
    public function test_admin_user_can_login_at_reception_dashboard(): void
    {
        $user = $this->userWithRole('admin');

        $response = $this->post('/reception/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('reception.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    /** Reception user must NOT be allowed in at the admin login page */
    public function test_reception_user_rejected_at_admin_login(): void
    {
        $user = $this->userWithRole('reception');

        $response = $this->post('/admin/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = $this->userWithRole('doctor');

        $response = $this->post('/doctor/login', [
            'username' => $user->username,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = $this->userWithRole('reception');
        $user->update(['status' => User::STATUS_INACTIVE]);

        $response = $this->post('/reception/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_inactive_user_is_logged_out_on_dashboard_access(): void
    {
        $user = $this->userWithRole('reception');
        $this->actingAs($user);

        $user->update(['status' => User::STATUS_INACTIVE]);

        $response = $this->get(route('reception.dashboard'));

        $response->assertRedirect('/reception/login');
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    // ── Cross-dashboard middleware guard ──────────────────────────────────────

    /** Logged-in reception user cannot access /admin/* routes */
    public function test_reception_user_blocked_from_admin_routes(): void
    {
        $user = $this->userWithRole('reception');
        $this->actingAs($user);

        $response = $this->get('/admin/overview');

        $response->assertStatus(403);
    }

    /** Doctor blocked from technical dashboard after admin removes that permission */
    public function test_doctor_blocked_from_technical_dashboard(): void
    {
        $user = $this->userWithRole('doctor');
        // Simulate admin revoking technical-dashboard access
        $user->role->permissions()->detach(
            Permission::where('dashboard', 'technical')->pluck('id')
        );
        $this->actingAs($user->fresh());

        $response = $this->getJson('/technical/inventory/list');

        $response->assertStatus(403);
    }

    /** Admin with cross-dashboard permissions can access reception */
    public function test_admin_with_permissions_can_access_reception_dashboard(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);

        $response = $this->get(route('reception.appointments'));

        $response->assertOk();
    }

    /** Admin with partial reception access cannot open blocked pages */
    public function test_admin_without_quote_permission_blocked_from_quote_page(): void
    {
        $admin = $this->userWithRole('admin');
        $appointmentsId = Permission::where('slug', 'reception.appointments.view')->value('id');
        $admin->role->permissions()->sync([$appointmentsId]);
        $this->actingAs($admin->fresh());

        $this->get(route('reception.appointments'))->assertOk();
        $this->get(route('reception.quote'))->assertStatus(403);
        $this->getJson('/reception/quote/list')->assertStatus(403);
    }

    /** Admin without reception permissions is blocked */
    public function test_admin_without_permissions_blocked_from_reception(): void
    {
        $admin = $this->userWithRole('admin');
        $admin->role->permissions()->detach();
        $this->actingAs($admin->fresh());

        $response = $this->get(route('reception.appointments'));

        $response->assertStatus(403);
    }

    /** Unauthenticated request redirects to login */
    public function test_unauthenticated_request_redirected(): void
    {
        $response = $this->get('/admin/overview');

        $response->assertRedirect();
    }

    // ── All 7 login pages exist ───────────────────────────────────────────────

    /** @dataProvider dashboardSlugProvider */
    public function test_login_page_exists_for_each_dashboard(string $slug): void
    {
        $response = $this->get("/{$slug}/login");

        $response->assertOk();
    }

    public static function dashboardSlugProvider(): array
    {
        return [
            ['admin'],
            ['reception'],
            ['doctor'],
            ['spec'],
            ['adjustments'],
            ['operations'],
            ['technical'],
        ];
    }
}
