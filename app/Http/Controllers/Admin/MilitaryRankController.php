<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMilitaryRankRequest;
use App\Http\Requests\Admin\UpdateMilitaryRankRequest;
use App\Models\MilitaryRank;
use App\Services\AuditService;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * إدارة الرتب العسكرية — يختار منها الاستقبال عند تسجيل المريض العسكري.
 */
class MilitaryRankController extends Controller
{
    use PaginationTrait;

    /**
     * قائمة الرتب (مع pagination للأدمن، أو كل الرتب للـ select).
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->boolean('all')) {
            $ranks = MilitaryRank::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'sort_order']);

            return response()->json(['data' => $ranks]);
        }

        $ranks = $this->fetchForDashboard(
            MilitaryRank::when(
                    $request->search,
                    fn ($q, $s) => $q->where('name', 'like', "%{$s}%")
                )
                ->orderByDesc('id')
        );

        return response()->json([
            'data'  => $ranks,
            'total' => $ranks->count(),
        ]);
    }

    /**
     * إضافة رتبة جديدة.
     */
    public function store(StoreMilitaryRankRequest $request): RedirectResponse|JsonResponse
    {
        $data = $request->validated();

        $rank = MilitaryRank::create([
            'name'       => $data['name'],
            'rank_code'  => null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        AuditService::log(
            action:      'create',
            description: "إضافة رتبة عسكرية: {$rank->name}",
            tag:         'admin',
            after:       $rank->toArray(),
        );

        if ($request->expectsJson()) {
            return response()->json($rank, 201);
        }

        return redirect()
            ->route('admin.military-ranks')
            ->with('success', "تم إضافة الرتبة «{$rank->name}» بنجاح.");
    }

    /**
     * تعديل اسم أو ترتيب الرتبة.
     */
    public function update(UpdateMilitaryRankRequest $request, MilitaryRank $militaryRank): JsonResponse
    {
        $data = $request->validated();

        $before = $militaryRank->only(['name', 'sort_order']);
        $militaryRank->update($data);

        AuditService::log(
            action:      'update',
            description: "تعديل رتبة عسكرية: {$militaryRank->name}",
            tag:         'admin',
            before:      $before,
            after:       $militaryRank->fresh()->only(['name', 'sort_order']),
        );

        return response()->json([
            'message' => 'تم تحديث الرتبة بنجاح.',
            'rank'    => $militaryRank->fresh(),
        ]);
    }

    public function destroy(MilitaryRank $militaryRank): JsonResponse
    {
        if ($militaryRank->patients()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف الرتبة — مرتبطة بمرضى مسجّلين.',
            ], 422);
        }

        $before = $militaryRank->only(['name', 'sort_order']);
        $militaryRank->delete();

        AuditService::log(
            action:      'delete',
            description: "حذف رتبة عسكرية: {$before['name']}",
            tag:         'admin',
            before:      $before,
        );

        return response()->json(['message' => 'تم حذف الرتبة بنجاح.']);
    }
}
