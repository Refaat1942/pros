<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCostingModesRequest;
use App\Http\Requests\Admin\UpdateCostingSettingsRequest;
use App\Services\AuditService;
use App\Services\CostingModeService;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;

class CostingSettingsController extends Controller
{
    public function __construct(
        private readonly SettingService $settings,
        private readonly CostingModeService $modeService,
    ) {}

    public function update(UpdateCostingSettingsRequest $request): JsonResponse
    {
        $before = $this->settings->overheadRates();

        $this->settings->updateOverheadRates($request->validated());

        $after = $this->settings->overheadRates();

        AuditService::log(
            action: 'update',
            description: 'تحديث نسب المصاريف الإضافية للتكاليف',
            tag: 'admin',
            before: $before,
            after: $after,
        );

        return response()->json([
            'message' => 'تم حفظ الإعدادات.',
            'rates' => $this->settings->overheadRateDefinitions(),
            'rates_sum' => $this->settings->overheadRatesSum(),
        ]);
    }

    /**
     * حفظ أنماط التكاليف ومكوّناتها (طرف صناعي / صرف سريع) — قابلة للتحكم من الإدارة.
     */
    public function updateModes(UpdateCostingModesRequest $request): JsonResponse
    {
        $before = $this->modeService->allModes();

        $this->modeService->saveModes($request->validated('modes'));

        $after = $this->modeService->allModes();

        AuditService::log(
            action: 'update',
            description: 'تحديث أنماط التكاليف ومكوّناتها',
            tag: 'admin',
            before: $before,
            after: $after,
        );

        return response()->json([
            'message' => 'تم حفظ أنماط التكاليف.',
            'costing_modes' => $after,
        ]);
    }
}
