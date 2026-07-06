@php
    use App\Services\SpecEditRequestService;
    use App\Support\ClinicTime;

    $specs = collect($submitted_specs ?? []);
    $stats = $spec_edit_stats ?? ['total' => 0, 'editable' => 0, 'pending' => 0, 'rejected' => 0];
    $editService = app(SpecEditRequestService::class);
    $submittedAtLabel = fn ($spec) => $spec->updated_at
        ? ClinicTime::format($spec->updated_at)
        : ($spec->submitted_at ? ClinicTime::format($spec->submitted_at, 'd/m/Y') : '—');

    $exportRows = $specs->map(function ($spec) use ($editService, $submittedAtLabel) {
        $pending = $spec->pendingEditRequest;
        $rejected = $spec->rejectedSpecEditRequest;
        $canEdit = $editService->canRequestEdit($spec);

        if ($pending) {
            $status = 'طلب تعديل معلّق';
        } elseif ($rejected) {
            $status = 'رُفض طلب التعديل';
        } elseif ($canEdit) {
            $status = 'قابل للتعديل';
        } else {
            $status = 'مُرسَل';
        }

        $itemsSummary = $spec->items
            ->map(fn ($i) => ($i->stock_item_code ?: '—') . ' × ' . $i->qty)
            ->implode(' | ');

        return [
            'patient'       => $spec->patient_name,
            'case_no'       => $spec->caseRecord?->case_no ?? '—',
            'order_ref'     => $spec->order_ref,
            'submitted_at'  => $submittedAtLabel($spec),
            'items_count'   => $spec->items->count(),
            'status'        => $status,
            'items_summary' => $itemsSummary ?: '—',
            'search'        => strtolower(trim(implode(' ', [
                $spec->patient_name,
                $spec->order_ref,
                $spec->caseRecord?->case_no,
                $status,
                $itemsSummary,
            ]))),
        ];
    })->values();
@endphp

