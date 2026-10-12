<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\VerifyCsrfToken;
use Tests\Support\ProstheticTestCase;

/**
 * «Page Expired 419» على السيرفر الأوفلاين: دخول ← خروج ← دخول، تاب قديم، وكوكي Secure على http.
 * CSRF مفعّل فعلياً هنا (Laravel يتخطاه في الاختبارات افتراضياً).
 */
class LoginSessionExpiryTest extends ProstheticTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(VerifyCsrfToken::class, StrictCsrfForTest::class);
    }

    private function tokenFromLoginPage(): string
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertSame(1, preg_match('/name="_token" value="([^"]+)"/', $html, $m), 'صفحة الدخول بلا _token');

        return $m[1];
    }

    public function test_login_logout_login_again_works_with_real_csrf(): void
    {
        $user = $this->userWithRole('technical');

        $this->post('/login', ['_token' => $this->tokenFromLoginPage(), 'username' => $user->username, 'password' => 'password'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($user);

        $this->post('/logout', ['_token' => session()->token()])->assertRedirect(route('home'));
        $this->assertGuest();

        $this->post('/login', ['_token' => $this->tokenFromLoginPage(), 'username' => $user->username, 'password' => 'password'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_stale_login_tab_returns_to_fresh_login_page_instead_of_419(): void
    {
        $user = $this->userWithRole('technical');

        $this->post('/login', ['_token' => 'token-from-an-old-tab', 'username' => $user->username, 'password' => 'password'])
            ->assertRedirect(route('home'))
            ->assertSessionHas('error');
        $this->assertGuest();

        // ويقدر يدخل مباشرة بعدها.
        $this->post('/login', ['_token' => $this->tokenFromLoginPage(), 'username' => $user->username, 'password' => 'password'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_logout_from_stale_tab_still_logs_out(): void
    {
        $this->actingAs($this->userWithRole('technical'));

        $this->post('/logout', ['_token' => 'old'])->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_ajax_with_expired_token_gets_clear_json_message(): void
    {
        $this->actingAs($this->userWithRole('technical'));

        $this->postJson('/technical/inventory/receive', [], ['X-CSRF-TOKEN' => 'old'])
            ->assertStatus(419)
            ->assertJsonPath('message', 'انتهت صلاحية الصفحة — حدّث الصفحة (F5) وحاول مرة أخرى.');
    }

    public function test_login_page_is_not_cached_by_the_browser(): void
    {
        $cache = $this->get('/')->assertOk()->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cache);
    }

    public function test_secure_cookie_setting_is_ignored_on_plain_http(): void
    {
        config(['session.secure' => true]);

        $cookie = collect($this->get('http://136.16.66.225/')->headers->getCookies())
            ->firstWhere(fn ($c) => $c->getName() === config('session.cookie'));

        $this->assertNotNull($cookie);
        $this->assertFalse($cookie->isSecure(), 'كوكي Secure على http لن يحفظه المتصفح → 419 في كل دخول');
    }
}

class StrictCsrfForTest extends VerifyCsrfToken
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}
