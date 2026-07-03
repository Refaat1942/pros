<?php

namespace Tests\Feature\Dev;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class QuickRoleSwitcherTest extends TestCase
{
    public function test_switcher_route_is_not_registered_outside_local(): void
    {
        $this->assertFalse(Route::has('dev.role-switch'));
    }

    public function test_switcher_config_lists_pipeline_roles(): void
    {
        $this->assertCount(9, config('dev-role-switcher.roles'));
        $this->assertArrayHasKey('costing', config('dev-role-switcher.roles'));
        $this->assertArrayHasKey('cashier', config('dev-role-switcher.roles'));
        $this->assertArrayHasKey('workshop', config('dev-role-switcher.roles'));
        $this->assertSame('operations.dashboard', config('dev-role-switcher.roles.operations.route'));
        $this->assertSame('cashier.dashboard', config('dev-role-switcher.roles.cashier.route'));
        $this->assertSame('workshop.dashboard', config('dev-role-switcher.roles.workshop.route'));
        $this->assertSame('technical.dashboard', config('dev-role-switcher.roles.technical.route'));
    }
}
