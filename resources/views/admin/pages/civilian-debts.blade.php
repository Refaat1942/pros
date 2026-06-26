@php
    use App\Enums\DebtStatus;
    use App\Services\DebtCollectionEntryService;
    $debts = $civilian_debts ?? collect();
    $companies = $civilian_debt_companies ?? collect();
    $collectionEntryService = app(DebtCollectionEntryService::class);

    $statusLabel = function (string $status, float $due, float $collected): string {
        if ($due > 0 && $collected >= $due) {
            return 'تم التحصيل';
        }
        return DebtStatus::tryFrom($status)?->label() ?? $status;
    };
@endphp

<div class="section-view" id="section-civilian-debts">
    <div class="ck-analytics" data-static-ui="1" id="analytics-civilian-debts">
        <div class="ck-stats">
            @foreach ($civilian_debts_stats ?? [] as $stat)
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
            <h3>🌐 مديونيات مدنية — جهات التعاقد</h3>
            <span class="badge" id="civDebtBadge">{{ $debts->count() }}</span>
        </div>
        <div class="data-toolbar civilian-debts-toolbar">
            <input type="text" id="civDebtSearch"
                   placeholder="🔍 بحث باسم الجهة..."
                   autocomplete="off">
            <select id="civDebtCompanyFilter" aria-label="فلتر الجهة">
                <option value="">كل الجهات</option>
                @foreach ($companies as $co)
                    <option value="{{ $co->id }}">{{ $co->name }}</option>
                @endforeach
            </select>
            <select id="civDebtStatusFilter" aria-label="فلتر الحالة">
                <option value="">كل الحالات</option>
                <option value="{{ DebtStatus::Pending->value }}">🔴 لم يُسدَّد</option>
                <option value="{{ DebtStatus::Partial->value }}">🟡 مسدَّد جزئياً</option>
                <option value="{{ DebtStatus::Paid->value }}">🟢 تم التحصيل</option>
            </select>
            <select id="civDebtBalanceFilter" aria-label="فلتر الرصيد">
                <option value="">كل الأرصدة</option>
                <option value="outstanding">متبقٍ للتحصيل</option>
                <option value="settled">تم التحصيل بالكامل</option>
            </select>
            <button type="button" class="btn-export excel" id="btnExportCivDebts">📊 Excel</button>
            <span class="toolbar-count" id="civDebtFilterCount">{{ $debts->count() }} جهة</span>
        </div>
        <div class="panel-body">
            <table id="civDebtTable" data-no-paginate>
                <thead>
                    <tr>
                        <th>جهة التعاقد</th>
                        <th class="num">المستحق (ج.م)</th>
                        <th class="num">المحصّل (ج.م)</th>
                        <th class="num">المتبقي (ج.م)</th>
                        <th>تفاصيل التحصيل</th>
                        <th>تحصيل</th>
                        <th>الحالة</th>
                        <th>إجراء</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($debts as $debt)
                        @php
                            $company = $debt->contractCompany;
                            $due = (float) $debt->due;
                            $collected = (float) $debt->collected;
                            $remaining = max(0, $due - $collected);
                            $isSettled = $remaining <= 0 && $due > 0;
                            $label = $statusLabel((string) $debt->status, $due, $collected);
                            $statusClass = match ($debt->status) {
                                DebtStatus::Paid->value => 'paid',
                                DebtStatus::Partial->value => 'partial',
                                default => 'pending',
                            };
                            $collectionPkg = $collectionEntryService->packageForPayable($debt, $due, $collected);
                            $collectionSummary = $collectionPkg['collection_summary'];
                            $collectionEntries = $collectionPkg['collection_entries'];
                            $lastCollectedAt = $collectionSummary['last_collected_at'] ?? null;
                        @endphp
                        <tr class="civ-debt-row"
                            data-id="{{ $debt->id }}"
                            data-company-id="{{ $debt->contract_company_id }}"
                            data-status="{{ $debt->status }}"
                            data-balance="{{ $remaining > 0 ? 'outstanding' : 'settled' }}"
                            data-frozen="{{ $isSettled ? '1' : '0' }}"
                            data-due="{{ $due }}"
                            data-collected="{{ $collected }}"
                            data-remaining="{{ $remaining }}"
                            data-filter-hidden="0"
                            data-search="{{ $company->name ?? '' }} {{ $company->company_code ?? '' }}"
                            data-collection-title="{{ $company->name ?? '' }}"
                            data-collection-summary='@json($collectionSummary)'
                            data-collection-entries='@json($collectionEntries)'
                            data-company-name="{{ $company->name ?? '—' }}"
                            data-company-code="{{ $company->company_code ?? '—' }}"
                            data-status-label="{{ $label }}"
                            data-last-collected-at="{{ $lastCollectedAt ?? '—' }}">
                            <td><strong>{{ $company->name ?? '—' }}</strong></td>
                            <td class="num civ-debt-due">{{ number_format($due, 2) }}</td>
                            <td class="num civ-debt-collected" style="color:#059669;">{{ number_format($collected, 2) }}</td>
                            <td class="num civ-debt-remaining">
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
                            <td class="civ-debt-collect-cell">
                                @if ($isSettled || $due <= 0)
                                    <span class="debt-collection-empty">—</span>
                                @else
                                    <div class="civ-debt-collect-row">
                                        <input type="number"
                                               class="civ-debt-amount-input debt-collect-amount-input form-control"
                                               min="0.01"
                                               max="{{ $remaining }}"
                                               step="0.01"
                                               placeholder="المبلغ المحوّل"
                                               aria-label="مبلغ التحصيل">
                                        <button type="button"
                                                class="btn-action success btn-civ-collect"
                                                data-company-id="{{ $debt->contract_company_id }}">
                                            تم التحصيل
                                        </button>
                                    </div>
                                @endif
                            </td>
                            <td class="civ-debt-status-cell">
                                @if ($isSettled)
                                    <span class="civ-debt-status civ-debt-status--paid">✅ تم التحصيل</span>
                                    @if ($lastCollectedAt)
                                        <div style="font-size:10px;color:#64748b;margin-top:4px;">{{ $lastCollectedAt }}</div>
                                    @endif
                                @elseif ($due <= 0)
                                    <span class="civ-debt-status civ-debt-status--pending">—</span>
                                @elseif ($collected > 0)
                                    <span class="civ-debt-status civ-debt-status--{{ $statusClass }}">{{ $label }}</span>
                                @else
                                    <span class="civ-debt-status civ-debt-status--pending">🔴 لم يُسدَّد</span>
                                @endif
                            </td>
                            <td class="col-actions">
                                <div class="table-actions civ-debt-table-actions">
                                    <button type="button"
                                            class="admin-table-btn admin-table-btn--view civ-debt-view-btn"
                                            onclick="openCivDebtDetail(this)"
                                            aria-label="عرض تفاصيل {{ $company->name ?? 'الجهة' }}">
                                        <span aria-hidden="true">👁️</span><span>عرض</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align:center;color:var(--text-muted);padding:28px;">
                                لا توجد مديونيات لجهات التعاقد المدنية بعد.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="civDebtDetailModal"
     style="display:none;position:fixed;inset:0;z-index:600;background:rgba(15,23,42,.65);
            backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:16px;">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:520px;max-height:92vh;
                box-shadow:0 24px 80px rgba(0,0,0,.25);display:flex;flex-direction:column;overflow:hidden;"
         onclick="event.stopPropagation()">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;
                    border-bottom:1px solid #e2e8f0;background:#f8fafc;flex-shrink:0;">
            <div>
                <h3 id="civDebtModalTitle" style="font-size:16px;font-weight:700;margin:0;">🌐 تفاصيل المديونية</h3>
                <p id="civDebtModalSubtitle" style="font-size:12px;color:#64748b;margin:4px 0 0;"></p>
            </div>
            <button type="button" id="btnCloseCivDebtDetail"
                    style="background:none;border:none;font-size:22px;cursor:pointer;color:#64748b;line-height:1;">&times;</button>
        </div>
        <div id="civDebtModalBody" style="flex:1;overflow:auto;padding:20px;"></div>
        <div class="catalog-modal-footer">
            <button type="button" class="btn-action primary" id="btnCivDebtModalClose">إغلاق</button>
        </div>
    </div>
