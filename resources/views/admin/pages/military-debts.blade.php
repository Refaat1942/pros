@php
    use App\Models\MilitaryDebt;
    use App\Services\DebtCollectionEntryService;
    $debts = $military_debts ?? collect();
    $collectionEntryService = app(DebtCollectionEntryService::class);

    $statusClass = function (string $status): string {
        return match ($status) {
            MilitaryDebt::STATUS_COLLECTED => 'paid',
            MilitaryDebt::STATUS_PARTIAL => 'partial',
            default => 'pending',
        };
    };
@endphp

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
        <select id="milDebtStatusFilter" aria-label="فلتر الحالة">
            <option value="">كل الحالات</option>
            <option value="{{ MilitaryDebt::STATUS_PENDING }}">🔴 بانتظار التحصيل</option>
            <option value="{{ MilitaryDebt::STATUS_PARTIAL }}">🟡 مسدَّد جزئياً</option>
            <option value="{{ MilitaryDebt::STATUS_COLLECTED }}">🟢 تم التحصيل</option>
        </select>
        <select id="milDebtBalanceFilter" aria-label="فلتر الرصيد">
            <option value="">كل الأرصدة</option>
            <option value="outstanding">متبقٍ للتحصيل</option>
            <option value="settled">تم التحصيل بالكامل</option>
        </select>
        <span class="toolbar-count" id="milDebtFilterCount">{{ $debts->count() }} سجل</span>
    </div>
    <div class="panel-body">
        <table class="bulk-select-table" data-bulk-bar="militaryDebtsBulkBar" data-bulk-delete-base="/admin/military-debts" data-no-paginate>
            <thead>
                <tr>
                    @include('admin.partials.bulk-select-th')
                    <th>رقم أمر الشغل</th>
                    <th>اسم المريض</th>
                    <th>الرقم العسكري</th>
                    <th>الجهة العسكرية الضامنة</th>
                    <th class="num">المستحق (ج.م)</th>
                    <th class="num">المحصّل (ج.م)</th>
                    <th class="num">المتبقي (ج.م)</th>
                    <th>تفاصيل التحصيل</th>
                    <th>الحالة</th>
                    <th>إجراء</th>
                </tr>
            </thead>
            <tbody id="milDebtTable">
                @forelse ($debts as $debt)
                    @php
                        $due = (float) $debt->total_cost;
                        $collected = (float) $debt->collected;
                        $remaining = max(0, $due - $collected);
                        $isSettled = $debt->isCollected();
                        $label = $isSettled ? 'تم التحصيل' : ($debt->status === MilitaryDebt::STATUS_PARTIAL ? 'مسدَّد جزئياً' : 'بانتظار التحصيل');
                        $class = $statusClass((string) $debt->status);
                        $collectionPkg = $collectionEntryService->packageForPayable($debt, $due, $collected);
                        $collectionSummary = $collectionPkg['collection_summary'];
                        $collectionEntries = $collectionPkg['collection_entries'];
                    @endphp
                    <tr class="mil-debt-row"
                        data-id="{{ $debt->id }}"
                        data-status="{{ $debt->status }}"
                        data-balance="{{ $remaining > 0 ? 'outstanding' : 'settled' }}"
                        data-frozen="{{ $isSettled ? '1' : '0' }}"
                        data-due="{{ $due }}"
                        data-collected="{{ $collected }}"
                        data-remaining="{{ $remaining }}"
                        data-filter-hidden="0"
                        data-search="{{ $debt->work_order_no }} {{ $debt->patient_name }} {{ $debt->sovereign_entity }}"
                        data-collection-title="{{ $debt->work_order_no ?? '' }} — {{ $debt->patient_name }}"
                        data-collection-summary='@json($collectionSummary)'
                        data-collection-entries='@json($collectionEntries)'
                        data-work-order="{{ $debt->work_order_no ?? '—' }}"
                        data-patient-name="{{ $debt->patient_name }}"
                        data-national-id="{{ $debt->patient_national_id ?? '—' }}"
                        data-sovereign-entity="{{ $debt->sovereign_entity }}"
                        data-delivered-at="{{ $debt->delivered_at?->format('d/m/Y') ?? '—' }}"
                        data-status-label="{{ $label }}"
                        data-collected-at="{{ $debt->collected_at?->format('d/m/Y H:i') ?? '—' }}">
                        @include('admin.partials.bulk-select-td', [
                            'id' => $debt->id,
                            'disabled' => $isSettled,
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
                        <td class="num mil-debt-due">{{ number_format($due, 2) }}</td>
                        <td class="num mil-debt-collected" style="color:#059669;">{{ number_format($collected, 2) }}</td>
                        <td class="num mil-debt-remaining">
                            <strong style="color:{{ $remaining > 0 ? '#d97706' : '#059669' }};">
                                {{ number_format($remaining, 2) }}
                            </strong>
                        </td>
                        <td class="debt-collection-cell">
                            @if ($collected > 0)
                                <button type="button" class="debt-collection-summary-btn" onclick="openDebtCollectionModal(this)">
                                    <span class="debt-collection-badge civ-debt-status civ-debt-status--{{ match($collectionSummary['mode']) {
                                        'full_once', 'full_multi' => 'paid',
                                        'partial_once', 'partial_multi' => 'partial',
                                        default => 'pending',
                                    } }}">{{ $collectionSummary['mode_label'] }}</span>
                                    @if ($collectionSummary['payment_count'] > 1)
                                        <small class="debt-collection-count">{{ $collectionSummary['payment_count'] }} دفعات</small>
                                    @endif
                                </button>
                            @else
                                <span class="debt-collection-empty">—</span>
                            @endif
                        </td>
                        <td class="mil-debt-action-cell civ-debt-action-cell">
                            @if ($isSettled)
                                <span class="civ-debt-status civ-debt-status--paid">✅ تم التحصيل</span>
                                @if ($debt->collected_at)
                                    <div style="font-size:10px;color:#64748b;margin-top:4px;">{{ $debt->collected_at->format('d/m/Y H:i') }}</div>
                                @endif
                            @elseif ($due <= 0)
                                <span class="civ-debt-status civ-debt-status--pending">—</span>
                            @else
                                <div class="civ-debt-collect-wrap">
                                    @if ($collected > 0)
                                        <span class="civ-debt-status civ-debt-status--{{ $class }}" style="margin-bottom:6px;">
                                            {{ $label }}
                                        </span>
                                    @endif
                                    <div class="civ-debt-collect-row">
                                        <input type="number"
                                               class="civ-debt-amount-input debt-collect-amount-input form-control mil-debt-amount-input"
                                               min="0.01"
                                               max="{{ $remaining }}"
                                               step="0.01"
                                               placeholder="المبلغ المحوّل"
                                               aria-label="مبلغ التحصيل">
                                        <button type="button"
                                                class="btn-action success btn-mil-collect"
                                                data-debt-id="{{ $debt->id }}">
                                            تم التحصيل
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </td>
                        <td class="col-actions mil-debt-action-cell">
                            <div class="table-actions mil-debt-table-actions">
                                <button type="button"
                                        class="admin-table-btn admin-table-btn--view mil-debt-view-btn"
                                        onclick="openMilDebtDetail(this)"
                                        aria-label="عرض تفاصيل {{ $debt->work_order_no ?? 'المديونية' }}">
                                    <span aria-hidden="true">👁️</span><span>عرض</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" style="text-align:center;padding:40px;color:var(--text-muted);">
                            لا توجد مديونيات عسكرية مسجلة بعد.<br>
                            <small>تظهر البيانات تلقائياً بعد إغلاق أي حالة عسكرية بالتسليم.</small>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

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
    var statusEl  = document.getElementById('milDebtStatusFilter');
    var balanceEl = document.getElementById('milDebtBalanceFilter');
    var countEl   = document.getElementById('milDebtFilterCount');

    function getRows() {
        return Array.prototype.slice.call(document.querySelectorAll('.mil-debt-row'));
    }

    function fmtMoney(n) {
        return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function rowMatchesFilters(row) {
        var term = searchEl ? searchEl.value.trim().toUpperCase() : '';
        var status = statusEl ? statusEl.value : '';
        var balance = balanceEl ? balanceEl.value : '';
        var haystack = (row.dataset.search || '').toUpperCase();
        var matchTerm = !term || haystack.indexOf(term) !== -1;
        var matchStatus = !status || row.dataset.status === status;
        var matchBalance = !balance || row.dataset.balance === balance;
        return matchTerm && matchStatus && matchBalance;
    }

    function applyFilters() {
        var visible = 0;
        getRows().forEach(function (row) {
            var show = rowMatchesFilters(row);
            row.dataset.filterHidden = show ? '0' : '1';
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        if (countEl) countEl.textContent = visible + ' سجل';
    }

    if (searchEl) searchEl.addEventListener('input', applyFilters);
    if (statusEl) statusEl.addEventListener('change', applyFilters);
    if (balanceEl) balanceEl.addEventListener('change', applyFilters);

    function getCsrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function showToast(msg, isError) {
        if (isError) {
            if (window.DebtCollectValidation) {
                window.DebtCollectValidation.showError(msg);
            } else {
                window.alert(msg);
            }
            return;
        }
        var toastEl = document.getElementById('toast');
        if (window.DashboardToast && toastEl) {
            window.DashboardToast.show(msg, { isError: false });
            return;
        }
    }

    function validateCollectInput(input, row) {
        var remaining = parseFloat(row.dataset.remaining) || 0;
        if (window.DebtCollectValidation) {
            return window.DebtCollectValidation.validateAmount(input, remaining, { alert: true });
        }
        var amount = parseFloat(input.value);
        if (!amount || amount <= 0) {
            showToast('أدخل المبلغ الذي حوّلته لحساب الإدارة.', true);
            return false;
        }
        if (amount > remaining) {
            showToast('لا يمكن أن يكون المبلغ المحصّل أكبر من المتبقي (' + fmtMoney(remaining) + ' ج.م).', true);
            return false;
        }
        return true;
    }

    function statusClassFor(status) {
        if (status === 'collected') return 'paid';
        if (status === 'partial_collection') return 'partial';
        return 'pending';
    }

    function updateRowFromDebt(row, debt) {
        var due = parseFloat(debt.due ?? debt.total_cost) || 0;
        var collected = parseFloat(debt.collected) || 0;
        var remaining = parseFloat(debt.remaining) || 0;

        row.dataset.status = debt.status;
        row.dataset.balance = remaining > 0 ? 'outstanding' : 'settled';
        row.dataset.frozen = debt.is_frozen ? '1' : '0';
        row.dataset.due = String(due);
        row.dataset.collected = String(collected);
        row.dataset.remaining = String(remaining);
        row.dataset.statusLabel = debt.status_label || '';

        var dueEl = row.querySelector('.mil-debt-due');
        var colEl = row.querySelector('.mil-debt-collected');
        var remEl = row.querySelector('.mil-debt-remaining');
        if (dueEl) dueEl.textContent = fmtMoney(due);
        if (colEl) colEl.textContent = fmtMoney(collected);
        if (remEl) {
            remEl.innerHTML = '<strong style="color:' + (remaining > 0 ? '#d97706' : '#059669') + ';">' + fmtMoney(remaining) + '</strong>';
        }

        if (window.DebtCollectionHistory && debt.collection_summary) {
            window.DebtCollectionHistory.updateCollectionCell(row, debt.collection_summary, debt.collection_entries, collected);
        }

        var actionCell = row.querySelector('.mil-debt-action-cell.civ-debt-action-cell');
        if (!actionCell) return;

        if (remaining <= 0 && due > 0) {
            var at = debt.collected_at ? '<div style="font-size:10px;color:#64748b;margin-top:4px;">' + debt.collected_at + '</div>' : '';
            actionCell.innerHTML = '<span class="civ-debt-status civ-debt-status--paid">✅ تم التحصيل</span>' + at;
            return;
        }

        if (due <= 0) {
            actionCell.innerHTML = '<span class="civ-debt-status civ-debt-status--pending">—</span>';
            return;
        }

        var partialBadge = collected > 0
            ? '<span class="civ-debt-status civ-debt-status--' + statusClassFor(debt.status) + '" style="margin-bottom:6px;">' + (debt.status_label || '') + '</span>'
            : '';

        actionCell.innerHTML =
            '<div class="civ-debt-collect-wrap">' +
                partialBadge +
                '<div class="civ-debt-collect-row">' +
                    '<input type="number" class="civ-debt-amount-input debt-collect-amount-input form-control mil-debt-amount-input" min="0.01" max="' + remaining + '" step="0.01" placeholder="المبلغ المحوّل" aria-label="مبلغ التحصيل">' +
                    '<button type="button" class="btn-action success btn-mil-collect" data-debt-id="' + row.dataset.id + '">تم التحصيل</button>' +
                '</div>' +
            '</div>';
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-mil-collect');
        if (!btn) return;

        var row = btn.closest('.mil-debt-row');
        if (!row) return;

        var input = row.querySelector('.mil-debt-amount-input');
        if (!validateCollectInput(input, row)) return;

        var amount = parseFloat(input.value);

        if (!window.confirm('تأكيد تسجيل تحصيل ' + fmtMoney(amount) + ' ج.م؟')) return;

        btn.disabled = true;
        fetch('/admin/military-debts/' + btn.getAttribute('data-debt-id') + '/collect', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ amount: amount }),
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    if (!res.ok) throw new Error(data.message || 'تعذّر تسجيل التحصيل');
                    return data;
                });
            })
            .then(function (data) {
                updateRowFromDebt(row, data.debt);
                showToast(data.message || 'تم التحصيل');
                applyFilters();
            })
            .catch(function (err) {
                showToast(err.message || 'تعذّر تسجيل التحصيل', true);
            })
            .finally(function () {
                btn.disabled = false;
            });
    });

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
        var statusColor = isCollected ? '#059669' : (d.status === 'partial_collection' ? '#d97706' : '#dc2626');
        var statusBg = isCollected ? '#dcfce7' : (d.status === 'partial_collection' ? '#fef3c7' : '#fee2e2');
        var statusIcon = isCollected ? '🟢' : (d.status === 'partial_collection' ? '🟡' : '🔴');

        if (body) {
            body.innerHTML =
                '<div style="display:grid;gap:12px;">' +
                detailRow('رقم أمر الشغل', '<span style="font-family:monospace;font-weight:700;color:#4f46e5;">' + esc(d.workOrder) + '</span>') +
                detailRow('اسم المريض العسكري', '<strong>' + esc(d.patientName) + '</strong>') +
                detailRow('الرقم العسكري / القومي', '<span style="font-family:monospace;">' + esc(d.nationalId) + '</span>') +
                detailRow('الجهة العسكرية الضامنة', '<span style="background:#ede9fe;color:#4f46e5;padding:3px 10px;border-radius:6px;font-weight:600;">🪖 ' + esc(d.sovereignEntity) + '</span>') +
                detailRow('المستحق (ج.م)', '<strong>' + fmtMoney(d.due) + '</strong>') +
                detailRow('المحصّل (ج.م)', '<strong style="color:#059669;">' + fmtMoney(d.collected) + '</strong>') +
                detailRow('المتبقي (ج.م)', '<strong style="color:' + (parseFloat(d.remaining) > 0 ? '#d97706' : '#059669') + ';">' + fmtMoney(d.remaining) + '</strong>') +
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

    applyFilters();
})();
</script>
