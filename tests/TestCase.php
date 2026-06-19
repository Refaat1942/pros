<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;

    /**
     * Seeders are intentionally disabled during the test phase.
     * All tests must construct their own data from scratch to verify
     * that validation rules, status transitions, and DB transactions
     * work correctly on empty tables.
     */
    protected bool $seed = false;
}
