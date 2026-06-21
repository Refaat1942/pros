@php
    use App\Models\ReturnNote;
    $notes = $return_notes ?? collect();
    $items = $return_items_summary ?? collect();

    $statusLabel = fn (string $status) => match ($status) {
        ReturnNote::STATUS_COMPLETED => 'مكتمل',
        ReturnNote::STATUS_PARTIAL   => 'جزئي',
        default                      => 'مصرّح',
    };

    $statusStyle = fn (string $status) => match ($status) {
        ReturnNote::STATUS_COMPLETED => ['bg' => '#dcfce7', 'color' => '#059669', 'icon' => '✅'],
        ReturnNote::STATUS_PARTIAL   => ['bg' => '#e0f2fe', 'color' => '#0e7490', 'icon' => '🔄'],
        default                      => ['bg' => '#fef3c7', 'color' => '#d97706', 'icon' => '⏳'],
    };
@endphp

<div class="section-view" id="section-returns">
    <div class="ck-analytics" data-static-ui="1" id="analytics-returns">
        <div class="ck-stats">
            @foreach ($return_notes_stats ?? [] as $stat)
                <div class="ck-stat">
                    <div class="ck-stat-icon" style="background:{{ $stat['bg'] ?? 'rgba(100,116,139,0.1)' }}">{{ $stat['icon'] }}</div>
                    <div>
                        <div class="ck-stat-label">{{ $stat['label'] }}</div>
                        <div class="ck-stat-value"
                             @if(!empty($stat['color'])) style="color:{{ $stat['color'] }}" @endif>{{ $stat['value'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="admin-returns-banner">
        ↩️ <span><strong>مراقبة ارتجاع المواد (قراءة فقط):</strong> تعرض هذه الصفحة كل إذونات الارتجاع من الورشة إلى المخزن والأصناف التي استُعيدت فعلياً بالباركود. لا تؤثر حركات الارتجاع على مديونية جهات التعاقد.</span>
    </div>

    {{-- ─── Returned items summary ─────────────────────────────────────── --}}
    <div class="panel" style="margin-bottom:20px;">
        <div class="panel-header">
            <h3>📦 الأصناف المرتجعة — ملخص تراكمي</h3>
            <span class="badge">{{ $items->count() }} صنف</span>
        </div>
        <div class="data-toolbar">
            <input type="text" id="returnItemSearch"
                   placeholder="🔍 بحث بالصنف أو الكود..."
                   autocomplete="off">
            <span class="toolbar-count" id="returnItemFilterCount">{{ $items->count() }} صنف</span>
        </div>
        <div class="panel-body">
            <table data-paginate="12">
                <thead>
                    <tr>
                        <th>كود الصنف</th>
                        <th>اسم الصنف</th>
                        <th>كمية مطلوبة (إجمالي)</th>
                        <th>كمية مرتجعة فعلياً</th>
                        <th>نسبة الاسترداد</th>
                    </tr>
                </thead>
                <tbody id="returnItemsTable">
                    @forelse ($items as $item)
                        @php
                            $requested = (int) $item->total_requested;
                            $returned  = (int) $item->total_returned;
                            $pct       = $requested > 0 ? min(100, round(($returned / $requested) * 100)) : 0;
                        @endphp
                        <tr class="return-item-row"
                            data-search="{{ $item->stock_item_code }} {{ $item->name }}">
                            <td><code class="return-code-chip">{{ $item->stock_item_code }}</code></td>
                            <td><strong>{{ $item->name }}</strong></td>
                            <td>{{ $requested }}</td>
                            <td><strong style="color:#059669;">{{ $returned }}</strong></td>
                            <td>
                                <div class="return-pct-bar">
                                    <div class="return-pct-fill" style="width:{{ $pct }}%;"></div>
                                    <span>{{ $pct }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align:center;padding:32px;color:var(--text-muted);">
                                لا توجد أصناف مرتجعة بعد.<br>
                                <small>تظهر البيانات بعد مسح باركود الارتجاع في لوحة المخزون.</small>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ─── Return notes log ─────────────────────────────────────────────── --}}
    <div class="panel">
        <div class="panel-header">
            <h3>↩️ سجل إذونات الارتجاع</h3>
            <span class="badge" id="returnNotesCount">{{ $notes->count() }}</span>
        </div>
        <div class="data-toolbar">
            <input type="text" id="returnNoteSearch"
                   placeholder="🔍 بحث برقم الإذن أو المريض أو أمر التشغيل..."
                   autocomplete="off">
            <select id="returnNoteStatusFilter"
                    style="padding:8px 12px;border-radius:8px;border:1px solid #e2e8f0;font-family:inherit;font-size:13px;">
                <option value="">كل الحالات</option>
                <option value="authorized">⏳ مصرّح</option>
                <option value="partial">🔄 جزئي</option>
                <option value="completed">✅ مكتمل</option>
            </select>
            <span class="toolbar-count" id="returnNoteFilterCount">{{ $notes->count() }} إذن</span>
        </div>
        <div class="panel-body">
            <table data-paginate="15">
                <thead>
                    <tr>
                        <th>رقم الإذن</th>
                        <th>BOM</th>
                        <th>أمر التشغيل</th>
                        <th>المريض</th>
                        <th>البنود</th>
                        <th>الحالة</th>
                        <th>أصدره</th>
                        <th>التاريخ</th>
                        <th class="col-actions">إجراء</th>
                    </tr>
                </thead>
                <tbody id="returnNotesTable">
                    @forelse ($notes as $note)
                        @php
                            $style = $statusStyle($note->status);
                            $linesSummary = $note->lines->map(fn ($l) => ($l->name ?: $l->stock_item_code) . ' ' . $l->qty_returned . '/' . $l->qty_requested)->join(' · ');
                            $linesJson = $note->lines->map(fn ($l) => [
                                'code' => $l->stock_item_code,
                                'name' => $l->name ?: $l->stock_item_code,
                                'requested' => $l->qty_requested,
                                'returned' => $l->qty_returned,
                                'reason' => $l->reason,
                            ])->values()->toJson(JSON_UNESCAPED_UNICODE);
                        @endphp
                        <tr class="return-note-row"
                            data-id="{{ $note->id }}"
                            data-status="{{ $note->status }}"
                            data-search="{{ $note->return_no }} {{ $note->patient_name }} {{ $note->work_order_no }} {{ $note->bom?->bom_no }}"
                            data-return-no="{{ $note->return_no }}"
                            data-bom-no="{{ $note->bom?->bom_no ?? '—' }}"
                            data-work-order="{{ $note->work_order_no ?? '—' }}"
                            data-patient="{{ $note->patient_name }}"
                            data-order-ref="{{ $note->order_ref }}"
                            data-status-label="{{ $statusLabel($note->status) }}"
                            data-created-by="{{ $note->createdByUser?->name ?? $note->created_by ?? '—' }}"
                            data-authorized-at="{{ $note->authorized_at?->format('d/m/Y H:i') ?? '—' }}"
                            data-completed-at="{{ $note->completed_at?->format('d/m/Y H:i') ?? '—' }}"
                            data-lines="{{ e($linesJson) }}">
                            <td><strong style="font-family:monospace;">{{ $note->return_no }}</strong></td>
                            <td>{{ $note->bom?->bom_no ?? '—' }}</td>
                            <td><span style="font-family:monospace;font-size:12px;color:#4f46e5;">{{ $note->work_order_no ?? '—' }}</span></td>
                            <td>{{ $note->patient_name }}</td>
                            <td class="return-lines-cell" title="{{ $linesSummary }}">{{ $linesSummary ?: '—' }}</td>
                            <td>
                                <span class="return-status-badge" style="background:{{ $style['bg'] }};color:{{ $style['color'] }};">
                                    {{ $style['icon'] }} {{ $statusLabel($note->status) }}
                                </span>
                            </td>
                            <td>{{ $note->createdByUser?->name ?? $note->created_by ?? '—' }}</td>
                            <td>{{ $note->authorized_at?->format('d/m/Y') ?? $note->created_at->format('d/m/Y') }}</td>
                            <td class="col-actions">
                                <div class="table-actions">
                                    <button type="button"
                                            class="admin-table-btn admin-table-btn--view return-note-view-btn"
                                            onclick="openReturnNoteDetail(this)"
                                            aria-label="عرض تفاصيل {{ $note->return_no }}">
                                        <span aria-hidden="true">👁️</span><span>عرض</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted);">
                                لا توجد إذونات ارتجاع مسجلة.<br>
                                <small>يُنشئ مسؤول المخزن الإذونات من لوحة المخزون → إذن ارتجاع.</small>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Detail modal --}}
