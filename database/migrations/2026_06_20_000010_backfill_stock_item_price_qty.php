<?php

use App\Models\PricingRequest;
use App\Models\StockItemPrice;
use App\Services\PricingService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        StockItemPrice::query()
            ->where(fn ($q) => $q->whereNull('qty')->orWhere('qty', '<=', 0))
            ->where('amount', '>', 0)
            ->update(['qty' => 1]);

        /** @var PricingService $pricingService */
        $pricingService = app(PricingService::class);

        PricingRequest::query()
            ->where('computed_total', '<=', 0)
            ->whereHas('items', fn ($q) => $q->where(fn ($q2) => $q2->whereNull('unit_price')->orWhere('unit_price', '<=', 0)))
            ->orderBy('id')
            ->each(fn (PricingRequest $request) => $pricingService->refreshLinePrices($request));
    }

    public function down(): void
    {
        // irreversible data correction
    }
};
