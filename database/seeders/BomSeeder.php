<?php

namespace Database\Seeders;

use App\Models\Bom;
use App\Models\BomItem;
use Database\Seeders\Support\PrototypeSeedData;
use Database\Seeders\Support\SeedRegistry;
use Illuminate\Database\Seeder;

class BomSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PrototypeSeedData::boms() as $row) {
            $caseId = SeedRegistry::$cases[$row['caseId']] ?? null;
            if (! $caseId) {
                continue;
            }

            $bom = Bom::query()->create([
                'bom_no' => $row['id'],
                'case_id' => $caseId,
                'order_ref' => $row['orderRef'],
                'quote_no' => $row['quoteId'],
                'patient_name' => $row['patient'],
                'stage' => $row['stage'],
                'released_at' => PrototypeSeedData::parseDateTime($row['releasedAt'] ?? null),
                'finished_at' => PrototypeSeedData::parseDateTime($row['finishedAt'] ?? null),
                'created_at' => PrototypeSeedData::parseDate($row['createdAt']),
                'updated_at' => PrototypeSeedData::parseDate($row['createdAt']),
            ]);

            SeedRegistry::$boms[$row['id']] = $bom->id;

            foreach ($row['items'] as $item) {
                BomItem::query()->create([
                    'bom_id' => $bom->id,
                    'stock_item_code' => $item['code'],
                    'name' => $item['name'],
                    'qty' => $item['qty'] ?? 1,
                    'unit_cost' => $item['unitCost'] ?? 0,
                    'issued_qty' => $item['issuedQty'] ?? 0,
                    'returned_qty' => $item['returnedQty'] ?? 0,
                ]);
            }
        }
    }
}
