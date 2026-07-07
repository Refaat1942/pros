<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Reports\Concerns\RendersAdminDashboard;
use App\Services\BiReportService;
use Illuminate\View\View;

class BiController extends Controller
{
    use RendersAdminDashboard;

    public function __construct(private readonly BiReportService $biReportService) {}

    /**
     * لوحات القيادة الخمس — بيانات حقيقية من الخدمة.
     */
    public function index(): View
    {
        return $this->adminPage('bi', [
            'board1' => $this->biReportService->boardPatients(),
            'board2' => $this->biReportService->boardInventory(),
            'board3' => $this->biReportService->boardOperations(),
            'board4' => $this->biReportService->boardEntitiesAndCosts(),
            'board5' => $this->biReportService->boardPurchasing(),
        ]);
    }
}
