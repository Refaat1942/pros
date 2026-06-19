<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MilitaryRank;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * إدارة الرتب العسكرية — يختار منها الاستقبال عند تسجيل المريض العسكري.
 */
class MilitaryRankController extends Controller
{
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

        $ranks = MilitaryRank::when(
                $request->search,
                fn ($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('rank_code', 'like', "%{$s}%")
            )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20);

        return response()->json([
            'data'       => $ranks->items(),
            'pagination' => [
                'total'        => $ranks->total(),
                'per_page'     => $ranks->perPage(),
                'current_page' => $ranks->currentPage(),
                'last_page'    => $ranks->lastPage(),
            ],
        ]);
    }

    /**
     * إضافة رتبة جديدة.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'rank_code'  => ['required', 'string', 'max:30', 'unique:military_ranks,rank_code'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

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

        return response()->json($rank, 201);
    }

    /**
     * تعديل اسم أو كود الرتبة.
     */
    public function update(Request $request, MilitaryRank $militaryRank): JsonResponse
    {
        $data = $request->validate([
            'name'       => ['sometimes', 'string', 'max:100'],
            'rank_code'  => ['sometimes', 'string', 'max:30', "unique:military_ranks,rank_code,{$militaryRank->id}"],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

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
    public function toggleActive(MilitaryRank $militaryRank): JsonResponse
    {
        $militaryRank->update(['is_active' => ! $militaryRank->is_active]);

        return response()->json([
            'id'        => $militaryRank->id,
            'is_active' => $militaryRank->is_active,
        ]);
    }
}
