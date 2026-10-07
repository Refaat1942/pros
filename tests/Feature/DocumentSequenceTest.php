<?php

namespace Tests\Feature;

use App\Support\DocumentSequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DocumentSequenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_use_seeds_from_current_max_then_increments(): void
    {
        $this->assertSame(8, DocumentSequence::next('CASE-2026', fn () => 7));
        $this->assertSame(9, DocumentSequence::next('CASE-2026', fn () => 7));
        $this->assertSame(10, DocumentSequence::next('CASE-2026', fn () => 0));

        $this->assertSame(1, DocumentSequence::next('QUEUE-2026-10-07', fn () => 0));
        $this->assertSame(10, (int) DB::table('document_sequences')->where('key', 'CASE-2026')->value('value'));
    }

    public function test_patient_purge_resets_case_counters_but_keeps_stock_counters(): void
    {
        DocumentSequence::next('CASE-2026', fn () => 41);
        DocumentSequence::next('QT-', fn () => 12);
        DocumentSequence::next('ITM', fn () => 300);
        DocumentSequence::next('SR-2610', fn () => 3);

        $this->artisan('prosthetics:purge-patient-data --force')->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            ['ITM', 'SR-2610'],
            DB::table('document_sequences')->pluck('key')->all(),
        );
        $this->assertSame(1, DocumentSequence::next('CASE-2026', fn () => 0));
    }
}
