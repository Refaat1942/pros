<?php

namespace Database\Seeders;

use App\Models\CaseRecommendation;
use App\Models\CaseRecord;
use Database\Seeders\Support\PrototypeSeedData;
use Database\Seeders\Support\SeedRegistry;
use Illuminate\Database\Seeder;

class CaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PrototypeSeedData::cases() as $row) {
            $patientType = PrototypeSeedData::derivePatientType($row);
            $patientCode = PrototypeSeedData::derivePatientId($row['id'], $patientType);
            $patientId = SeedRegistry::$patients[$patientCode] ?? null;

            if (! $patientId) {
                continue;
            }

            $case = CaseRecord::query()->create([
                'case_no' => $row['id'],
                'order_ref' => $row['orderRef'],
                'patient_id' => $patientId,
                'contract_company_id' => SeedRegistry::$companies[$row['company']] ?? null,
                'company_name' => $row['company'],
                'patient_type' => $patientType,
                'path' => $row['path'],
                'stage_key' => $row['stageKey'],
                'manufacturing_stage' => $row['manufacturingStage'],
                'work_order_no' => $row['workOrderNo'] ?? null,
                'quote_no' => $row['quoteId'],
                'quote_date' => PrototypeSeedData::parseDate($row['quoteDate'] ?? null),
                'quote_total' => $row['quoteTotal'],
                'total_cost' => $row['totalCost'],
                'paid' => $row['paid'],
                'approval_date' => PrototypeSeedData::parseDate($row['approvalDate'] ?? null),
                'approval_confirmed_at' => PrototypeSeedData::parseDateTime($row['approvalConfirmedAt'] ?? null),
                'delivered_at' => PrototypeSeedData::parseDate($row['deliveredAt'] ?? null),
                'rank' => $row['rank'] ?? null,
                'sovereign_entity' => $row['sovereignEntity'] ?? null,
            ]);

            SeedRegistry::$cases[$row['id']] = $case->id;

            $recommendations = $row['recommendations']
                ?? PrototypeSeedData::recommendationsForCase($row['id'], $row['orderRef']);

            foreach ($recommendations as $rec) {
                CaseRecommendation::query()->create([
                    'case_id' => $case->id,
                    'stock_item_code' => $rec['code'],
                    'name' => $rec['name'],
                    'qty' => $rec['qty'] ?? 1,
                ]);
            }
        }
    }
}
