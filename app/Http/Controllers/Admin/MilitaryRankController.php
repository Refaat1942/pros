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
     * قائمة الرتب (مع pagination للأدمن، أو كل الرتب الفعّالة للـ select).
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->boolean('all')) {
            $ranks = MilitaryRank::active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'rank_code', 'sort_order']);

            return response()->json(['data' => $ranks]);
        }

        $ranks = $this->fetchForDashboard(
            MilitaryRank::when(
                    $request->search,
                    fn ($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('rank_code', 'like', "%{$s}%")
                )
                ->orderBy('sort_order')
                ->orderBy('name')
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
            'rank_code'  => strtoupper($data['rank_code']),
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active'  => true,
        ]);

        AuditService::log(
            action:      'create',
            description: "إضافة رتبة عسكرية: {$rank->name} ({$rank->rank_code})",
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
     * تعديل اسم أو كود الرتبة.
     */
    public function update(UpdateMilitaryRankRequest $request, MilitaryRank $militaryRank): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['rank_code'])) {
            $data['rank_code'] = strtoupper($data['rank_code']);
        }

        $before = $militaryRank->only(['name', 'rank_code', 'sort_order']);
        $militaryRank->update($data);

        AuditService::log(
            action:      'update',
            description: "تعديل رتبة عسكرية: {$militaryRank->rank_code}",
            tag:         'admin',
            before:      $before,
            after:       $militaryRank->fresh()->only(['name', 'rank_code', 'sort_order']),
        );

        return response()->json($militaryRank->fresh());
    }

    /**
     * تفعيل / تعطيل الرتبة.
     */
    public function toggleActive(Request $request, MilitaryRank $militaryRank): RedirectResponse|JsonResponse
    {
        $militaryRank->update(['is_active' => ! $militaryRank->is_active]);

        if ($request->expectsJson()) {
            return response()->json([
                'id'        => $militaryRank->id,
                'is_active' => $militaryRank->is_active,
            ]);
        }

        return redirect()
            ->route('admin.military-ranks')
            ->with('success', 'تم تحديث حالة الرتبة.');
    }
}
