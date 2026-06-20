@php
    use App\Models\MilitaryDebt;
    $debts = $military_debts ?? collect();
@endphp

{{-- ─── Statistics ─────────────────────────────────────────────────────── --}}
<div class="ck-analytics" data-static-ui="1" id="analytics-military-debts">
    <div class="ck-stats">
        @foreach ($military_debts_stats ?? [] as $stat)
            <div class="ck-stat">
                <div class="ck-stat-icon" style="background:{{ $stat['bg'] ?? 'rgba(100,116,139,0.1)' }}">{{ $stat['icon'] }}</div>
                <div>
                    <div class="ck-stat-label">{{ $stat['label'] }}</div>
                    <div class="ck-stat-value"
                         data-stat="{{ $stat['key'] ?? '' }}"
                         @if(!empty($stat['color'])) style="color:{{ $stat['color'] }}" @endif>{{ $stat['value'] }}</div>
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- ─── Table ───────────────────────────────────────────────────────────── --}}
<div class="panel">
    <div class="panel-header">
        <h3>🪖 مديونيات الجهات العسكرية</h3>
        <span class="badge" id="milDebtCount">{{ $debts->count() }}</span>
    </div>
    <div class="data-toolbar">
        @include('admin.partials.bulk-action-bar', ['bulkBarId' => 'militaryDebtsBulkBar'])
        <input type="text" id="milDebtSearch"
               placeholder="🔍 بحث بالمريض أو الجهة أو رقم الأمر..."
               autocomplete="off">
        <select id="milDebtStatusFilter"
                style="padding:8px 12px;border-radius:8px;border:1px solid #e2e8f0;font-family:inherit;font-size:13px;">
            <option value="">كل الحالات</option>
            <option value="pending_collection">🔴 بانتظار التحصيل</option>
            <option value="collected">🟢 تم التحصيل</option>
        </select>
        <span class="toolbar-count" id="milDebtFilterCount">{{ $debts->count() }} سجل</span>
    </div>
    <div class="panel-body">
        <table class="bulk-select-table" data-bulk-bar="militaryDebtsBulkBar" data-bulk-delete-base="/admin/military-debts" data-paginate="15">
            <thead>
                <tr>
                    @include('admin.partials.bulk-select-th')
                    <th>رقم أمر الشغل</th>
                    <th>اسم المريض</th>
                    <th>الرقم العسكري</th>
                    <th>الجهة العسكرية الضامنة</th>
                    <th>إجمالي تكلفة الخامات</th>
                    <th>تاريخ الإغلاق</th>
                    <th>حالة المديونية</th>
                    <th>إجراء</th>
                </tr>
            </thead>
            <tbody id="milDebtTable">
                @forelse ($debts as $debt)
                    <tr class="mil-debt-row"
                        data-id="{{ $debt->id }}"
                        data-status="{{ $debt->status }}"
                        data-frozen="{{ $debt->isCollected() ? '1' : '0' }}"
                        data-cost="{{ (float) $debt->total_cost }}"
                        data-search="{{ $debt->work_order_no }} {{ $debt->patient_name }} {{ $debt->sovereign_entity }}"
                        data-work-order="{{ $debt->work_order_no ?? '—' }}"
                        data-patient-name="{{ $debt->patient_name }}"
                        data-national-id="{{ $debt->patient_national_id ?? '—' }}"
                        data-sovereign-entity="{{ $debt->sovereign_entity }}"
                        data-total-cost="{{ number_format((float) $debt->total_cost, 0) }}"
                        data-delivered-at="{{ $debt->delivered_at?->format('d/m/Y') ?? '—' }}"
                        data-status-label="{{ $debt->isCollected() ? 'تم التحصيل وإيداع الحساب' : 'بانتظار التحصيل' }}"
                        data-collected-at="{{ $debt->collected_at?->format('d/m/Y H:i') ?? '—' }}">
                        @include('admin.partials.bulk-select-td', [
                            'id' => $debt->id,
                            'disabled' => $debt->isCollected(),
                            'disabledTitle' => 'لا يمكن حذف سجل محصّل ومجمّد',
                        ])
                        <td>
                            <span style="font-family:monospace;font-size:12px;font-weight:700;color:#4f46e5;">
                                {{ $debt->work_order_no ?? '—' }}
                            </span>
                        </td>
                        <td><strong>{{ $debt->patient_name }}</strong></td>
                        <td>
                            <span style="font-family:monospace;font-size:12px;color:#64748b;">
                                {{ $debt->patient_national_id ?? '—' }}
                            </span>
                        </td>
                        <td>
                            <span style="background:#ede9fe;color:#4f46e5;padding:3px 8px;border-radius:6px;font-size:12px;font-weight:600;">
                                🪖 {{ $debt->sovereign_entity }}
                            </span>
                        </td>
                        <td>
                            <strong style="color:{{ $debt->status === MilitaryDebt::STATUS_COLLECTED ? '#059669' : '#d97706' }};">
                                {{ number_format((float)$debt->total_cost, 0) }} ج.م
                            </strong>
                        </td>
                        <td>{{ $debt->delivered_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="mil-debt-status-cell">
                            @if ($debt->isCollected())
                                <div>
                                    <span style="display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:8px;font-size:12px;font-weight:700;background:#dcfce7;color:#059669;">
                                        🟢 تم التحصيل وإيداع الحساب
                                    </span>
                                    <div style="font-size:10px;color:#64748b;margin-top:3px;">
                                        {{ $debt->collected_at?->format('d/m/Y H:i') ?? '' }}
                                    </div>
                                </div>
                            @else
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <select
                                        class="mil-debt-status-select"
                                        data-debt-id="{{ $debt->id }}"
                                        style="padding:6px 10px;border-radius:8px;border:1px solid #e2e8f0;font-family:inherit;font-size:12px;font-weight:600;cursor:pointer;background:#fff;">
                                        <option value="pending_collection" {{ $debt->status === MilitaryDebt::STATUS_PENDING ? 'selected' : '' }}>
                                            🔴 بانتظار التحصيل
                                        </option>
                                        <option value="collected" {{ $debt->status === MilitaryDebt::STATUS_COLLECTED ? 'selected' : '' }}>
                                            🟢 تم التحصيل وإيداع الحساب
                                        </option>
                                    </select>
                                    <span class="mil-debt-saving" data-id="{{ $debt->id }}"
                                          style="display:none;font-size:11px;color:#64748b;">جاري...</span>
                                </div>
                            @endif
                        </td>
                        <td>
                            <button type="button"
                                    class="btn btn-secondary mil-debt-view-btn"
                                    style="padding:4px 12px;font-size:11px;"
                                    onclick="openMilDebtDetail(this)">
                                👁️ عرض
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted);">
                            لا توجد مديونيات عسكرية مسجلة بعد.<br>
                            <small>تظهر البيانات تلقائياً بعد إغلاق أي حالة عسكرية بالتسليم.</small>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ─── Detail Modal ────────────────────────────────────────────────────── --}}
