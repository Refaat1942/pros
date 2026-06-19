<?php

namespace Tests\Feature\Auth;

use App\Models\CaseRecord;
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
            'email'    => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('reception.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_user_can_login_at_admin_dashboard(): void
    {
        $user = $this->userWithRole('admin');

        $response = $this->post('/admin/login', [
            'email'    => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
    }

    /** Reception user must NOT be allowed in at the admin login page */
    public function test_reception_user_rejected_at_admin_login(): void
    {
        $user = $this->userWithRole('reception');

        $response = $this->post('/admin/login', [
            'email'    => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = $this->userWithRole('doctor');

        $response = $this->post('/doctor/login', [
            'email'    => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
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

    /** Doctor cannot access /technical/* */
    public function test_doctor_blocked_from_technical_dashboard(): void
    {
        $user = $this->userWithRole('doctor');
        $this->actingAs($user);

        $response = $this->getJson('/technical/inventory/list');

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
