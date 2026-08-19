<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\CatalogListVisibilityService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function __construct(private readonly UserService $userService) {}

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->userService->create($request->validated());

        return redirect()
            ->route('admin.employees')
            ->with('success', 'تم إضافة الموظف بنجاح.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->userService->update($user, $request->validated());

        return redirect()
            ->route('admin.employees')
            ->with('success', 'تم تحديث بيانات الموظف بنجاح.');
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        $user->loadMissing('role:id,slug');

        if (in_array($user->role?->slug, [Role::SLUG_ADMIN, Role::SLUG_SUPER_ADMIN], true)) {
            return redirect()
                ->route('admin.employees')
                ->with('error', 'لا يمكن تعطيل حساب السوبر أدمن أو مسؤول النظام.');
        }

        $user = $this->userService->toggleStatus($user);

        $label = $user->status === User::STATUS_ACTIVE ? 'تفعيل' : 'تعطيل';

        return redirect()
            ->route('admin.employees')
            ->with('success', "تم {$label} حساب {$user->name}.");
    }

    public function destroy(User $user): JsonResponse
    {
        if (Auth::id() === $user->id) {
            return response()->json(['message' => 'لا يمكن حذف حسابك الحالي.'], 422);
        }

        $user->loadMissing('role:id,slug');

        if (in_array($user->role?->slug, [Role::SLUG_ADMIN, Role::SLUG_SUPER_ADMIN], true)) {
            return response()->json(['message' => 'لا يمكن حذف حساب السوبر أدمن أو مسؤول النظام.'], 422);
        }

        $this->userService->delete($user);

        return response()->json(['message' => 'تم حذف الموظف بنجاح.']);
    }

    public function catalogListVisibilityDefaults(
        Request $request,
        CatalogListVisibilityService $visibility,
    ): JsonResponse {
        $role = Role::query()->findOrFail($request->integer('role_id'));
        $userStored = null;

        if ($userId = $request->integer('user_id')) {
            $user = User::query()->find($userId);
            $userStored = $user?->catalog_list_visibility;
        }

        return response()->json([
            'catalog' => $visibility->catalogForRole($role->slug, $userStored),
        ]);
    }
}
