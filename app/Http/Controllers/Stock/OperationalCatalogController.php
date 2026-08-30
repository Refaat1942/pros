<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Services\CatalogListVisibilityService;
use App\Services\StockCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * قائمة أصناف تشغيلية للقراءة فقط — بدون أسعار (حسب profile في config/catalog_lists.php).
 */
class OperationalCatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $profile = (string) ($request->route()?->defaults['profile'] ?? '');
        $profiles = config('catalog_lists.profiles', []);
        abort_unless($profile !== '' && isset($profiles[$profile]), 404);

        $catalogService = app(StockCatalogService::class);
        $visibility = app(CatalogListVisibilityService::class);
        $user = $request->user();

        abort_unless($user && $visibility->isListEnabledForUser($user, $profile), 403);

        return response()->json([
            'data' => collect($catalogService->listForTechnicalInventory($user, $profile))->values(),
            'total' => $catalogService->countAll(),
            'columns' => $visibility->tableOrderForUser($user, $profile),
        ]);
    }
}
