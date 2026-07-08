<?php

namespace App\Http\Controllers\Reception;

use App\Http\Controllers\Controller;
use App\Services\ReceptionScreenHintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceptionHintController extends Controller
{
    public function __construct(private readonly ReceptionScreenHintService $hints) {}

    public function show(Request $request): JsonResponse
    {
        $page = (string) $request->query('page', '');

        return response()->json($this->hints->hint($page));
    }
}
