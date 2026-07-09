<?php

namespace Tests\Feature\Console;

use App\Models\Patient;
use Database\Seeders\PatientSeeder;
use Database\Seeders\RolesAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgePatientDataCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_removes_patients_and_keeps_users(): void
    {
        $this->seed(RolesAndAdminSeeder::class);
        $this->seed(PatientSeeder::class);

        $this->assertGreaterThan(0, Patient::query()->count());

        $this->artisan('prosthetics:purge-patient-data --force')
            ->assertSuccessful();

        $this->assertSame(0, Patient::query()->count());
        $this->assertGreaterThan(0, \App\Models\User::query()->count());
    }
}
