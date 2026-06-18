<?php

namespace Database\Seeders;

use App\Models\CaseRecord;
use App\Models\PricingRequest;
use App\Models\PricingRequestItem;
use Database\Seeders\Support\PrototypeSeedData;
use Database\Seeders\Support\SeedRegistry;
use Illuminate\Database\Seeder;

class PricingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PrototypeSeedData::pricingRequests() as $row) {
            $caseId = null;
            foreach (PrototypeSeedData::cases() as $case) {
                if (($case['pricingQueueId'] ?? null) === $row['id']) {
                    $caseId = SeedRegistry::$cases[$case['id']] ?? null;
                    break;
                }
            }

            $pricing = PricingRequest::query()->create([
                'request_no' => $row['id'],
                'order_ref' => $row['orderRef'],
                'case_id' => $caseId,
                'patient_name' => $row['patient'],
                'company_name' => $row['company'],
                'request_date' => PrototypeSeedData::parseDate($row['date']),
                'items_count' => $row['items'],
                'doctor_name' => $row['doctor'],
                'patient_type' => $row['patientType'],
                'status_key' => $row['statusKey'],
                'step' => $row['step'],
                'approved_at' => PrototypeSeedData::parseDateTime($row['approvedAt'] ?? null),
                'approved_by' => $row['approvedBy'],
            ]);

            SeedRegistry::$pricingRequests[$row['id']] = $pricing->id;

            foreach ($row['recommendations'] as $rec) {
                PricingRequestItem::query()->create([
                    'pricing_request_id' => $pricing->id,
                    'stock_item_code' => $rec['code'],
                    'name' => $rec['name'],
                    'qty' => $rec['qty'] ?? 1,
                ]);
            }

            if ($caseId) {
                CaseRecord::query()->whereKey($caseId)->update([
                    'pricing_request_id' => $pricing->id,
                ]);
            }
        }
    }
}
