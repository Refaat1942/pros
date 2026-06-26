@php
    use App\Enums\DebtStatus;
    $debts = $civilian_debts ?? collect();
    $companies = $civilian_debt_companies ?? collect();
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
                   placeholder="🔍 بحث باسم الجهة أو الكود..."
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
                <option value="{{ DebtStatus::Paid->value }}">🟢 مسدَّد</option>
            </select>
            <select id="civDebtBalanceFilter" aria-label="فلتر الرصيد">
                <option value="">كل الأرصدة</option>
                <option value="outstanding">متبقٍ للتحصيل</option>
                <option value="settled">مسدَّد بالكامل</option>
            </select>
            <button type="button" class="btn-export excel" id="btnExportCivDebts">📊 Excel</button>
            <span class="toolbar-count" id="civDebtFilterCount">{{ $debts->count() }} جهة</span>
        </div>
        <div class="panel-body">
            <table id="civDebtTable" data-paginate="15">
                <thead>
                    <tr>
                        <th>كود الجهة</th>
                        <th>جهة التعاقد</th>
                        <th class="num">المستحق (ج.م)</th>
                        <th class="num">المحصّل (ج.م)</th>
                        <th class="num">المتبقي (ج.م)</th>
                        <th>الحالة</th>
                        <th>نسبة التحصيل</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($debts as $debt)
                        @php
                            $company = $debt->contractCompany;
                            $due = (float) $debt->due;
                            $collected = (float) $debt->collected;
                            $remaining = max(0, $due - $collected);
                            $pct = $due > 0 ? min(100, round(($collected / $due) * 100)) : ($collected > 0 ? 100 : 0);
                            $status = DebtStatus::tryFrom((string) $debt->status);
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
                            data-search="{{ ($company->company_code ?? '') }} {{ $company->name ?? '' }}">
                            <td>
                                <span class="bi-code">{{ $company->company_code ?? '—' }}</span>
                            </td>
                            <td><strong>{{ $company->name ?? '—' }}</strong></td>
                            <td class="num">{{ number_format($due, 2) }}</td>
                            <td class="num" style="color:#059669;">{{ number_format($collected, 2) }}</td>
                            <td class="num">
                                <strong style="color:{{ $remaining > 0 ? '#d97706' : '#059669' }};">
                                    {{ number_format($remaining, 2) }}
                                </strong>
                            </td>
                            <td>
                                <span class="civ-debt-status civ-debt-status--{{ $statusClass }}">
                                    {{ $status?->label() ?? $debt->status }}
                                </span>
                            </td>
                            <td>
                                <div class="civ-debt-progress" title="{{ $pct }}%">
                                    <div class="civ-debt-progress__bar" style="width:{{ $pct }}%;"></div>
                                    <span class="civ-debt-progress__label">{{ $pct }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align:center;color:var(--text-muted);padding:28px;">
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
    if (document.body.dataset.activePage !== 'civilian-debts') return;

    var searchEl = document.getElementById('civDebtSearch');
    var companyEl = document.getElementById('civDebtCompanyFilter');
    var statusEl = document.getElementById('civDebtStatusFilter');
    var balanceEl = document.getElementById('civDebtBalanceFilter');
    var countEl = document.getElementById('civDebtFilterCount');
    var rows = Array.prototype.slice.call(document.querySelectorAll('.civ-debt-row'));

    function applyFilters() {
        var term = searchEl ? searchEl.value.trim().toUpperCase() : '';
        var company = companyEl ? companyEl.value : '';
        var status = statusEl ? statusEl.value : '';
        var balance = balanceEl ? balanceEl.value : '';
        var visible = 0;

        rows.forEach(function (row) {
            var matchTerm = !term || (row.dataset.search || '').toUpperCase().indexOf(term) !== -1;
            var matchCompany = !company || row.dataset.companyId === company;
            var matchStatus = !status || row.dataset.status === status;
            var matchBalance = !balance || row.dataset.balance === balance;
            var show = matchTerm && matchCompany && matchStatus && matchBalance;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        if (countEl) countEl.textContent = visible + ' جهة';
        if (window.TablePagination) TablePagination.refreshById('civDebtTable');
    }

    if (searchEl) searchEl.addEventListener('input', applyFilters);
    if (companyEl) companyEl.addEventListener('change', applyFilters);
    if (statusEl) statusEl.addEventListener('change', applyFilters);
    if (balanceEl) balanceEl.addEventListener('change', applyFilters);

    var exportBtn = document.getElementById('btnExportCivDebts');
    if (exportBtn && window.ExportKit) {
        exportBtn.addEventListener('click', function () {
            var headers = ['كود الجهة', 'جهة التعاقد', 'المستحق (ج.م)', 'المحصّل (ج.م)', 'المتبقي (ج.م)', 'الحالة', 'نسبة التحصيل'];
            var dataRows = [];
            rows.forEach(function (row) {
                if (row.style.display === 'none') return;
                var cells = row.querySelectorAll('td');
                if (cells.length < 7) return;
                dataRows.push([
                    cells[0].textContent.trim(),
                    cells[1].textContent.trim(),
                    cells[2].textContent.trim(),
                    cells[3].textContent.trim(),
                    cells[4].textContent.trim(),
                    cells[5].textContent.trim(),
                    cells[6].querySelector('.civ-debt-progress__label')
                        ? cells[6].querySelector('.civ-debt-progress__label').textContent.trim()
                        : cells[6].textContent.trim(),
                ]);
            });
            ExportKit.toExcel('مديونيات_مدنية_' + new Date().toISOString().slice(0, 10), headers, dataRows);
        });
    }
})();
</script>
