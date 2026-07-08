<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateWorkflowPoliciesRequest;
use App\Models\WorkflowStagePolicy;
use App\Services\AuditService;
use App\Services\WorkflowPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowSettingsController extends Controller
{
    public function __construct(private readonly WorkflowPolicyService $policies) {}

    public function update(UpdateWorkflowPoliciesRequest $request): JsonResponse
    {
        $pathway = $request->validated('pathway');
        $before = $this->policies->policies($pathway);

        $this->policies->savePolicies($pathway, $request->validated('policies'));

        $after = $this->policies->policies($pathway);

        AuditService::log(
            action: 'update',
            description: $pathway === WorkflowStagePolicy::PATHWAY_MILITARY
                ? 'تحديث سياسات تدفق المسار العسكري'
                : 'تحديث سياسات تدفق المسار المدني',
            tag: 'admin',
            before: $before,
            after: $after,
        );

        return response()->json([
            'message' => 'تم حفظ قواعد التدفق.',
            'pathway' => $pathway,
            'policies' => $after,
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $pathway = $request->validate([
            'pathway' => ['required', 'string', 'in:civilian,military'],
        ])['pathway'];

        $before = $this->policies->policies($pathway);
        $this->policies->resetToDefaults($pathway);
        $after = $this->policies->policies($pathway);

        AuditService::log(
            action: 'update',
            description: 'استعادة سياسات التدفق الافتراضية — '.$pathway,
            tag: 'admin',
            before: $before,
            after: $after,
        );

        return response()->json([
            'message' => 'تمت استعادة قواعد التدفق الافتراضية.',
            'pathway' => $pathway,
            'policies' => $after,
        ]);
    }
}
