<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVisitTypeRequest;
use App\Http\Requests\Admin\UpdateVisitTypeRequest;
use App\Models\VisitType;
use App\Services\AuditService;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * إدارة أنواع الزيارات — يختار منها الاستقبال عند جدولة المريض.
 */
class VisitTypeController extends Controller
{
    use PaginationTrait;

    public function index(Request $request): JsonResponse
    {
        if ($request->boolean('all')) {
            $types = VisitType::query()
                ->orderBy('name')
                ->get(['id', 'name']);

            return response()->json(['data' => $types]);
        }

        $types = $this->fetchForDashboard(
            VisitType::when(
                $request->search,
                fn ($q, $s) => $q->where('name', 'like', "%{$s}%")
            )
                ->orderByDesc('id')
        );

        return response()->json([
            'data'  => $types,
            'total' => $types->count(),
        ]);
    }

    public function store(StoreVisitTypeRequest $request): RedirectResponse|JsonResponse
    {
        $data = $request->validated();

        $type = VisitType::create([
            'name' => $data['name'],
        ]);

        AuditService::log(
            action:      'create',
            description: "إضافة نوع زيارة: {$type->name}",
            tag:         'admin',
            after:       $type->toArray(),
        );

        if ($request->expectsJson()) {
            return response()->json($type, 201);
        }

        return redirect()
            ->route('admin.visit-types')
            ->with('success', "تم إضافة نوع الزيارة «{$type->name}» بنجاح.");
    }

    public function update(UpdateVisitTypeRequest $request, VisitType $visitType): JsonResponse
    {
        $data = $request->validated();

        $before = $visitType->only(['name']);
        $visitType->update($data);

        AuditService::log(
            action:      'update',
            description: "تعديل نوع زيارة: {$visitType->name}",
            tag:         'admin',
            before:      $before,
            after:       $visitType->fresh()->only(['name']),
        );

        return response()->json([
            'message'    => 'تم تحديث نوع الزيارة بنجاح.',
            'visit_type' => $visitType->fresh(),
        ]);
    }

    public function destroy(VisitType $visitType): JsonResponse
    {
        if ($visitType->appointments()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف نوع الزيارة — مرتبط بمواعيد مسجّلة.',
            ], 422);
        }

        $before = $visitType->only(['name']);
        $visitType->delete();

        AuditService::log(
            action:      'delete',
            description: "حذف نوع زيارة: {$before['name']}",
            tag:         'admin',
            before:      $before,
        );

        return response()->json(['message' => 'تم حذف نوع الزيارة بنجاح.']);
    }
}
