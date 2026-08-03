<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use App\Services\PermissionCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * مصفوفة الصلاحيات التفصيلية — للسوبر أدمن فقط.
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
        /** @var User|null $actor */
        $actor = Auth::user();

        if (! $actor?->isSuperAdmin()) {
            return redirect()
                ->route('admin.permissions')
                ->with('error', 'فقط حساب السوبر أدمن (superadmin) يمكنه حفظ مصفوفة الصلاحيات.');
        }

        app(PermissionCatalogService::class)->syncToDatabase();

        $matrix = $this->parseMatrix($request);

        $validPermissionIds = Permission::pluck('id')->all();

        Role::query()
            ->where('slug', '!=', Role::SLUG_SUPER_ADMIN)
            ->get()
            ->each(function (Role $role) use ($matrix, $validPermissionIds) {
                $ids = array_values(array_intersect(
                    $this->permissionIdsForRole($matrix, $role->id),
                    $validPermissionIds,
                ));

                $role->permissions()->sync($ids);
            });

        AuditService::log(
            action: 'update',
            description: 'تحديث مصفوفة الصلاحيات التفصيلية',
            tag: 'admin',
            after: ['roles' => array_keys($matrix)],
        );

        $message = 'تم حفظ مصفوفة الصلاحيات بنجاح.';

        return redirect()
            ->route('admin.permissions')
            ->with('success', $message)
            ->with('status', $message);
    }

    /**
     * @return array<int|string, list<int>>
     */
    private function parseMatrix(Request $request): array
    {
        $json = $request->input('matrix_json');
        if (is_string($json) && $json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return (array) $request->input('matrix', []);
    }

    /**
     * @param  array<int|string, mixed>  $matrix
     * @return list<int>
     */
    private function permissionIdsForRole(array $matrix, int $roleId): array
    {
        $raw = $matrix[$roleId] ?? $matrix[(string) $roleId] ?? [];

        return array_values(array_map('intval', (array) $raw));
    }
}
