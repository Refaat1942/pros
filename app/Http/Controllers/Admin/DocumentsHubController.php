<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CustomDocumentService;
use App\Services\DocumentTemplateService;
use Illuminate\View\View;

class DocumentsHubController extends Controller
{
    public function __construct(private readonly DocumentTemplateService $templates) {}

    public function index(): View
    {
        $pages = config('dashboards.admin.pages', []);

        return view('dashboard.show', [
            'dashboardKey' => 'admin',
            'activePage' => 'documents-hub',
            'pageTitle' => $pages['documents-hub']['title'] ?? 'مركز الوثائق',
            'pageLabel' => $pages['documents-hub']['label'] ?? 'مركز الوثائق',
            'documents' => $this->templates->hubGroups(),
            'customDocumentsTableReady' => app(CustomDocumentService::class)->tableReady(),
        ]);
    }
}
