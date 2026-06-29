<?php

namespace App\Http\Controllers\Adjustments;

use App\Http\Controllers\Controller;
use App\Services\AdjustmentsTransferHistoryService;
use App\Support\ExportCsvFormat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdjustmentsHistoryController extends Controller
{
    public function __construct(
        private readonly AdjustmentsTransferHistoryService $historyService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $dates = $this->historyService->parseDateRange(
            $request->query('from'),
            $request->query('to'),
        );

        $search = $request->query('search');
        $rows   = $this->historyService->list($dates['from'], $dates['to'], $search);

        return response()->json([
            'data'       => $rows,
            'stats'      => $this->historyService->stats($dates['from'], $dates['to'], $search),
            'date_from'  => $dates['from']->toDateString(),
            'date_to'    => $dates['to']->toDateString(),
            'total'      => count($rows),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $dates = $this->historyService->parseDateRange(
            $request->query('from'),
            $request->query('to'),
        );

        $report = $this->historyService->exportReport(
            $dates['from'],
            $dates['to'],
            $request->query('search'),
        );

        $filename = 'adjustments-to-costing-' . $dates['from']->format('Y-m-d') . '_' . $dates['to']->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($report) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, [$report['title']]);
            fputcsv($out, [$report['period_label']]);
            fputcsv($out, []);
            fputcsv($out, ExportCsvFormat::row($report['headers']));
            foreach ($report['rows'] as $row) {
                fputcsv($out, ExportCsvFormat::row($row));
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }
}