<div id="milDebtDetailModal"
     style="display:none;position:fixed;inset:0;z-index:600;background:rgba(15,23,42,.65);
            backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:16px;">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:520px;max-height:92vh;
                box-shadow:0 24px 80px rgba(0,0,0,.25);display:flex;flex-direction:column;overflow:hidden;"
         onclick="event.stopPropagation()">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;
                    border-bottom:1px solid #e2e8f0;background:#f8fafc;flex-shrink:0;">
            <div>
                <h3 id="milDebtModalTitle" style="font-size:16px;font-weight:700;margin:0;">🪖 تفاصيل المديونية</h3>
                <p id="milDebtModalSubtitle" style="font-size:12px;color:#64748b;margin:4px 0 0;"></p>
            </div>
            <button type="button" id="btnCloseMilDebtDetail"
                    style="background:none;border:none;font-size:22px;cursor:pointer;color:#64748b;line-height:1;">&times;</button>
        </div>
        <div id="milDebtModalBody" style="flex:1;overflow:auto;padding:20px;"></div>
        <div style="padding:12px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;text-align:left;">
            <button type="button" class="btn-view" id="btnMilDebtModalClose">إغلاق</button>
        </div>
    </div>
</div>

<script>
(function () {
    var searchEl  = document.getElementById('milDebtSearch');
    var filterEl  = document.getElementById('milDebtStatusFilter');
    var rows      = Array.prototype.slice.call(document.querySelectorAll('.mil-debt-row'));
    var countEl   = document.getElementById('milDebtFilterCount');

    function applyFilters() {
        var term   = searchEl ? searchEl.value.trim().toUpperCase() : '';
        var status = filterEl ? filterEl.value : '';
        var visible = 0;
        rows.forEach(function (row) {
            var matchTerm   = !term   || (row.dataset.search || '').toUpperCase().indexOf(term) !== -1;
            var matchStatus = !status || row.dataset.status === status;
            row.style.display = (matchTerm && matchStatus) ? '' : 'none';
            if (matchTerm && matchStatus) visible++;
        });
        if (countEl) countEl.textContent = visible + ' سجل';
        if (window.TablePagination) TablePagination.refreshById('milDebtTable');
    }

    if (searchEl) searchEl.addEventListener('input', applyFilters);
    if (filterEl) filterEl.addEventListener('change', applyFilters);

    function getCsrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function showToast(msg, isError) {
        if (window.DashboardToast) {
            window.DashboardToast.show(msg, { isError: isError });
            return;
        }
        alert(msg);
    }

    function parseNum(val) {
        return parseInt(String(val || '0').replace(/,/g, ''), 10) || 0;
    }

    function fmtNum(n) {
        return n.toLocaleString('ar-EG');
    }

    function statEl(key) {
        return document.querySelector('#analytics-military-debts [data-stat="' + key + '"]');
    }

    function updateStatsOnCollect(cost) {
        var pendingCount = statEl('pending_count');
        var collectedCount = statEl('collected_count');
        var pendingAmount = statEl('pending_amount');
        var collectedAmount = statEl('collected_amount');

        if (pendingCount) pendingCount.textContent = String(Math.max(0, parseInt(pendingCount.textContent, 10) - 1));
        if (collectedCount) collectedCount.textContent = String(parseInt(collectedCount.textContent, 10) + 1);
        if (pendingAmount) pendingAmount.textContent = fmtNum(Math.max(0, parseNum(pendingAmount.textContent) - cost));
        if (collectedAmount) collectedAmount.textContent = fmtNum(parseNum(collectedAmount.textContent) + cost);
    }

    window.openMilDebtDetail = function (btn) {
        var row = btn.closest('.mil-debt-row');
        if (!row) return;

        var d = row.dataset;
        var modal = document.getElementById('milDebtDetailModal');
        var title = document.getElementById('milDebtModalTitle');
        var subtitle = document.getElementById('milDebtModalSubtitle');
        var body = document.getElementById('milDebtModalBody');

        if (title) title.textContent = '🪖 تفاصيل المديونية — ' + (d.workOrder || '—');
        if (subtitle) subtitle.textContent = (d.patientName || '') + ' · ' + (d.sovereignEntity || '');

        var isCollected = d.status === 'collected';
        var statusColor = isCollected ? '#059669' : '#dc2626';
        var statusBg = isCollected ? '#dcfce7' : '#fee2e2';
        var statusIcon = isCollected ? '🟢' : '🔴';

        if (body) {
            body.innerHTML =
                '<div style="display:grid;gap:12px;">' +
                detailRow('رقم أمر الشغل', '<span style="font-family:monospace;font-weight:700;color:#4f46e5;">' + esc(d.workOrder) + '</span>') +
                detailRow('اسم المريض العسكري', '<strong>' + esc(d.patientName) + '</strong>') +
                detailRow('الرقم العسكري / القومي', '<span style="font-family:monospace;">' + esc(d.nationalId) + '</span>') +
                detailRow('الجهة العسكرية الضامنة', '<span style="background:#ede9fe;color:#4f46e5;padding:3px 10px;border-radius:6px;font-weight:600;">🪖 ' + esc(d.sovereignEntity) + '</span>') +
                detailRow('إجمالي تكلفة الخامات', '<strong style="font-size:16px;color:' + (isCollected ? '#059669' : '#d97706') + ';">' + esc(d.totalCost) + ' ج.م</strong>') +
                detailRow('تاريخ إغلاق الحالة', esc(d.deliveredAt)) +
                detailRow('حالة المديونية', '<span style="display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:8px;font-size:12px;font-weight:700;background:' + statusBg + ';color:' + statusColor + ';">' + statusIcon + ' ' + esc(d.statusLabel) + '</span>') +
                (isCollected ? detailRow('تاريخ التحصيل والإيداع', esc(d.collectedAt)) : '') +
                (d.frozen === '1' ? '<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 14px;font-size:12px;color:#059669;">🔒 السجل مجمّد — تم اعتماد التحصيل ولا يمكن التعديل.</div>' : '') +
                '</div>';
        }

        if (modal) modal.style.display = 'flex';
    };

    window.closeMilDebtDetail = function () {
        var modal = document.getElementById('milDebtDetailModal');
        if (modal) modal.style.display = 'none';
    };

    function detailRow(label, value) {
        return '<div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;">' +
            '<span style="font-size:13px;color:#64748b;font-weight:600;">' + label + '</span>' +
            '<span style="font-size:13px;text-align:left;">' + value + '</span></div>';
    }

    function esc(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    var milDebtModal = document.getElementById('milDebtDetailModal');
    if (milDebtModal) {
        milDebtModal.addEventListener('click', function (e) {
            if (e.target === milDebtModal) closeMilDebtDetail();
        });
    }
    var btnCloseMilDebt = document.getElementById('btnCloseMilDebtDetail');
    var btnMilDebtClose = document.getElementById('btnMilDebtModalClose');
    if (btnCloseMilDebt) btnCloseMilDebt.addEventListener('click', closeMilDebtDetail);
    if (btnMilDebtClose) btnMilDebtClose.addEventListener('click', closeMilDebtDetail);

    document.querySelectorAll('.mil-debt-status-select').forEach(function (sel) {
        sel.addEventListener('change', function () {
            var id       = sel.dataset.debtId;
            var newVal   = sel.value;
            var savingEl = document.querySelector('.mil-debt-saving[data-id="' + id + '"]');
            var row      = document.querySelector('.mil-debt-row[data-id="' + id + '"]');
            var cost     = row ? parseFloat(row.dataset.cost || '0') : 0;

            sel.disabled = true;
            if (savingEl) savingEl.style.display = 'inline';

            fetch('/admin/military-debts/' + id + '/status', {
                method: 'PATCH',
                headers: {
                    'Accept':           'application/json',
                    'Content-Type':     'application/json',
                    'X-CSRF-TOKEN':     getCsrf(),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ status: newVal })
            })
            .then(function (r) {
                return r.ok ? r.json() : r.json().then(function (j) { throw j; });
            })
            .then(function (res) {
                showToast(res.message || 'تم التحديث.', false);

                if (newVal === 'collected') {
                    var cell = sel.closest('.mil-debt-status-cell');
                    if (cell) {
                        var collectedAt = (res.debt && res.debt.collected_at) ? res.debt.collected_at : '';
                        cell.innerHTML =
                            '<div>' +
                            '<span style="display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:8px;font-size:12px;font-weight:700;background:#dcfce7;color:#059669;">🟢 تم التحصيل وإيداع الحساب</span>' +
                            (collectedAt ? '<div style="font-size:10px;color:#64748b;margin-top:3px;">' + collectedAt + '</div>' : '') +
                            '</div>';
                    }
                    if (row) {
                        row.dataset.status = 'collected';
                        row.dataset.frozen = '1';
                        row.dataset.statusLabel = 'تم التحصيل وإيداع الحساب';
                        row.dataset.collectedAt = (res.debt && res.debt.collected_at) ? res.debt.collected_at : '—';
                    }

                    updateStatsOnCollect(cost);
                } else {
                    if (row) row.dataset.status = newVal;
                    sel.disabled = false;
                    if (savingEl) savingEl.style.display = 'none';
                }
            })
            .catch(function (err) {
                var msg = (err && err.message) ? err.message : 'تعذّر التحديث.';
                showToast(msg, true);
                sel.value    = sel.value === 'collected' ? 'pending_collection' : 'collected';
                sel.disabled = false;
                if (savingEl) savingEl.style.display = 'none';
            });
        });
    });
})();
</script>
