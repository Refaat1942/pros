<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

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

    protected function setUp(): void
    {
        parent::setUp();

        // سجل الـ seeders ثابت (static) ويبقى بين الاختبارات في نفس العملية — بدون تصفيره
        // تتخطى الـ seeders بياناتها في اختبار لاحق فتصبح الجداول فارغة حسب ترتيب التشغيل.
        \Database\Seeders\Support\SeedRegistry::reset();
    }
}
