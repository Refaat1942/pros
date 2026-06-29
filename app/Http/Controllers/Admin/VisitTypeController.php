<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderVisitTypesRequest;
use App\Http\Requests\Admin\StoreVisitTypeRequest;
use App\Http\Requests\Admin\UpdateVisitTypeRequest;
use App\Models\VisitType;
use App\Services\AuditService;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
                ->ordered()
                ->get(['id', 'name', 'sort_order']);

            return response()->json(['data' => $types]);
        }

        $types = $this->fetchForDashboard(
            VisitType::when(
                $request->search,
                fn ($q, $s) => $q->where('name', 'like', "%{$s}%")
            )
                ->ordered()
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
            'name'       => $data['name'],
            'sort_order' => (int) (VisitType::max('sort_order') ?? 0) + 10,
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

    /**
     * حفظ ترتيب أنواع الزيارات بعد السحب والإفلات.
     */
    public function reorder(ReorderVisitTypesRequest $request): JsonResponse
    {
        $ids = $request->validated('ids');

        DB::transaction(function () use ($ids) {
            foreach ($ids as $index => $id) {
                VisitType::whereKey($id)->update(['sort_order' => ($index + 1) * 10]);
            }
        });

        AuditService::log(
            action:      'update',
            description: 'إعادة ترتيب أنواع الزيارات',
            tag:         'admin',
            after:       ['order' => $ids],
        );

        return response()->json(['message' => 'تم حفظ الترتيب بنجاح.']);
    }
}
