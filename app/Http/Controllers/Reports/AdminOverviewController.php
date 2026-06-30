<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Reports\Concerns\RendersAdminDashboard;
use App\Services\AdminOverviewExportService;
use App\Services\AdminOverviewService;
use App\Services\AdminPatientTrackService;
use App\Support\ExportCsvFormat;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminOverviewController extends Controller
{
    use RendersAdminDashboard;

    public function __construct(
        private readonly AdminPatientTrackService $patientTrackService,
        private readonly AdminOverviewService $overview,
        private readonly AdminOverviewExportService $exporter,
    ) {
    }

    /**
     * نظرة عامة — دورة العمل + مؤشرات مالية ومخزون.
     */
    public function index(Request $request): View
    {
        $dates = $this->overview->parseDateRange(
            $request->query('from'),
            $request->query('to'),
        );

        return $this->adminPage('overview', $this->overview->pageData($dates['from'], $dates['to']));
    }

    public function export(Request $request): StreamedResponse
    {
        $dates = $this->overview->parseDateRange(
            $request->query('from'),
            $request->query('to'),
        );

        $report   = $this->exporter->build($dates['from'], $dates['to']);
        $filename = $this->exportFilename($dates['from'], $dates['to']);

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

            foreach ($report['sections'] as $section) {
                fputcsv($out, [$section['title']]);
                fputcsv($out, ExportCsvFormat::row($section['headers']));
                foreach ($section['rows'] as $row) {
                    fputcsv($out, ExportCsvFormat::row($row));
                }
                fputcsv($out, []);
            }

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    /** API: مسار المرضى النشطين */
    public function patientTracksApi(Request $request): JsonResponse
    {
        return response()->json(
            $this->patientTrackService->list(
                search: $request->query('search'),
                stage: $request->query('stage'),
                patientType: $request->query('patient_type'),
                visitType: $request->query('visit_type'),
            )->values()
        );
    }

    private function exportFilename(Carbon $from, Carbon $to): string
    {
        return 'نظرة_عامة_' . $from->format('Y-m-d') . '_' . $to->format('Y-m-d') . '.csv';
    }
}
