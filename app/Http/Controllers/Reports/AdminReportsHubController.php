<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Reports\Concerns\RendersAdminDashboard;
use App\Services\AdminReportsHubService;
use App\Support\ExportCsvFormat;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportsHubController extends Controller
{
    use RendersAdminDashboard;

    public function __construct(private readonly AdminReportsHubService $hub) {}

    public function index(): View
    {
        return $this->adminPage('reports', [
            'report_sections' => $this->hub->sections(),
        ]);
    }

    public function show(Request $request, string $section): View
    {
        abort_unless($this->hub->sectionMeta($section), 404);

        $dates = $this->hub->parseDateRange(
            $request->query('from'),
            $request->query('to'),
        );

        return $this->adminPage('reports-section', [
            'report_section' => $section,
            'section_meta' => $this->hub->sectionMeta($section),
            'report_data' => $this->hub->build($section, $dates['from'], $dates['to']),
            'date_from' => $dates['from']?->toDateString() ?? '',
            'date_to' => $dates['to']?->toDateString() ?? '',
        ]);
    }

    public function export(Request $request, string $section): StreamedResponse
    {
        abort_unless($this->hub->sectionMeta($section), 404);

        $dates = $this->hub->parseDateRange(
            $request->query('from'),
            $request->query('to'),
        );

        try {
            $report = $this->hub->build($section, $dates['from'], $dates['to']);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        $filename = $this->exportFilename(
            $report['title'] ?? $this->hub->sectionMeta($section)['label'] ?? 'تقرير',
            $dates['from'],
            $dates['to'],
        );

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($report) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, [$report['title'] ?? 'تقرير']);
            fputcsv($out, [$report['period_label'] ?? '']);
            fputcsv($out, []);
            fputcsv($out, ExportCsvFormat::row($report['headers'] ?? []));
            foreach ($report['rows'] ?? [] as $row) {
                fputcsv($out, ExportCsvFormat::row($row));
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportFilename(string $title, ?Carbon $from, ?Carbon $to): string
    {
        $base = preg_replace('/\s+/u', '_', trim($title));
        $base = preg_replace('/[^\p{L}\p{N}_-]+/u', '', $base) ?: 'تقرير';

        $suffix = ($from && $to)
            ? $from->format('Y-m-d').'_'.$to->format('Y-m-d')
            : 'all';

        return $base.'_'.$suffix.'.csv';
    }
}