@push('styles')
<style>
    .spec-preview-toolbar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; padding:0 24px 16px; }
    .spec-preview-toolbar .toolbar-count { margin-right:auto; font-size:13px; color:var(--text-muted); font-weight:700; }
    .spec-preview-items-source { display:none !important; }
    .spec-preview-detail-grid { display:grid; gap:16px; }
    .spec-preview-detail-grid--dual { grid-template-columns:1fr; }
    @media (min-width: 992px) { .spec-preview-detail-grid--dual { grid-template-columns:1fr 1fr; } }
    .spec-preview-detail-label { margin:0 0 8px; font-size:12px; font-weight:800; color:var(--text-muted); }
    .spec-preview-detail-label.is-pending { color:#6d28d9; }
    .spec-preview-detail-label.is-rejected { color:#b91c1c; }
    .spec-preview-detail-label span { font-weight:500; }
    .spec-preview-detail-wrap--pending .bom-table thead { background:#f5f3ff; }
    .spec-preview-detail-wrap--rejected .bom-table thead { background:#fef2f2; }
    .spec-preview-note { margin:10px 0 0; padding:10px 12px; border-radius:10px; font-size:13px; line-height:1.6; }
    .spec-preview-note.is-muted { background:#f1f5f9; color:#475569; }
    .spec-preview-note.is-pending { background:#f5f3ff; border:1px solid #ede9fe; color:#5b21b6; }
    .spec-preview-note.is-rejected { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }
    .spec-status-badge { display:inline-flex; align-items:center; gap:4px; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; white-space:nowrap; }
    .spec-status-badge--sent { background:#ecfdf5; color:#047857; }
    .spec-status-badge--editable { background:#f5f3ff; color:#6d28d9; }
    .spec-status-badge--pending { background:#fffbeb; color:#b45309; }
    .spec-status-badge--rejected { background:#fef2f2; color:#b91c1c; }
    .spec-preview-actions { display:flex; flex-wrap:wrap; gap:6px; justify-content:flex-end; }
</style>
@endpush

<div class="section-view" id="section-spec-preview">
    <div id="analytics-spec-preview">
        @include('partials.dashboard-analytics-empty', [
            'stats' => $spec_preview_stats ?? [],
            'hide_charts' => true,
        ])
    </div>

    <div class="panel inventory-wrap" id="specPreviewRoot">
        <div class="panel-header">
            <div>
                <h3>👁️ معاينة التوصيفات المُرسَلة</h3>
                <p style="margin:4px 0 0;font-size:12px;color:var(--text-muted);">
                    جدول التوصيفات — يمكن طلب التعديل أثناء وجود الحالة في المعدلات (موافقة الإدارة)
                </p>
            </div>
            <span class="badge" id="specPreviewCount">{{ $specs->count() }} توصيف</span>
        </div>

        <div class="spec-preview-toolbar data-toolbar">
            <input type="search" id="specPreviewSearch" class="form-control table-search-input"
                   placeholder="🔍 بحث بالمريض أو رقم الحالة أو الصنف..." autocomplete="off">
            <div class="export-btns">
                <button type="button" class="btn-export excel" id="btnSpecPreviewExcel">📊 Excel</button>
                <button type="button" class="btn-export pdf" id="btnSpecPreviewPdf">📄 PDF</button>
            </div>
            <span class="toolbar-count" id="specPreviewVisibleCount">{{ $specs->count() }} ظاهر</span>
        </div>

        <div class="bom-table-wrap">
            <table data-paginate="10" class="bom-table" id="specPreviewTable">
                <thead>
                    <tr>
                        <th>اسم المريض</th>
                        <th>رقم الحالة</th>
                        <th>مرجع الطلب</th>
                        <th>تاريخ الإرسال</th>
                        <th>عدد الأصناف</th>
                        <th>الحالة</th>
                        <th class="col-actions">إجراء</th>
                    </tr>
                </thead>
                <tbody id="specPreviewTableBody">
                    @forelse ($specs as $spec)
                        @php
                            $canEdit = $editService->canRequestEdit($spec);
                            $pending = $spec->pendingEditRequest;
                            $rejected = $spec->rejectedSpecEditRequest;
                            $editRequest = $pending ?? $rejected;
                            $stage = $spec->caseRecord?->stage_key;

                            if ($pending) {
                                $statusClass = 'spec-status-badge--pending';
                                $statusLabel = '⏳ طلب تعديل معلّق';
                            } elseif ($rejected) {
                                $statusClass = 'spec-status-badge--rejected';
                                $statusLabel = '❌ رُفض طلب التعديل';
                            } elseif ($canEdit) {
                                $statusClass = 'spec-status-badge--editable';
                                $statusLabel = '✏️ قابل للتعديل';
                            } else {
                                $statusClass = 'spec-status-badge--sent';
                                $statusLabel = 'مُرسَل';
                            }

                            $searchHay = strtolower(implode(' ', [
                                $spec->patient_name,
                                $spec->order_ref,
                                $spec->caseRecord?->case_no,
                                $statusLabel,
                                $spec->items->pluck('stock_item_code')->implode(' '),
                                $spec->items->pluck('name')->implode(' '),
                            ]));
                        @endphp
                        <tr class="spec-preview-row" data-spec-id="{{ $spec->id }}" data-search="{{ $searchHay }}">
                            <td><strong>{{ $spec->patient_name }}</strong></td>
                            <td>{{ $spec->caseRecord?->case_no ?? '—' }}</td>
                            <td>{{ $spec->order_ref }}</td>
                            <td>{{ $submittedAtLabel($spec) }}</td>
                            <td>{{ $spec->items->count() }}</td>
                            <td><span class="spec-status-badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                            <td>
                                <div class="spec-preview-actions">
                                    <button type="button" class="btn-action spec-preview-toggle-btn" data-spec-id="{{ $spec->id }}">📦 البنود</button>
                                    @if ($canEdit)
                                        <button type="button" class="btn-action primary spec-edit-open-btn" data-spec-id="{{ $spec->id }}">✏️ طلب تعديل</button>
                                    @endif
                                    <a href="{{ route('spec.spec.print', $spec) }}?embed=1"
                                       target="_blank" rel="noopener" class="btn-action">🖨️ طباعة</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-cell">لا توجد توصيفات مُرسَلة بعد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @foreach ($specs as $spec)
        @php
            $pending = $spec->pendingEditRequest;
            $rejected = $spec->rejectedSpecEditRequest;
            $editRequest = $pending ?? $rejected;
            $stage = $spec->caseRecord?->stage_key;
        @endphp
        <div class="spec-preview-items-source" id="spec-preview-items-source-{{ $spec->id }}" hidden>
            @include('spec.partials.preview-spec-detail', compact('spec', 'editRequest', 'pending', 'rejected', 'stage'))
        </div>
    @endforeach
</div>

<script>
    window.__SPEC_PREVIEW_EXPORT = @json($exportRows);
</script>

@include('spec.partials.preview-edit-modals')
@include('spec.partials.preview-items-modal')
