<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Services\PublicTrackingService;
use Illuminate\View\View;

/**
 * صفحة متابعة عامة — بدون مصادقة، بدون بيانات سرية.
 */
class PublicTrackingController extends Controller
{
    public function __construct(private readonly PublicTrackingService $publicTrackingService)
    {
    }

    public function show(string $uid): View
    {
        $tracking = $this->publicTrackingService->resolve($uid);

        return view('public.tracking_page', [
            'tracking' => $tracking,
        ]);
    }
}
