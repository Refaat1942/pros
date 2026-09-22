<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateStockUomSettingsRequest;
use App\Services\AuditService;
use App\Services\StockUomProfileService;
use Illuminate\Http\JsonResponse;

class StockUomSettingsController extends Controller
{
    public function __construct(private readonly StockUomProfileService $profiles) {}

    public function update(UpdateStockUomSettingsRequest $request): JsonResponse
    {
        $before = [
            'custom' => app(StockUomProfileService::class)->allProfiles(),
        ];

        $byKey = [];
        foreach ($request->validated('custom_profiles') as $row) {
            $key = $row['key'];
            $byKey[$key] = [
                'label' => $row['label'],
                'supply_uom' => $row['supply_uom'] !== '' ? $row['supply_uom'] : null,
                'units_per_supply_unit' => (float) $row['units_per_supply_unit'],
                'base_uom_hint' => $row['base_uom_hint'] ?? null,
            ];
        }

        $suggestions = $request->validated('supply_unit_suggestions');

        $this->profiles->save($byKey, is_array($suggestions) ? array_values($suggestions) : null);

        $after = ['custom' => $this->profiles->allProfiles()];

        AuditService::log(
            action: 'update',
            description: 'تحديث قوالب وحدات التوريد والمخزن',
            tag: 'admin',
            before: $before,
            after: $after,
        );

        return response()->json([
            'message' => 'تم حفظ إعدادات وحدات القياس.',
            'profiles' => $this->profiles->allProfiles(),
            'supply_unit_suggestions' => $this->profiles->supplyUnitSuggestions(),
        ]);
    }
}
