<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\WorkshopTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkshopTrackingController extends Controller
{
    public function __construct(
        private readonly WorkshopTrackingService $tracking,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $payload = $this->tracking->trackingList(
            $request->integer('section_id') ?: null,
            $request->integer('technician_id') ?: null,
        );

        return response()->json($payload + [
            'total' => count($payload['data']),
        ]);
    }
}
