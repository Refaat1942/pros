<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Services\ReceptionSelfServiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceptionSelfServiceController extends Controller
{
    public function __construct(private readonly ReceptionSelfServiceService $selfService)
    {
    }

    /**
     * استعلام بالهاتف / QR / كود المريض / الاسم — للوحة الاستقبال.
     */
    public function lookup(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if ($query === '') {
            return response()->json(['message' => 'يرجى إدخال رقم الهاتف أو كود المريض.'], 422);
        }

        $result = $this->selfService->lookup($query);

        if (! $result) {
            return response()->json(['message' => "لا توجد بيانات مطابقة لـ «{$query}»"], 404);
        }

        return response()->json($result);
    }
}
