<?php

namespace Database\Seeders;

use App\Models\Quote;
use App\Models\QuoteItem;
use Database\Seeders\Support\PrototypeSeedData;
use Database\Seeders\Support\SeedRegistry;
use Illuminate\Database\Seeder;

class QuoteSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PrototypeSeedData::cases() as $case) {
            if (empty($case['quoteId'])) {
                continue;
            }

            $caseId = SeedRegistry::$cases[$case['id']] ?? null;
            if (! $caseId) {
                continue;
            }

            $pricingRequestId = null;
            if (! empty($case['pricingQueueId'])) {
                $pricingRequestId = SeedRegistry::$pricingRequests[$case['pricingQueueId']] ?? null;
            }

            $status = match ($case['stageKey']) {
                'admin_approval', 'cost_calc' => 'approved',
                'waiting_return' => 'issued',
                default => 'issued',
            };

            $statusLabel = $case['stageKey'] === 'waiting_return'
                ? 'معتمد — جاهز للطباعة'
                : 'معتمد';

            $quote = Quote::query()->create([
                'quote_no' => $case['quoteId'],
                'order_ref' => $case['orderRef'],
                'case_id' => $caseId,
                'pricing_request_id' => $pricingRequestId,
                'patient_name' => $case['patient'],
                'company_name' => $case['company'],
                'quote_date' => PrototypeSeedData::parseDate($case['quoteDate'] ?? $case['createdAt']),
                'status' => $status,
                'status_label' => $statusLabel,
                'total' => $case['quoteTotal'],
            ]);

            SeedRegistry::$quotes[$case['quoteId']] = $quote->id;

            $recommendations = PrototypeSeedData::recommendationsForCase($case['id'], $case['orderRef']);

            foreach ($recommendations as $rec) {
                $qty = $rec['qty'] ?? 1;
                $unit = PrototypeSeedData::highestUnitPrice($rec['code']);

                QuoteItem::query()->create([
                    'quote_id' => $quote->id,
                    'name' => $rec['name'].' — '.$rec['code'],
                    'stock_item_code' => $rec['code'],
                    'qty' => $qty,
                    'amount' => $unit * $qty,
                ]);
            }
        }
    }
}
