<?php

namespace Tests\Feature\Auth;

use App\Models\Permission;
use App\Models\User;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * Feature — Dashboard guard & role isolation.
 *
 * تسجيل الدخول موحّد (POST /login) ويوجّه كل موظف للوحة دوره؛ صفحات
 * /{dashboard}/login القديمة تُحوَّل للصفحة الرئيسية.
 * الوصول بين اللوحات بعد الدخول يحرسه DashboardGuardMiddleware ومصفوفة الصلاحيات.
 */
class DashboardGuardTest extends TestCase
{
    use ProstheticTestHelper;

    // ── Login ────────────────────────────────────────────────────────────────

    public function test_reception_user_can_login_at_reception_dashboard(): void
    {
        $user = $this->userWithRole('reception');

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('reception.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_user_can_login_at_admin_dashboard(): void
    {
        $user = $this->userWithRole('admin');

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
    }

    /** الأدمن بصلاحيات الاستقبال يدخل لوحته ثم يفتح الاستقبال */
    public function test_admin_user_can_open_reception_after_login(): void
    {
        $user = $this->userWithRole('admin');

        $this->post('/login', [
            'username' => $user->username,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->get(route('reception.appointments'))->assertOk();
    }

    /** موظف الاستقبال يُوجَّه للاستقبال حتى لو حاول من رابط لوحة الإدارة */
    public function test_reception_user_lands_on_reception_not_admin(): void
    {
        $user = $this->userWithRole('reception');

        $this->post('/login', [
            'username' => $user->username,
            'password' => 'password',
        ])->assertRedirect(route('reception.dashboard'));

        $this->get('/admin/overview')->assertStatus(403);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = $this->userWithRole('doctor');

        $response = $this->post('/login', [
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

        $response = $this->post('/login', [
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
        $admin = $this->limitedAdmin();
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
        $admin = $this->limitedAdmin();
        $admin->role->permissions()->detach();
        $this->actingAs($admin->fresh());

        $response = $this->get(route('reception.appointments'));

        $response->assertStatus(403);
    }

    /** Reception user with all reception permissions revoked is fully blocked */
    public function test_reception_user_without_permissions_blocked_from_own_dashboard(): void
    {
        $user = $this->userWithRole('reception');
        $user->role->permissions()->detach(
            Permission::where('dashboard', 'reception')->pluck('id')
        );
        $this->actingAs($user->fresh());

        $this->get(route('reception.dashboard'))->assertStatus(403);
        $this->get(route('reception.appointments'))->assertStatus(403);
    }

    public function test_reception_user_without_permissions_cannot_login(): void
    {
        $user = $this->userWithRole('reception');
        $user->role->permissions()->detach(
            Permission::where('dashboard', 'reception')->pluck('id')
        );

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    /** Unauthenticated request redirects to login */
    public function test_unauthenticated_request_redirected(): void
    {
        $response = $this->get('/admin/overview');

        $response->assertRedirect();
    }

    // ── روابط الدخول القديمة لكل لوحة تُحوَّل لصفحة الدخول الموحّدة ──────────

    /** @dataProvider dashboardSlugProvider */
    public function test_legacy_dashboard_login_url_redirects_to_unified_login(string $slug): void
    {
        $this->get("/{$slug}/login")->assertRedirect('/');
        $this->get('/')->assertOk();
    }

    /** أدمن محدود حقيقي (userWithRole('admin') يرجع سوبر أدمن يتجاوز كل الصلاحيات). */
    private function limitedAdmin(): User
    {
        $role = $this->makeRole(\App\Models\Role::SLUG_ADMIN);
        app(\App\Services\PermissionCatalogService::class)->syncToDatabase();
        $role->permissions()->sync(Permission::query()->where('dashboard', '!=', 'admin')->pluck('id'));

        return User::query()->updateOrCreate(
            ['username' => 'limited-admin'],
            [
                'role_id' => $role->id,
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'status' => User::STATUS_ACTIVE,
                'name' => 'أدمن محدود',
            ],
        );
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
