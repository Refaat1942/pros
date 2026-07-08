<?php

namespace App\Http\Controllers\Assistant;

use App\Http\Controllers\Controller;
use App\Services\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * المساعد الذكي الإرشادي — بحث نصّي داخل قاعدة معرفة أوفلاين بالعامية،
 * مقيَّد بصلاحيات المستخدم الحالي واللوحة/الصفحة اللي هو فيها.
 */
class AssistantController extends Controller
{
    public function __construct(private readonly AssistantService $assistant) {}

    public function search(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user === null) {
            return response()->json(['results' => []], 401);
        }

        $query = trim((string) $request->query('q', ''));
        $dashboard = $request->filled('dashboard') ? (string) $request->query('dashboard') : null;
        $page = $request->filled('page') ? (string) $request->query('page') : null;

        $results = $query === ''
            ? $this->assistant->suggestions($user, $dashboard, $page)
            : $this->assistant->search($user, $query, $dashboard, $page);

        return response()->json([
            'query' => $query,
            'results' => $results,
        ]);
    }
}
