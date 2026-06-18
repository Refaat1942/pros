<?php

namespace Database\Seeders;

use App\Models\CreditNote;
use Database\Seeders\Support\PrototypeSeedData;
use Database\Seeders\Support\SeedRegistry;
use Illuminate\Database\Seeder;

class CreditNoteSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PrototypeSeedData::creditNotes() as $row) {
            $caseId = SeedRegistry::$cases[$row['caseId']] ?? null;
            if (! $caseId) {
                continue;
            }

            CreditNote::query()->create([
                'credit_note_no' => $row['id'],
                'case_id' => $caseId,
                'order_ref' => $row['orderRef'],
                'patient_name' => $row['patient'],
                'company_name' => $row['company'],
                'type' => $row['type'],
                'amount' => $row['amount'],
                'original_total' => $row['originalTotal'],
                'reason' => $row['reason'],
                'status' => $row['status'],
                'approved_at' => PrototypeSeedData::parseDateTime($row['approvedAt'] ?? null),
                'approved_by' => $row['approvedBy'],
                'created_at' => PrototypeSeedData::parseDateTime($row['createdAt']),
                'updated_at' => PrototypeSeedData::parseDateTime($row['createdAt']),
            ]);
        }
    }
}
