@php
    use App\Enums\DebtStatus;
    $debts = $civilian_debts ?? collect();
    $companies = $civilian_debt_companies ?? collect();

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
                        <th>الحالة</th>
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
                        @endphp
                        <tr class="civ-debt-row"
                            data-id="{{ $debt->id }}"
                            data-company-id="{{ $debt->contract_company_id }}"
                            data-status="{{ $debt->status }}"
                            data-balance="{{ $remaining > 0 ? 'outstanding' : 'settled' }}"
                            data-due="{{ $due }}"
                            data-collected="{{ $collected }}"
                            data-remaining="{{ $remaining }}"
                            data-filter-hidden="0"
                            data-search="{{ $company->name ?? '' }} {{ $company->company_code ?? '' }}">
                            <td><strong>{{ $company->name ?? '—' }}</strong></td>
                            <td class="num civ-debt-due">{{ number_format($due, 2) }}</td>
                            <td class="num civ-debt-collected" style="color:#059669;">{{ number_format($collected, 2) }}</td>
                            <td class="num civ-debt-remaining">
                                <strong style="color:{{ $remaining > 0 ? '#d97706' : '#059669' }};">
                                    {{ number_format($remaining, 2) }}
                                </strong>
                            </td>
                            <td class="civ-debt-action-cell">
                                @if ($isSettled)
                                    <span class="civ-debt-status civ-debt-status--paid">✅ تم التحصيل</span>
                                @elseif ($due <= 0)
                                    <span class="civ-debt-status civ-debt-status--pending">—</span>
                                @else
                                    <div class="civ-debt-collect-wrap">
                                        @if ($collected > 0)
                                            <span class="civ-debt-status civ-debt-status--{{ $statusClass }}" style="margin-bottom:6px;">
                                                {{ $label }}
                                            </span>
                                        @endif
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
                                                    data-company-id="{{ $company->id }}">
                                                تم التحصيل
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align:center;color:var(--text-muted);padding:28px;">
                                لا توجد مديونيات لجهات التعاقد المدنية بعد.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
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

    function updateRowFromDebt(row, debt) {
        var due = parseFloat(debt.due) || 0;
        var collected = parseFloat(debt.collected) || 0;
        var remaining = parseFloat(debt.remaining) || 0;

        row.dataset.status = debt.status;
        row.dataset.balance = remaining > 0 ? 'outstanding' : 'settled';
        row.dataset.due = String(due);
        row.dataset.collected = String(collected);
        row.dataset.remaining = String(remaining);

        var dueEl = row.querySelector('.civ-debt-due');
        var colEl = row.querySelector('.civ-debt-collected');
        var remEl = row.querySelector('.civ-debt-remaining');
        if (dueEl) dueEl.textContent = fmtMoney(due);
        if (colEl) colEl.textContent = fmtMoney(collected);
        if (remEl) {
            remEl.innerHTML = '<strong style="color:' + (remaining > 0 ? '#d97706' : '#059669') + ';">' + fmtMoney(remaining) + '</strong>';
        }

        var actionCell = row.querySelector('.civ-debt-action-cell');
        if (!actionCell) return;

        if (remaining <= 0 && due > 0) {
            actionCell.innerHTML = '<span class="civ-debt-status civ-debt-status--paid">✅ تم التحصيل</span>';
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
                    '<input type="number" class="civ-debt-amount-input debt-collect-amount-input form-control" min="0.01" max="' + remaining + '" step="0.01" placeholder="المبلغ المحوّل" aria-label="مبلغ التحصيل">' +
                    '<button type="button" class="btn-action success btn-civ-collect" data-company-id="' + row.dataset.companyId + '">تم التحصيل</button>' +
                '</div>' +
            '</div>';
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