<div id="returnNoteDetailModal" class="admin-return-modal-overlay" style="display:none;">
    <div class="admin-return-modal" onclick="event.stopPropagation()">
        <div class="admin-return-modal-header">
            <div>
                <h3 id="returnNoteModalTitle">↩️ تفاصيل إذن الارتجاع</h3>
                <p id="returnNoteModalSubtitle" class="modal-subtitle"></p>
            </div>
            <button type="button" class="modal-close" id="btnCloseReturnNoteDetail" aria-label="إغلاق">&times;</button>
        </div>
        <div id="returnNoteModalBody" class="admin-return-modal-body"></div>
        <div class="admin-return-modal-footer">
            <button type="button" class="btn-action primary" id="btnReturnNoteModalClose">إغلاق</button>
        </div>
    </div>
</div>

<script>
(function () {
    function esc(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function applyNoteFilters() {
        var term   = (document.getElementById('returnNoteSearch') || {}).value || '';
        term = term.trim().toUpperCase();
        var status = (document.getElementById('returnNoteStatusFilter') || {}).value || '';
        var rows = document.querySelectorAll('.return-note-row');
        var visible = 0;
        rows.forEach(function (row) {
            var matchTerm   = !term || (row.dataset.search || '').toUpperCase().indexOf(term) !== -1;
            var matchStatus = !status || row.dataset.status === status;
            row.style.display = (matchTerm && matchStatus) ? '' : 'none';
            if (matchTerm && matchStatus) visible++;
        });
        var countEl = document.getElementById('returnNoteFilterCount');
        if (countEl) countEl.textContent = visible + ' إذن';
        if (window.TablePagination) TablePagination.refreshById('returnNotesTable');
    }

    function applyItemFilters() {
        var term = (document.getElementById('returnItemSearch') || {}).value || '';
        term = term.trim().toUpperCase();
        var rows = document.querySelectorAll('.return-item-row');
        var visible = 0;
        rows.forEach(function (row) {
            var match = !term || (row.dataset.search || '').toUpperCase().indexOf(term) !== -1;
            row.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        var countEl = document.getElementById('returnItemFilterCount');
        if (countEl) countEl.textContent = visible + ' صنف';
        if (window.TablePagination) TablePagination.refreshById('returnItemsTable');
    }

    var noteSearch = document.getElementById('returnNoteSearch');
    var noteFilter = document.getElementById('returnNoteStatusFilter');
    var itemSearch = document.getElementById('returnItemSearch');
    if (noteSearch) noteSearch.addEventListener('input', applyNoteFilters);
    if (noteFilter) noteFilter.addEventListener('change', applyNoteFilters);
    if (itemSearch) itemSearch.addEventListener('input', applyItemFilters);

    function detailRow(label, value) {
        return '<div class="admin-return-detail-row">' +
            '<span class="admin-return-detail-label">' + label + '</span>' +
            '<span class="admin-return-detail-value">' + value + '</span></div>';
    }

    window.openReturnNoteDetail = function (btn) {
        var row = btn.closest('.return-note-row');
        if (!row) return;
        var d = row.dataset;
        var lines = [];
        try { lines = JSON.parse(d.lines || '[]'); } catch (e) { lines = []; }

        var title = document.getElementById('returnNoteModalTitle');
        var subtitle = document.getElementById('returnNoteModalSubtitle');
        var body = document.getElementById('returnNoteModalBody');
        var modal = document.getElementById('returnNoteDetailModal');

        if (title) title.textContent = '↩️ ' + esc(d.returnNo);
        if (subtitle) subtitle.textContent = esc(d.patient) + ' · ' + esc(d.bomNo) + ' · ' + esc(d.workOrder);

        var linesHtml = lines.length
            ? '<div class="admin-return-lines-table-wrap"><table class="admin-return-lines-table">' +
              '<thead><tr><th>الصنف</th><th>مطلوب</th><th>مرتجع</th><th>السبب</th></tr></thead><tbody>' +
              lines.map(function (ln) {
                  var done = ln.returned >= ln.requested;
                  return '<tr class="' + (done ? 'is-complete' : '') + '">' +
                      '<td><strong>' + esc(ln.name) + '</strong><br><code>' + esc(ln.code) + '</code></td>' +
                      '<td>' + ln.requested + '</td>' +
                      '<td><strong style="color:' + (done ? '#059669' : '#d97706') + ';">' + ln.returned + '</strong></td>' +
                      '<td>' + esc(ln.reason || '—') + '</td></tr>';
              }).join('') + '</tbody></table></div>'
            : '<p style="color:var(--text-muted);">لا بنود.</p>';

        if (body) {
            body.innerHTML =
                '<div class="admin-return-detail-grid">' +
                detailRow('رقم الإذن', '<strong style="font-family:monospace;">' + esc(d.returnNo) + '</strong>') +
                detailRow('BOM', esc(d.bomNo)) +
                detailRow('أمر التشغيل', '<span style="font-family:monospace;color:#4f46e5;">' + esc(d.workOrder) + '</span>') +
                detailRow('المريض', '<strong>' + esc(d.patient) + '</strong>') +
                detailRow('مرجع الطلب', esc(d.orderRef)) +
                detailRow('الحالة', esc(d.statusLabel)) +
                detailRow('أصدره', esc(d.createdBy)) +
                detailRow('تاريخ الإصدار', esc(d.authorizedAt)) +
                (d.completedAt !== '—' ? detailRow('تاريخ الاكتمال', esc(d.completedAt)) : '') +
                '</div>' +
                '<h4 class="admin-return-lines-title">البنود</h4>' + linesHtml;
        }

        if (modal) modal.style.display = 'flex';
    };

    window.closeReturnNoteDetail = function () {
        var modal = document.getElementById('returnNoteDetailModal');
        if (modal) modal.style.display = 'none';
    };

    var modal = document.getElementById('returnNoteDetailModal');
    if (modal) modal.addEventListener('click', function (e) { if (e.target === modal) closeReturnNoteDetail(); });
    var btnX = document.getElementById('btnCloseReturnNoteDetail');
    var btnClose = document.getElementById('btnReturnNoteModalClose');
    if (btnX) btnX.addEventListener('click', closeReturnNoteDetail);
    if (btnClose) btnClose.addEventListener('click', closeReturnNoteDetail);
})();
</script>
