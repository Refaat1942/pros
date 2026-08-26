<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\ResolveSupplyRequestLineRequest;
use App\Http\Requests\Stock\StoreSupplyRequestLineRequest;
use App\Models\SupplyRequestLine;
use App\Models\User;
use App\Services\SupplyRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupplyRequestController extends Controller
{
    public function __construct(private readonly SupplyRequestService $supplyRequestService) {}

    public function index(): JsonResponse
    {
        $lines = $this->supplyRequestService->listOpenLines();

        return response()->json([
            'data' => $lines->map(fn (SupplyRequestLine $line) => $this->supplyRequestService->formatLine($line))->values(),
            'total' => $lines->count(),
        ]);
    }

    public function store(StoreSupplyRequestLineRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $line = $this->supplyRequestService->createLine($request->validated(), $user);

        return response()->json([
            'message' => 'تم تسجيل طلب التوريد.',
            'line' => $this->supplyRequestService->formatLine($line),
        ], 201);
    }

    public function resolve(SupplyRequestLine $supplyRequestLine, ResolveSupplyRequestLineRequest $request): JsonResponse
    {
        $line = $this->supplyRequestService->resolveNonCatalogLine(
            $supplyRequestLine,
            (int) $request->validated('stock_item_id'),
        );

        return response()->json([
            'message' => 'تم ربط البند بصنف الكتالوج — جاهز للاستلام.',
            'line' => $this->supplyRequestService->formatLine($line),
        ]);
    }

    public function searchItems(Request $request): JsonResponse
    {
        $q = (string) $request->query('q', '');
        $limit = (int) $request->query('limit', 30);

        return response()->json([
            'data' => $this->supplyRequestService->searchCatalogItems($q, $limit),
        ]);
    }
}
