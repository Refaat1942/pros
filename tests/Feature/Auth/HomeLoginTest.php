<?php

namespace Tests\Feature\Auth;

use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class HomeLoginTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_home_shows_unified_login_form(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('سجّل دخولك', false)
            ->assertSee('name="username"', false)
            ->assertDontSee('بوابات الدخول حسب الدور الوظيفي', false);
    }

    public function test_login_redirects_user_to_their_dashboard(): void
    {
        $reception = $this->userWithRole('reception');

        $this->post('/login', [
            'username' => $reception->username,
            'password' => 'password',
        ])->assertRedirect(route('reception.dashboard'));

        $this->assertAuthenticatedAs($reception);
    }

    public function test_authenticated_user_visiting_home_redirects_to_dashboard(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get('/')
            ->assertRedirect('/admin');
    }

    public function test_legacy_dashboard_login_url_redirects_to_home(): void
    {
        $this->get('/reception/login')
            ->assertRedirect('/');
    }
}
