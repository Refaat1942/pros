<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\Controller;
use App\Http\Requests\Department\ResetDepartmentStaffPasswordRequest;
use App\Http\Requests\Department\StoreDepartmentStaffRequest;
use App\Http\Requests\Department\UpdateDepartmentStaffRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\CatalogListVisibilityService;
use App\Services\DepartmentStaffService;
use App\Services\UserPageAccessService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DepartmentStaffController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
        private readonly DepartmentStaffService $departmentStaff,
    ) {}

    public function store(StoreDepartmentStaffRequest $request): RedirectResponse
    {
        /** @var User $manager */
        $manager = Auth::user();
        $manager->loadMissing('role:id,slug');

        $data = $request->validated();
        $data['role_id'] = $manager->role_id;
        $data['access_tier'] = UserPageAccessService::TIER_DEPARTMENT_STAFF;

        $this->userService->create($data);

        return redirect()
            ->route($this->departmentStaff->staffRedirectRoute($manager))
            ->with('success', 'تم إضافة الموظف بنجاح.');
    }

    public function update(UpdateDepartmentStaffRequest $request, User $user): RedirectResponse
    {
        /** @var User $manager */
        $manager = Auth::user();
        $this->departmentStaff->assertCanManage($user);

        $data = $request->validated();
        $data['role_id'] = $manager->role_id;
        $data['access_tier'] = UserPageAccessService::TIER_DEPARTMENT_STAFF;

        $this->userService->update($user, $data);

        return redirect()
            ->route($this->departmentStaff->staffRedirectRoute($manager))
            ->with('success', 'تم تحديث بيانات الموظف بنجاح.');
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        $this->departmentStaff->assertCanManage($user);

        $user = $this->userService->toggleStatus($user);
        /** @var User $manager */
        $manager = Auth::user();
        $label = $user->status === User::STATUS_ACTIVE ? 'تفعيل' : 'تعطيل';

        return redirect()
            ->route($this->departmentStaff->staffRedirectRoute($manager))
            ->with('success', "تم {$label} حساب {$user->name}.");
    }

    public function destroy(User $user): JsonResponse
    {
        $this->departmentStaff->assertCanManage($user);

        $this->userService->delete($user);

        return response()->json(['message' => 'تم حذف الموظف بنجاح.']);
    }

    public function resetPassword(ResetDepartmentStaffPasswordRequest $request, User $user): JsonResponse
    {
        $this->departmentStaff->assertCanManage($user);

        $this->userService->resetPassword($user, $request->validated('password'));

        return response()->json(['message' => 'تم إعادة تعيين كلمة المرور بنجاح.']);
    }

    public function catalogListVisibility(
        Request $request,
        CatalogListVisibilityService $visibility,
    ): JsonResponse {
        /** @var User $manager */
        $manager = Auth::user();
        $manager->loadMissing('role:id,slug');

        $role = $manager->role;
        abort_unless($role !== null, 422, 'لا يوجد دور مرتبط بالحساب.');

        $userStored = null;
        if ($userId = $request->integer('user_id')) {
            $user = User::query()->find($userId);
            if ($user && $this->departmentStaff->canManage($manager, $user)) {
                $userStored = $user->catalog_list_visibility;
            }
        }

        return response()->json([
            'catalog' => $visibility->catalogForRole($role->slug, $userStored),
        ]);
    }

    public function rolePages(UserPageAccessService $access): JsonResponse
    {
        /** @var User $manager */
        $manager = Auth::user();
        $role = $manager->role;
        abort_unless($role !== null, 422);

        if (in_array($role->slug, [Role::SLUG_ADMIN, Role::SLUG_SUPER_ADMIN], true)) {
            return response()->json(['pages' => [], 'staff_defaults' => []]);
        }

        return response()->json([
            'pages' => $access->pagesForRole($role),
            'staff_defaults' => $access->defaultStaffPages($role->slug),
        ]);
    }
}
