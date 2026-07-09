<?php

namespace Tests\Feature\Middleware;

use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class IdleSessionTimeoutTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_idle_session_redirects_to_home_with_error(): void
    {
        config(['session.idle_timeout' => 5]);

        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->withSession(['last_activity' => now()->subMinutes(6)->timestamp])
            ->get('/admin/branding-settings')
            ->assertRedirect(route('home'))
            ->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_active_session_is_not_logged_out(): void
    {
        config(['session.idle_timeout' => 5]);

        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->withSession(['last_activity' => now()->subMinutes(2)->timestamp])
            ->get('/admin/branding-settings')
            ->assertOk();
    }
}