</div>

<script>
(function () {
    function initCivilianDebtsPage() {
        if (document.body.dataset.activePage !== 'civilian-debts') return;

    var searchEl = document.getElementById('civDebtSearch');
    var companyEl = document.getElementById('civDebtCompanyFilter');
    var statusEl = document.getElementById('civDebtStatusFilter');
    var balanceEl = document.getElementById('civDebtBalanceFilter');
    var countEl = document.getElementById('civDebtFilterCount');

    function getRows() {
        return Array.prototype.slice.call(document.querySelectorAll('.civ-debt-row'));
    }

    function fmtMoney(n) {
        return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function esc(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function detailRow(label, value) {
        return '<div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;">' +
            '<span style="font-size:13px;color:#64748b;font-weight:600;">' + label + '</span>' +
            '<span style="font-size:13px;text-align:left;">' + value + '</span></div>';
    }

    function resolveLastCollectedAt(debt) {
        if (debt.collection_summary && debt.collection_summary.last_collected_at) {
            return debt.collection_summary.last_collected_at;
        }
        if (debt.last_collected_at) return debt.last_collected_at;
        return '';
    }

    function rowMatchesFilters(row) {
        var term = searchEl ? searchEl.value.trim().toUpperCase() : '';
        var company = companyEl ? companyEl.value : '';
        var status = statusEl ? statusEl.value : '';
        var balance = balanceEl ? balanceEl.value : '';
        var haystack = (row.dataset.search || '').toUpperCase();
        var matchTerm = !term || haystack.indexOf(term) !== -1;
        var matchCompany = !company || String(row.dataset.companyId) === String(company);
        var matchStatus = !status || row.dataset.status === status;
        var matchBalance = !balance || row.dataset.balance === balance;
        return matchTerm && matchCompany && matchStatus && matchBalance;
    }

    function applyFilters() {
        var visible = 0;

        getRows().forEach(function (row) {
            var show = rowMatchesFilters(row);
            row.dataset.filterHidden = show ? '0' : '1';
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        if (countEl) countEl.textContent = visible + ' جهة';
    }

    if (searchEl) searchEl.addEventListener('input', applyFilters);
    if (companyEl) companyEl.addEventListener('change', applyFilters);
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
        if (status === 'paid') return 'paid';
        if (status === 'partial') return 'partial';
        return 'pending';
    }

    function renderStatusCellHtml(debt, remaining, due, lastAt) {
        if (remaining <= 0 && due > 0) {
            var atHtml = lastAt ? '<div style="font-size:10px;color:#64748b;margin-top:4px;">' + esc(lastAt) + '</div>' : '';
            return '<span class="civ-debt-status civ-debt-status--paid">✅ تم التحصيل</span>' + atHtml;
        }
        if (due <= 0) {
            return '<span class="civ-debt-status civ-debt-status--pending">—</span>';
        }
        if (parseFloat(debt.collected) > 0) {
            return '<span class="civ-debt-status civ-debt-status--' + statusClassFor(debt.status) + '">' + esc(debt.status_label || '') + '</span>';
        }
        return '<span class="civ-debt-status civ-debt-status--pending">🔴 لم يُسدَّد</span>';
    }

    function renderCollectCellHtml(row, remaining, due) {
        if (remaining <= 0 || due <= 0) {
            return '<span class="debt-collection-empty">—</span>';
        }
        return '<div class="civ-debt-collect-row">' +
            '<input type="number" class="civ-debt-amount-input debt-collect-amount-input form-control" min="0.01" max="' + remaining + '" step="0.01" placeholder="المبلغ المحوّل" aria-label="مبلغ التحصيل">' +
            '<button type="button" class="btn-action success btn-civ-collect" data-company-id="' + row.dataset.companyId + '">تم التحصيل</button>' +
            '</div>';
    }

    function updateRowFromDebt(row, debt) {
        var due = parseFloat(debt.due) || 0;
        var collected = parseFloat(debt.collected) || 0;
        var remaining = parseFloat(debt.remaining) || 0;
        var lastAt = resolveLastCollectedAt(debt);

        row.dataset.status = debt.status;
        row.dataset.balance = remaining > 0 ? 'outstanding' : 'settled';
        row.dataset.frozen = debt.is_frozen ? '1' : '0';
        row.dataset.due = String(due);
        row.dataset.collected = String(collected);
        row.dataset.remaining = String(remaining);
        row.dataset.statusLabel = debt.status_label || '';
        row.dataset.lastCollectedAt = lastAt || '—';

        var dueEl = row.querySelector('.civ-debt-due');
        var colEl = row.querySelector('.civ-debt-collected');
        var remEl = row.querySelector('.civ-debt-remaining');
        if (dueEl) dueEl.textContent = fmtMoney(due);
        if (colEl) colEl.textContent = fmtMoney(collected);
        if (remEl) {
            remEl.innerHTML = '<strong style="color:' + (remaining > 0 ? '#d97706' : '#059669') + ';">' + fmtMoney(remaining) + '</strong>';
        }

        if (window.DebtCollectionHistory && debt.collection_summary) {
            window.DebtCollectionHistory.updateCollectionCell(row, debt.collection_summary, debt.collection_entries, collected);
        }

        var statusCell = row.querySelector('.civ-debt-status-cell');
        var collectCell = row.querySelector('.civ-debt-collect-cell');
        if (statusCell) statusCell.innerHTML = renderStatusCellHtml(debt, remaining, due, lastAt);
        if (collectCell) collectCell.innerHTML = renderCollectCellHtml(row, remaining, due);
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-civ-collect');
        if (!btn) return;

        var row = btn.closest('.civ-debt-row');
        if (!row) return;

        var input = row.querySelector('.civ-debt-amount-input');
        if (!validateCollectInput(input, row)) return;

        var amount = parseFloat(input.value);
        var remaining = parseFloat(row.dataset.remaining) || 0;

        if (!window.confirm('تأكيد تسجيل تحصيل ' + fmtMoney(amount) + ' ج.م؟')) return;

        btn.disabled = true;
        fetch('/admin/civilian-debts/' + btn.getAttribute('data-company-id') + '/collect', {
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

    window.openCivDebtDetail = function (btn) {
        var row = btn.closest('.civ-debt-row');
        if (!row) return;

        var d = row.dataset;
        var modal = document.getElementById('civDebtDetailModal');
        var title = document.getElementById('civDebtModalTitle');
        var subtitle = document.getElementById('civDebtModalSubtitle');
        var body = document.getElementById('civDebtModalBody');

        if (title) title.textContent = '🌐 تفاصيل المديونية — ' + (d.companyName || '—');
        if (subtitle) subtitle.textContent = (d.companyCode || '') ? 'كود الجهة: ' + d.companyCode : '';

        var isSettled = parseFloat(d.remaining) <= 0 && parseFloat(d.due) > 0;
        var collectedAmt = parseFloat(d.collected) || 0;
        var lastPaymentAt = (d.lastCollectedAt && d.lastCollectedAt !== '—') ? d.lastCollectedAt : '';
        var statusColor = isSettled ? '#059669' : (d.status === 'partial' ? '#d97706' : '#dc2626');
        var statusBg = isSettled ? '#dcfce7' : (d.status === 'partial' ? '#fef3c7' : '#fee2e2');
        var statusIcon = isSettled ? '🟢' : (d.status === 'partial' ? '🟡' : '🔴');

        if (body) {
            body.innerHTML =
                '<div style="display:grid;gap:12px;">' +
                detailRow('جهة التعاقد', '<strong>' + esc(d.companyName) + '</strong>') +
                detailRow('كود الجهة', '<span style="font-family:monospace;">' + esc(d.companyCode) + '</span>') +
                detailRow('المستحق (ج.م)', '<strong>' + fmtMoney(d.due) + '</strong>') +
                detailRow('المحصّل (ج.م)', '<strong style="color:#059669;">' + fmtMoney(d.collected) + '</strong>') +
                detailRow('المتبقي (ج.م)', '<strong style="color:' + (parseFloat(d.remaining) > 0 ? '#d97706' : '#059669') + ';">' + fmtMoney(d.remaining) + '</strong>') +
                detailRow('حالة المديونية', '<span style="display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:8px;font-size:12px;font-weight:700;background:' + statusBg + ';color:' + statusColor + ';">' + statusIcon + ' ' + esc(d.statusLabel) + '</span>') +
                (collectedAmt > 0 && lastPaymentAt ? detailRow('تاريخ آخر تحصيل', esc(lastPaymentAt)) : '') +
                (d.frozen === '1' ? '<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 14px;font-size:12px;color:#059669;">🔒 تم التحصيل بالكامل — لا يمكن إضافة دفعات جديدة.</div>' : '') +
                '</div>';
        }

        if (modal) modal.style.display = 'flex';
    };

    window.closeCivDebtDetail = function () {
        var modal = document.getElementById('civDebtDetailModal');
        if (modal) modal.style.display = 'none';
    };

    var btnCloseCivDetail = document.getElementById('btnCloseCivDebtDetail');
    var btnCivModalClose = document.getElementById('btnCivDebtModalClose');
    var civDetailModal = document.getElementById('civDebtDetailModal');
    if (btnCloseCivDetail) btnCloseCivDetail.addEventListener('click', closeCivDebtDetail);
    if (btnCivModalClose) btnCivModalClose.addEventListener('click', closeCivDebtDetail);
    if (civDetailModal) {
        civDetailModal.addEventListener('click', function (e) {
            if (e.target === civDetailModal) closeCivDebtDetail();
        });
    }

    function rowStatusText(row) {
        var badge = row.querySelector('.civ-debt-status');
        if (badge) return badge.textContent.replace(/\s+/g, ' ').trim();
        if (row.querySelector('.civ-debt-amount-input')) return 'بانتظار التحصيل';
        return '—';
    }

    function exportCivDebts() {
        if (!window.ExportKit || !ExportKit.toExcel) {
            alert('أداة التصدير غير متاحة — حدّث الصفحة وحاول مرة أخرى.');
            return;
        }

        var headers = ['جهة التعاقد', 'المستحق (ج.م)', 'المحصّل (ج.م)', 'المتبقي (ج.م)', 'الحالة'];
        var dataRows = [];

        getRows().forEach(function (row) {
            if (row.dataset.filterHidden === '1') return;
            dataRows.push([
                (row.dataset.search || '').trim(),
                fmtMoney(row.dataset.due),
                fmtMoney(row.dataset.collected),
                fmtMoney(row.dataset.remaining),
                rowStatusText(row),
            ]);
        });

        ExportKit.toExcel(
            'مديونيات_مدنية_' + new Date().toISOString().slice(0, 10),
            headers,
            dataRows
        );
    }

    var exportBtn = document.getElementById('btnExportCivDebts');
    if (exportBtn) exportBtn.addEventListener('click', exportCivDebts);

    applyFilters();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCivilianDebtsPage);
    } else {
        initCivilianDebtsPage();
    }
})();
</script>
