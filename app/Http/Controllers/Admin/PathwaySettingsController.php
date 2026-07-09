<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePathwaySettingsRequest;
use App\Models\PathwayStep;
use App\Services\AuditService;
use App\Services\PathwayConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PathwaySettingsController extends Controller
{
    public function __construct(private readonly PathwayConfigService $pathwayConfig) {}

    public function update(UpdatePathwaySettingsRequest $request): JsonResponse
    {
        $pathway = $request->validated('pathway');
        $before = $this->pathwayConfig->steps($pathway);

        $this->pathwayConfig->saveSteps($pathway, $request->validated('steps'));

        $after = $this->pathwayConfig->steps($pathway);

        AuditService::log(
            action: 'update',
            description: match ($pathway) {
                PathwayStep::PATHWAY_MILITARY => 'تحديث ترقيم مسار المريض العسكري',
                PathwayStep::PATHWAY_ENTITY => 'تحديث ترقيم مسار جهات التعاقد',
                default => 'تحديث ترقيم مسار المريض المدني',
            },
            tag: 'admin',
            before: $before,
            after: $after,
        );

        return response()->json([
            'message' => 'تم حفظ إعدادات المسار.',
            'pathway' => $pathway,
            'steps' => $after,
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $pathway = $request->validate([
            'pathway' => ['required', 'string', 'in:civilian,military,entity'],
        ])['pathway'];

        $before = $this->pathwayConfig->steps($pathway);
        $this->pathwayConfig->resetToDefaults($pathway);
        $after = $this->pathwayConfig->steps($pathway);

        AuditService::log(
            action: 'update',
            description: 'استعادة الإعدادات الافتراضية لمسار '.$this->pathwayConfig->pathwayLabel($pathway),
            tag: 'admin',
            before: $before,
            after: $after,
        );

        return response()->json([
            'message' => 'تمت استعادة الإعدادات الافتراضية.',
            'pathway' => $pathway,
            'steps' => $after,
        ]);
    }
}
