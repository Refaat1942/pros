<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateFormFieldSettingsRequest;
use App\Services\AuditService;
use App\Services\FormFieldPolicyService;
use Illuminate\Http\JsonResponse;

class FormFieldSettingsController extends Controller
{
    public function __construct(private readonly FormFieldPolicyService $fieldPolicy) {}

    public function update(UpdateFormFieldSettingsRequest $request): JsonResponse
    {
        $before = $this->fieldPolicy->all();
        $fields = $request->validated('fields');

        $this->fieldPolicy->update($fields);
        $after = $this->fieldPolicy->all();

        AuditService::log(
            action: 'update',
            description: 'تحديث إلزامية حقول النماذج',
            tag: 'admin',
            before: $before,
            after: $after,
        );

        return response()->json([
            'message' => 'تم حفظ إعدادات الحقول.',
            'fields' => $this->fieldPolicy->catalogForAdmin(),
        ]);
    }
}
