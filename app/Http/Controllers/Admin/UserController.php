<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;

class UserController extends Controller
{
    public function __construct(private readonly UserService $userService)
    {
    }

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
        $user = $this->userService->toggleStatus($user);

        $label = $user->status === User::STATUS_ACTIVE ? 'تفعيل' : 'تعطيل';

        return redirect()
            ->route('admin.employees')
            ->with('success', "تم {$label} حساب {$user->name}.");
    }
}
