<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
        /** @var User|null $actor */
        $actor = Auth::user();
        $user->loadMissing('role:id,slug');

        if ($user->role?->slug === Role::SLUG_SUPER_ADMIN) {
            return redirect()
                ->route('admin.employees')
                ->with('error', 'لا يمكن تعطيل حساب السوبر أدمن.');
        }

        if ($user->role?->slug === Role::SLUG_ADMIN && ! $actor?->isSuperAdmin()) {
            return redirect()
                ->route('admin.employees')
                ->with('error', 'لا يمكن تعطيل مسؤول النظام — السوبر أدمن فقط.');
        }

        $user = $this->userService->toggleStatus($user);

        $label = $user->status === User::STATUS_ACTIVE ? 'تفعيل' : 'تعطيل';

        return redirect()
            ->route('admin.employees')
            ->with('success', "تم {$label} حساب {$user->name}.");
    }

    public function destroy(User $user): JsonResponse
    {
        /** @var User|null $actor */
        $actor = Auth::user();

        if ($actor?->id === $user->id) {
            return response()->json(['message' => 'لا يمكن حذف حسابك الحالي.'], 422);
        }

        $user->loadMissing('role:id,slug');

        if ($user->role?->slug === Role::SLUG_SUPER_ADMIN) {
            return response()->json(['message' => 'لا يمكن حذف حساب السوبر أدمن.'], 422);
        }

        if ($user->role?->slug === Role::SLUG_ADMIN && ! $actor?->isSuperAdmin()) {
            return response()->json(['message' => 'لا يمكن حذف مسؤول النظام — السوبر أدمن فقط.'], 422);
        }

        $this->userService->delete($user);

        return response()->json(['message' => 'تم حذف الموظف بنجاح.']);
    }
}
