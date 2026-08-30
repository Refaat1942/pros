<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateDocumentTemplateRequest;
use App\Models\CaseRecord;
use App\Models\CustomDocument;
use App\Services\AuditService;
use App\Services\CustomDocumentService;
use App\Services\DocumentTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DocumentTemplateController extends Controller
{
    public function __construct(
        private readonly DocumentTemplateService $templates,
        private readonly CustomDocumentService $customDocuments,
    ) {}

    public function edit(string $document): View
    {
        abort_unless($this->templates->exists($document), 404);

        $def = $this->templates->definition($document);
        $values = $this->templates->for($document);
        $pages = config('dashboards.admin.pages', []);
        $custom = $this->customDocuments->findByKey($document);

        return view('dashboard.show', [
            'dashboardKey' => 'admin',
            'activePage' => 'document-template-edit',
            'pageTitle' => 'تخصيص: '.$def['title'],
            'pageLabel' => $pages['document-template-edit']['label'] ?? 'تخصيص وثيقة',
            'documentKey' => $document,
            'documentTitle' => $def['title'],
            'documentDescription' => $def['description'],
            'fields' => $def['fields'],
            'values' => $values,
            'previewUrl' => route('admin.documents-hub.preview', $document),
            'hubUrl' => route('admin.documents-hub'),
            'isCustomDocument' => $custom instanceof CustomDocument,
            'customDocumentId' => $custom?->id,
            'referenceUrl' => $custom?->referenceUrl(),
            'referenceIsImage' => $custom?->referenceIsImage() ?? false,
        ]);
    }

    public function update(UpdateDocumentTemplateRequest $request, string $document): JsonResponse
    {
        abort_unless($this->templates->exists($document), 404);

        $before = $this->templates->for($document);
        $after = $this->templates->update($document, $request->validated());

        AuditService::log(
            action: 'update',
            description: "تخصيص قالب وثيقة: {$document}",
            tag: 'admin',
            before: $before,
            after: $after,
        );

        return response()->json([
            'message' => 'تم حفظ تخصيص الوثيقة.',
            'values' => $after,
        ]);
    }

    public function preview(string $document): View
    {
        abort_unless($this->templates->exists($document), 404);

        $tpl = $this->templates->for($document);
        $autoPrint = false;

        if ($this->templates->isCustom($document)) {
            $custom = $this->customDocuments->findByKey($document);

            return view('admin.print.custom-document-preview', [
                'documentTemplate' => $tpl,
                'customDocument' => $custom,
            ]);
        }

        return match ($document) {
            'issue_voucher' => view('prints.issue-voucher', [
                'voucher' => $this->sampleIssueVoucher(),
                'autoPrint' => $autoPrint,
                'documentTemplate' => $tpl,
            ]),
            'payment_receipt' => view('prints.payment-receipt', [
                'receipt' => $this->samplePaymentReceipt(),
                'autoPrint' => $autoPrint,
                'documentTemplate' => $tpl,
            ]),
            'supply_request_list' => view('prints.supply-request-list', [
                'lines' => $this->sampleSupplyLines(),
                'generatedAt' => now(),
                'autoPrint' => $autoPrint,
                'documentTemplate' => $tpl,
            ]),
            'work_order' => view('prints.work-order', [
                'case' => $this->sampleWorkOrderCase(),
                'autoPrint' => $autoPrint,
                'documentTemplate' => $tpl,
                'previewValueDisplay' => '15,000',
            ]),
            'quote' => view('admin.print.document-template-quote-preview', [
                'documentTemplate' => $tpl,
            ]),
            'spec_print' => view('admin.print.document-template-spec-preview', [
                'documentTemplate' => $tpl,
            ]),
            default => abort(404),
        };
    }

    /** @return array<string, mixed> */
    private function sampleIssueVoucher(): array
    {
        return [
            'voucher_no' => 'DEMO-001',
            'work_order_no' => 'WO-2026-0001',
            'case_no' => 'CASE-DEMO',
            'patient_name' => 'مريض تجريبي — معاينة القالب',
            'company_name' => 'جهة تجريبية',
            'written_items' => 'طرف صناعي ركبة — مواصفات تجريبية للمعاينة.',
            'tech_notes' => 'ملاحظة فنية تجريبية.',
            'technician_name' => 'فني تجريبي',
            'workshop_section_name' => 'قسم الإنتاج',
            'spec_groups' => [
                [
                    'label' => 'الطرف الصناعي',
                    'lines' => [
                        ['stock_item_code' => 'RM-001', 'name' => 'صنف تجريبي 1', 'qty' => 2],
                        ['stock_item_code' => 'RM-002', 'name' => 'صنف تجريبي 2', 'qty' => 1],
                    ],
                ],
            ],
            'items' => [
                ['stock_item_code' => 'RM-001', 'name' => 'صنف تجريبي 1', 'qty' => 2],
                ['stock_item_code' => 'RM-002', 'name' => 'صنف تجريبي 2', 'qty' => 1],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function samplePaymentReceipt(): array
    {
        return [
            'payment_no' => 'PAY-DEMO-001',
            'patient_name' => 'مريض تجريبي — معاينة',
            'patient_serial' => 'P-DEMO',
            'entity' => 'جهة تجريبية',
            'case_no' => 'CASE-DEMO',
            'order_ref' => 'REF-DEMO',
            'amount' => 15000,
            'amount_words' => 'خمسة عشر ألف جنيه مصري لا غير',
            'method_label' => 'نقدي',
            'received_at' => now()->format('d/m/Y H:i'),
            'received_by' => 'أمين الخزنة',
            'fully_paid' => true,
            'installment_label' => 'دفعة 1',
        ];
    }

    /** @return list<array<string, mixed>> */
    private function sampleSupplyLines(): array
    {
        return [
            [
                'request_no' => 'SR-26080001',
                'display_name' => 'صنف تجريبي — معاينة',
                'qty' => 5,
                'uom' => 'قطعة',
                'requested_at' => now()->format('d/m/Y H:i'),
                'received_at' => '—',
                'status_label' => 'بانتظار التوريد',
            ],
            [
                'request_no' => 'SR-26080002',
                'display_name' => 'صنف آخر',
                'qty' => 2,
                'uom' => 'زوج',
                'requested_at' => now()->subDay()->format('d/m/Y H:i'),
                'received_at' => '—',
                'status_label' => 'جاهز للاستلام',
            ],
        ];
    }

    private function sampleWorkOrderCase(): CaseRecord
    {
        $case = new CaseRecord([
            'case_no' => 'CASE-DEMO',
            'order_ref' => 'REF-DEMO',
            'work_order_no' => 'WO-2026-0001',
            'quote_no' => 'QT-DEMO',
            'approval_date' => now(),
            'patient_type' => 'civilian',
            'quote_total' => 15000,
        ]);
        $case->id = 0;

        $patient = new \App\Models\Patient(['name' => 'مريض تجريبي — معاينة']);
        $case->setRelation('patient', $patient);

        $bom = new \App\Models\Bom(['bom_no' => 'BOM-DEMO', 'patient_name' => 'مريض تجريبي']);
        $items = collect([
            new \App\Models\BomItem(['stock_item_code' => 'RM-001', 'name' => 'صنف تجريبي', 'qty' => 1]),
        ]);
        $bom->setRelation('items', $items);
        $case->setRelation('bom', $bom);

        return $case;
    }
}
