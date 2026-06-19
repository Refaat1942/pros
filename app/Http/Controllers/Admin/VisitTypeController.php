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
            $types = VisitType::active()
                ->orderBy('name')
                ->get(['id', 'name']);

            return response()->json(['data' => $types]);
        }

        $types = $this->fetchForDashboard(
            VisitType::when(
                $request->search,
                fn ($q, $s) => $q->where('name', 'like', "%{$s}%")
            )
                ->orderBy('name')
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
            'name'      => $data['name'],
            'is_active' => true,
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
            description: "تعديل نوع زيارة #{$visitType->id}",
            tag:         'admin',
            before:      $before,
            after:       $visitType->fresh()->only(['name']),
        );

        return response()->json($visitType->fresh());
    }

    public function toggleActive(Request $request, VisitType $visitType): RedirectResponse|JsonResponse
    {
        $visitType->update(['is_active' => ! $visitType->is_active]);

        if ($request->expectsJson()) {
            return response()->json([
                'id'        => $visitType->id,
                'is_active' => $visitType->is_active,
            ]);
        }

        return redirect()
            ->route('admin.visit-types')
            ->with('success', 'تم تحديث حالة نوع الزيارة.');
    }
}
