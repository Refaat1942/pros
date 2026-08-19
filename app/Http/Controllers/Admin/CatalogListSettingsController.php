<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCatalogListSettingsRequest;
use App\Services\AuditService;
use App\Services\CatalogListVisibilityService;
use Illuminate\Http\JsonResponse;

class CatalogListSettingsController extends Controller
{
    public function __construct(private readonly CatalogListVisibilityService $visibility) {}

    public function update(UpdateCatalogListSettingsRequest $request): JsonResponse
    {
        $before = $this->visibility->all();

        $this->visibility->update($request->validated());
        $after = $this->visibility->all();

        AuditService::log(
            action: 'update',
            description: 'تحديث إعدادات عرض قوائم الأصناف',
            tag: 'admin',
            before: $before,
            after: $after,
        );

        return response()->json([
            'message' => 'تم حفظ إعدادات عرض قوائم الأصناف.',
            'roles' => $this->visibility->catalogForAdmin(),
        ]);
    }
}
