<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\WorkshopTechnicianService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class WorkshopTechnicianController extends Controller
{
    public function __construct(
        private readonly WorkshopTechnicianService $technicians,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->technicians->listForAdmin(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'alpha_dash',
                Rule::unique('users', 'username'),
            ],
            'password' => ['required', 'string', 'min:6'],
            'status' => ['nullable', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])],
            'section_ids' => ['nullable', 'array'],
            'section_ids.*' => ['integer', 'exists:workshop_sections,id'],
        ], [
            'username.unique' => 'اسم المستخدم مستخدم مسبقاً.',
            'username.alpha_dash' => 'اسم المستخدم: حروف إنجليزية وأرقام و _ و - فقط.',
        ]);

        $user = $this->technicians->create(
            $validated,
            $validated['section_ids'] ?? [],
        );

        $technician = collect($this->technicians->listForAdmin())->firstWhere('id', $user->id);

        return response()->json([
            'message' => 'تم إضافة الفني.',
            'technician' => $technician,
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:255'],
            'username' => [
                'sometimes',
                'string',
                'min:3',
                'max:50',
                'alpha_dash',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', 'min:6'],
            'status' => ['nullable', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])],
            'section_ids' => ['nullable', 'array'],
            'section_ids.*' => ['integer', 'exists:workshop_sections,id'],
        ], [
            'username.unique' => 'اسم المستخدم مستخدم مسبقاً.',
            'username.alpha_dash' => 'اسم المستخدم: حروف إنجليزية وأرقام و _ و - فقط.',
        ]);

        $sectionIds = array_key_exists('section_ids', $validated)
            ? ($validated['section_ids'] ?? [])
            : null;

        unset($validated['section_ids']);

        $this->technicians->update($user, $validated, $sectionIds);

        return response()->json(['message' => 'تم تحديث بيانات الفني.']);
    }

    public function destroy(User $user): JsonResponse
    {
        if (Auth::id() === $user->id) {
            return response()->json(['message' => 'لا يمكن حذف حسابك الحالي.'], 422);
        }

        $user->loadMissing('role:id,slug');

        if ($user->role?->slug !== Role::SLUG_WORKSHOP) {
            abort(404);
        }

        $this->technicians->delete($user);

        return response()->json(['message' => 'تم حذف الفني.']);
    }
}
