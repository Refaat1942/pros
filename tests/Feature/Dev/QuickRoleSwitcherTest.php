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

    public function test_switcher_config_lists_seven_pipeline_roles(): void
    {
        $this->assertCount(7, config('dev-role-switcher.roles'));
        $this->assertArrayHasKey('costing', config('dev-role-switcher.roles'));
        $this->assertSame('operations.dashboard', config('dev-role-switcher.roles.operations.route'));
        $this->assertSame('technical.dashboard', config('dev-role-switcher.roles.technical.route'));
    }
}
