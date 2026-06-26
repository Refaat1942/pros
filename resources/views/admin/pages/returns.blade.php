@php
    use App\Models\ReturnNote;
    $notes = $return_notes ?? collect();
    $items = $return_items_summary ?? collect();
    $linesExport = $return_lines_export ?? [];
    $barcodes = $return_barcodes ?? collect();

    $statusLabel = fn (string $status) => match ($status) {
        ReturnNote::STATUS_COMPLETED => 'تم الاستلام',
        ReturnNote::STATUS_PARTIAL   => 'استلام جزئي',
        default                      => 'بانتظار استلام المخزن',
    };

    $statusStyle = fn (string $status) => match ($status) {
        ReturnNote::STATUS_COMPLETED => ['bg' => '#dcfce7', 'color' => '#059669', 'icon' => '✅'],
        ReturnNote::STATUS_PARTIAL   => ['bg' => '#e0f2fe', 'color' => '#0e7490', 'icon' => '🔄'],
        default                      => ['bg' => '#fef3c7', 'color' => '#d97706', 'icon' => '📤'],
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
        ↩️ <span><strong>طلبات الارتجاع </strong> مراقبة كاملة لمسار الورشة → المخزن — الإرسال من مكتب التشغيل والاستلام بتأكيد الباركود في المخزن.</span>
    </div>

    {{-- ─── Return requests log ──────────────────────────────────────────── --}}
    <div class="panel" style="margin-bottom:20px;">
        <div class="panel-header">
            <h3>📋 سجل طلبات الارتجاع</h3>
            <span class="badge" id="returnNotesCount">{{ $notes->count() }}</span>
        </div>
        <div class="data-toolbar">
            <input type="text" id="returnNoteSearch"
                   placeholder="🔍 بحث برقم الطلب أو المريض أو أمر التشغيل..."
                   autocomplete="off">
            <select id="returnNoteStatusFilter"
                    style="padding:8px 12px;border-radius:8px;border:1px solid #e2e8f0;font-family:inherit;font-size:13px;">
                <option value="">كل الحالات</option>
                <option value="authorized">📤 بانتظار المخزن</option>
                <option value="partial">🔄 استلام جزئي</option>
                <option value="completed">✅ تم الاستلام</option>
            </select>
            <div class="export-btns">
                <button type="button" class="btn-export excel" onclick="exportAdminReturnNotes('excel')">📊 Excel</button>
            </div>
            <span class="toolbar-count" id="returnNoteFilterCount">{{ $notes->count() }} طلب</span>
        </div>
        <div class="panel-body">
            <table data-paginate="15" id="returnNotesTable">
                <thead>
                    <tr>
                        <th>رقم الطلب</th>
                        <th>BOM</th>
                        <th>أمر التشغيل</th>
                        <th>المريض</th>
                        <th>سبب الارتجاع</th>
                        <th>البنود</th>
                        <th>الحالة</th>
                        <th>أرسله</th>
                        <th>تاريخ الإرسال</th>
                        <th>تاريخ الاستلام</th>
                        <th class="col-actions">إجراء</th>
                    </tr>
                </thead>
                <tbody id="returnNotesTableBody">
                    @forelse ($notes as $note)
                        @php
                            $style = $statusStyle($note->status);
                            $reason = $note->lines->first()?->reason ?? '—';
                            $linesSummary = $note->lines->map(fn ($l) => ($l->name ?: $l->stock_item_code) . ' ' . $l->qty_returned . '/' . $l->qty_requested)->join(' · ');
                            $linesJson = $note->lines->map(fn ($l) => [
                                'code' => $l->stock_item_code,
                                'name' => $l->name ?: $l->stock_item_code,
                                'barcode' => $barcodes[$l->stock_item_code] ?? ('BC-' . preg_replace('/\D/', '', $l->stock_item_code)),
                                'requested' => $l->qty_requested,
                                'returned' => $l->qty_returned,
                                'reason' => $l->reason,
                            ])->values()->toJson(JSON_UNESCAPED_UNICODE);
                        @endphp
                        <tr class="return-note-row"
                            data-id="{{ $note->id }}"
                            data-status="{{ $note->status }}"
                            data-search="{{ $note->return_no }} {{ $note->patient_name }} {{ $note->work_order_no }} {{ $note->bom?->bom_no }} {{ $reason }}"
                            data-return-no="{{ $note->return_no }}"
                            data-bom-no="{{ $note->bom?->bom_no ?? '—' }}"
                            data-work-order="{{ $note->work_order_no ?? '—' }}"
                            data-patient="{{ $note->patient_name }}"
                            data-order-ref="{{ $note->order_ref ?? '—' }}"
                            data-case-no="{{ $note->caseRecord?->case_no ?? '—' }}"
                            data-status-label="{{ $statusLabel($note->status) }}"
                            data-created-by="{{ $note->createdByUser?->name ?? $note->created_by ?? '—' }}"
                            data-reason="{{ $reason }}"
                            data-authorized-at="{{ $note->authorized_at?->format('d/m/Y H:i') ?? '—' }}"
                            data-completed-at="{{ $note->completed_at?->format('d/m/Y H:i') ?? '—' }}"
                            data-lines="{{ e($linesJson) }}">
                            <td><strong style="font-family:monospace;">{{ $note->return_no }}</strong></td>
                            <td>{{ $note->bom?->bom_no ?? '—' }}</td>
                            <td><span style="font-family:monospace;font-size:12px;color:#4f46e5;">{{ $note->work_order_no ?? '—' }}</span></td>
                            <td>{{ $note->patient_name }}</td>
                            <td class="return-reason-cell" title="{{ $reason }}">{{ \Illuminate\Support\Str::limit($reason, 40) }}</td>
                            <td class="return-lines-cell" title="{{ $linesSummary }}">{{ $linesSummary ?: '—' }}</td>
                            <td>
                                <span class="return-status-badge" style="background:{{ $style['bg'] }};color:{{ $style['color'] }};">
                                    {{ $style['icon'] }} {{ $statusLabel($note->status) }}
                                </span>
                            </td>
                            <td>{{ $note->createdByUser?->name ?? $note->created_by ?? '—' }}</td>
                            <td>{{ $note->authorized_at?->format('d/m/Y H:i') ?? $note->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $note->completed_at?->format('d/m/Y H:i') ?? '—' }}</td>
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
                            <td colspan="11" style="text-align:center;padding:40px;color:var(--text-muted);">
                                لا توجد طلبات ارتجاع مسجلة.<br>
                                <small>يُرسل مكتب التشغيل الطلبات من لوحة التشغيل → ارتجاع للمخزن.</small>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ─── Line-level detail ────────────────────────────────────────────── --}}
    <div class="panel" style="margin-bottom:20px;">
        <div class="panel-header">
            <h3>🔍 تفاصيل البنود — سجل كامل</h3>
            <span class="badge">{{ count($linesExport) }} بند</span>
        </div>
        <div class="data-toolbar">
            <input type="text" id="returnLineSearch"
                   placeholder="🔍 بحث بالطلب أو الصنف أو الباركود..."
                   autocomplete="off">
            <div class="export-btns">
                <button type="button" class="btn-export excel" onclick="exportAdminReturnLinesDetail('excel')">📊 Excel — تفاصيل كاملة</button>
            </div>
            <span class="toolbar-count" id="returnLineFilterCount">{{ count($linesExport) }} بند</span>
        </div>
        <div class="panel-body">
            <table data-paginate="20" id="returnLinesDetailTable">
                <thead>
                    <tr>
                        <th>رقم الطلب</th>
                        <th>الحالة</th>
                        <th>أمر التشغيل</th>
                        <th>المريض</th>
                        <th>كود الصنف</th>
                        <th>اسم الصنف</th>
                        <th>الباركود</th>
                        <th>مطلوب</th>
                        <th>مستلم</th>
                        <th>متبقي</th>
                        <th>سبب الارتجاع</th>
                        <th>أرسله</th>
                        <th>تاريخ الإرسال</th>
                        <th>تاريخ الاستلام</th>
                    </tr>
                </thead>
                <tbody id="returnLinesTableBody">
                    @forelse ($linesExport as $row)
                        <tr class="return-line-row"
                            data-search="{{ $row['return_no'] }} {{ $row['patient_name'] }} {{ $row['stock_item_code'] }} {{ $row['item_name'] }} {{ $row['barcode'] }} {{ $row['work_order_no'] }}">
                            <td><strong style="font-family:monospace;">{{ $row['return_no'] }}</strong></td>
                            <td>{{ $row['status'] }}</td>
                            <td style="font-family:monospace;font-size:12px;color:#4f46e5;">{{ $row['work_order_no'] }}</td>
                            <td>{{ $row['patient_name'] }}</td>
                            <td><code class="return-code-chip">{{ $row['stock_item_code'] }}</code></td>
                            <td><strong>{{ $row['item_name'] }}</strong></td>
                            <td><code>{{ $row['barcode'] }}</code></td>
                            <td>{{ $row['qty_requested'] }}</td>
                            <td><strong style="color:#059669;">{{ $row['qty_returned'] }}</strong></td>
                            <td>{{ $row['qty_pending'] }}</td>
                            <td>{{ $row['reason'] }}</td>
                            <td>{{ $row['sent_by'] }}</td>
                            <td>{{ $row['sent_at'] }}</td>
                            <td>{{ $row['received_at'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="14" style="text-align:center;padding:32px;color:var(--text-muted);">
                                لا توجد بنود ارتجاع مسجلة بعد.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ─── Returned items summary ─────────────────────────────────────── --}}
    <div class="panel">
        <div class="panel-header">
            <h3>📦 الأصناف المرتجعة — ملخص تراكمي</h3>
            <span class="badge">{{ $items->count() }} صنف</span>
        </div>
        <div class="data-toolbar">
            <input type="text" id="returnItemSearch"
                   placeholder="🔍 بحث بالصنف أو الكود..."
                   autocomplete="off">
            <div class="export-btns">
                <button type="button" class="btn-export excel" onclick="exportAdminReturnItems('excel')">📊 Excel</button>
            </div>
            <span class="toolbar-count" id="returnItemFilterCount">{{ $items->count() }} صنف</span>
        </div>
        <div class="panel-body">
            <table data-paginate="12" id="returnItemsTable">
                <thead>
                    <tr>
                        <th>كود الصنف</th>
                        <th>اسم الصنف</th>
                        <th>كمية مطلوبة (إجمالي)</th>
                        <th>كمية مرتجعة فعلياً</th>
                        <th>نسبة الاسترداد</th>
                    </tr>
                </thead>
                <tbody id="returnItemsTableBody">
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
                                <small>تظهر البيانات بعد تأكيد استلام الارتجاع في لوحة المخزن.</small>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    window.__ADMIN_RETURN_LINES_EXPORT = @json($linesExport, JSON_UNESCAPED_UNICODE);
</script>

{{-- Detail modal --}}
<div id="returnNoteDetailModal" class="admin-return-modal-overlay" style="display:none;">
    <div class="admin-return-modal" onclick="event.stopPropagation()">
        <div class="admin-return-modal-header">
            <div>
                <h3 id="returnNoteModalTitle">↩️ تفاصيل طلب الارتجاع</h3>
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
