<?php

namespace Database\Seeders;

use App\Models\ReturnNote;
use App\Models\ReturnNoteLine;
use Database\Seeders\Support\PrototypeSeedData;
use Database\Seeders\Support\SeedRegistry;
use Illuminate\Database\Seeder;

class ReturnNoteSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PrototypeSeedData::returnNotes() as $row) {
            $bomId = SeedRegistry::$boms[$row['bomId']] ?? null;
            $caseId = SeedRegistry::$cases[$row['caseId']] ?? null;

            if (! $bomId || ! $caseId) {
                continue;
            }

            $note = ReturnNote::query()->create([
                'return_no' => $row['id'],
                'bom_id' => $bomId,
                'case_id' => $caseId,
                'order_ref' => $row['orderRef'],
                'work_order_no' => $row['workOrderNo'],
                'patient_name' => $row['patient'],
                'status' => $row['status'],
                'created_by' => $row['createdBy'],
                'authorized_at' => PrototypeSeedData::parseDateTime($row['authorizedAt'] ?? null),
                'completed_at' => PrototypeSeedData::parseDateTime($row['completedAt'] ?? null),
                'audit_trail' => $row['auditTrail'],
                'created_at' => PrototypeSeedData::parseDateTime($row['createdAt']),
                'updated_at' => PrototypeSeedData::parseDateTime($row['createdAt']),
            ]);

            foreach ($row['lines'] as $line) {
                ReturnNoteLine::query()->create([
                    'return_note_id' => $note->id,
                    'stock_item_code' => $line['code'],
                    'name' => $line['name'],
                    'qty_requested' => $line['qtyRequested'],
                    'qty_returned' => $line['qtyReturned'],
                    'reason' => $line['reason'],
                ]);
            }
        }
    }
}
