<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * مصفوفة الصلاحيات التفصيلية — يضبط صلاحيات كل دور (عدا الأدمن).
 */
class PermissionMatrixController extends Controller
{
    /**
     * مزامنة صلاحيات الأدوار من نموذج المصفوفة.
     *
     * يتوقع الحقل matrix[role_id][] = permission_id.
     */
    public function update(Request $request): RedirectResponse
    {
        $matrix = (array) $request->input('matrix', []);

        $validPermissionIds = Permission::pluck('id')->all();

        Role::query()
            ->get()
            ->each(function (Role $role) use ($matrix, $validPermissionIds) {
                $ids = array_values(array_intersect(
                    array_map('intval', (array) ($matrix[$role->id] ?? [])),
                    $validPermissionIds,
                ));

                $role->permissions()->sync($ids);
            });

        AuditService::log(
            action:      'update',
            description: 'تحديث مصفوفة الصلاحيات التفصيلية',
            tag:         'admin',
            after:       ['roles' => array_keys($matrix)],
        );

        return back()->with('status', 'تم حفظ مصفوفة الصلاحيات بنجاح.');
    }
}
