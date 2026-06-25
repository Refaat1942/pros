@php
    /** قائمة مُنسّقة من StockCatalogService::formatItem (مصفوفات). */
    $items = collect($stock_items ?? []);
    // $categories = collect($stock_categories ?? []);
@endphp
<div class="section-view" id="section-catalog">
    <div class="panel">
        <div class="panel-header">
            <h3>📦 الأصناف والأسعار</h3>
            <span class="badge">{{ $items->count() }} صنف</span>
        </div>

        <div id="catalogImportStatus" style="display:none;margin:12px 16px;padding:10px 14px;border-radius:8px;font-size:13px;"></div>
        @if (session('status'))
            <div style="margin:12px 16px;padding:10px 14px;background:#dcfce7;border:1px solid #86efac;border-radius:8px;color:#166534;font-size:13px;">
                ✅ {{ session('status') }}
            </div>
        @endif
        @if (!empty(session('import_errors')))
            <div style="margin:12px 16px;padding:10px 14px;background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;color:#92400e;font-size:12px;">
                @foreach (session('import_errors') as $err)
                    <div>⚠️ {{ $err }}</div>
                @endforeach
            </div>
        @endif

        <div class="data-toolbar" style="flex-wrap:wrap;gap:8px;">
            <input type="text" id="catalogSlimSearch" placeholder="🔍 بحث بالصنف أو الكود..." onkeyup="applySlimCatalogFilters()">
            {{-- فلتر الفئات — معطّل مع صفحة فئات الأصناف
            <select id="catalogCategoryFilter" onchange="applySlimCatalogFilters()" style="padding:8px 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;min-width:160px;">
                <option value="">🏷️ كل الفئات</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </select>
            --}}
            <button type="button" class="btn-action" style="background:var(--primary);color:#fff;border:none;" onclick="openSlimCatalogForm()">➕ إضافة صنف</button>

            @can('import-inventory')
                <a class="btn-action" href="{{ route('admin.catalog.template') }}">⬇️ تنزيل القالب</a>
                <form id="catalogImportForm" method="POST" action="{{ route('admin.catalog.import') }}" enctype="multipart/form-data" style="display:inline-flex;">
                    @csrf
                    <input type="file" id="catalogImportFile" name="file" accept=".csv,text/csv" class="catalog-file-input" required>
                    <label for="catalogImportFile" class="btn-action success catalog-file-label" id="catalogImportBtn">📤 ارفع ملف اكسيل</label>
                </form>
            @endcan

            <span class="toolbar-count" id="catalogSlimCount">{{ $items->count() }} صنف</span>
        </div>

        {{-- نموذج مبسّط: السمات الأساسية فقط --}}
        <div class="catalog-form" id="catalogSlimForm" style="display:none;padding:16px;border:1px solid var(--border);border-radius:10px;margin:0 16px 12px;">
            <input type="hidden" id="slimEditId" value="">
            <input type="hidden" id="slimEditCode" value="">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
                <div>
                    <label style="font-size:12px;font-weight:700;">كود الصنف / الباركود</label>
                    <input type="text" id="slimCode" placeholder="تلقائي إن تُرك فارغاً" style="width:100%;padding:9px;border:1px solid var(--border);border-radius:8px;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:700;">اسم الصنف *</label>
                    <input type="text" id="slimName" placeholder="مثال: ركبة هيدروليكية" style="width:100%;padding:9px;border:1px solid var(--border);border-radius:8px;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:700;">الكمية</label>
                    <input type="number" id="slimQty" min="0" value="0" style="width:100%;padding:9px;border:1px solid var(--border);border-radius:8px;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:700;">السعر</label>
                    <input type="number" id="slimPrice" min="0" step="0.01" value="0" style="width:100%;padding:9px;border:1px solid var(--border);border-radius:8px;">
                </div>
            </div>

            {{-- أسعار إضافية — صنف بأكثر من سعر (اختياري) --}}
            <div style="margin-top:14px;">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    {{-- <label style="font-size:12px;font-weight:700;">أسعار إضافية (لو ليه أكثر من سعر)</label> --}}
                    <button type="button" class="btn-action" onclick="addSlimPriceRow()">+ سعر إضافي</button>
                </div>
                <div id="slimExtraPrices" style="margin-top:8px;display:flex;flex-direction:column;gap:8px;"></div>
            </div>

            <div id="slimCatalogError" style="display:none;margin-top:10px;padding:8px;background:#fee2e2;border-radius:8px;color:#dc2626;font-size:12px;"></div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px;">
                <button type="button" class="btn-action" onclick="closeSlimCatalogForm()">إلغاء</button>
                <button type="button" class="btn-action success" onclick="saveSlimCatalog()">💾 حفظ الصنف</button>
            </div>
        </div>

        <p class="catalog-table-hint">
            💡 لإضافة أكثر من سعر لنفس الصنف في ملف Excel/CSV، اكتب الأسعار في عمود «السعر» مفصولة بعلامة
            <strong>;</strong>
            — مثال: <code dir="ltr">1000;2000;2200</code>
            (أول سعر أساسي والباقي أسعار إضافية). عمود «أعلى سعر» يعرض أعلى قيمة مسجّلة.
        </p>

        <div class="panel-body" style="overflow-x:auto;">
            <table class="catalog-slim-table" style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="background:var(--surface-2,#f8fafc);">
                        <th style="padding:10px;text-align:right;">الكود</th>
                        <th style="padding:10px;text-align:right;">الصنف</th>
                        <th style="padding:10px;text-align:center;">الكمية</th>
                        <th style="padding:10px;text-align:center;">أعلى سعر</th>
                        <th style="padding:10px;text-align:center;">أسعار إضافية</th>
                        <th style="padding:10px;text-align:center;min-width:280px;">إجراء</th>
                    </tr>
                </thead>
                <tbody id="catalogSlimTable">
                    @forelse ($items as $item)
                        <tr class="catalog-slim-row"
                            data-search="{{ strtolower(($item['code'] ?? '') . ' ' . ($item['name'] ?? '') . ' ' . ($item['category'] ?? '')) }}"
                            data-category-id="{{ $item['category_id'] ?? '' }}"
                            data-item="{{ json_encode($item, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) }}"
                            style="border-top:1px solid var(--border);">
                            <td style="padding:8px;direction:ltr;text-align:right;"><strong>{{ $item['code'] ?? '' }}</strong></td>
                            <td style="padding:8px;">{{ $item['name'] ?? '' }}</td>
                            <td style="padding:8px;text-align:center;">{{ (int) ($item['qty'] ?? 0) }}</td>
                            <td style="padding:10px;text-align:center;" class="catalog-price-cell">{{ number_format((float) ($item['highest_price'] ?? $item['price'] ?? 0), 2) }}</td>
                            <td style="padding:8px;text-align:center;color:var(--text-muted);">
                                @if (!empty($item['prices']))
                                    {{ count($item['prices']) }} سعر
                                @else
                                    —
                                @endif
                            </td>
                            <td style="padding:10px;text-align:center;white-space:nowrap;">
                                <button type="button" class="btn-action" onclick="viewSlimCatalog(this)">👁️ عرض</button>
                                <button type="button" class="btn-action" onclick="editSlimCatalog(this)">✏️ تعديل</button>
                                @can('print-barcode')
                                    <a class="btn-action" target="_blank" href="{{ route('admin.catalog.labels', $item['id']) }}">🏷️ باركود</a>
                                @endcan
                                <button type="button" class="btn-action danger" onclick="deleteSlimCatalog({{ $item['id'] }}, {{ json_encode($item['name'] ?? '') }})">🗑️</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:24px;">لا توجد أصناف — أضف صنفاً أو ارفع ملف CSV.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="catalog-modal-overlay" id="catalogViewModal" role="dialog" aria-modal="true" hidden>
    <div class="catalog-modal" onclick="event.stopPropagation()">
        <div class="catalog-modal-header">
            <div>
                <h3 id="catalogViewTitle">تفاصيل الصنف</h3>
                <div class="modal-code" id="catalogViewCode"></div>
            </div>
            <button type="button" class="catalog-modal-close" id="catalogViewClose" aria-label="إغلاق">&times;</button>
        </div>
        <div class="catalog-modal-body" id="catalogViewBody"></div>
        <div class="catalog-modal-footer">
            <button type="button" class="btn-action" id="catalogViewCloseBtn">إغلاق</button>
        </div>
    </div>
</div>

<style>
    #section-catalog .catalog-table-hint {
        margin: 0 16px 12px;
        padding: 10px 14px;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 8px;
        color: #1e40af;
        font-size: 13px;
        line-height: 1.7;
    }
    #section-catalog .catalog-table-hint code {
        background: #fff;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 12px;
    }
    #section-catalog .catalog-file-input {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        border: 0;
    }
    #section-catalog .catalog-file-label {
        cursor: pointer;
        margin: 0;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    #section-catalog .catalog-slim-table {
        font-size: 14px;
    }
    #section-catalog .catalog-price-cell {
        font-size: 15px;
        font-weight: 800;
        color: var(--primary-dark, #5b21b6);
        font-variant-numeric: tabular-nums;
    }
    #catalogViewModal.catalog-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.45);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1200;
        padding: 16px;
    }
    #catalogViewModal.catalog-modal-overlay.open {
        display: flex;
    }
    #catalogViewModal .catalog-modal {
        background: #fff;
        border-radius: 12px;
        width: min(520px, 96vw);
        max-height: 90vh;
        overflow: hidden;
        box-shadow: 0 24px 64px rgba(0,0,0,0.2);
    }
    #catalogViewModal .catalog-modal-header,
    #catalogViewModal .catalog-modal-footer {
        padding: 14px 18px;
        border-bottom: 1px solid var(--border, #e2e8f0);
    }
    #catalogViewModal .catalog-modal-footer {
        border-bottom: none;
        border-top: 1px solid var(--border, #e2e8f0);
        display: flex;
        justify-content: flex-end;
    }
    #catalogViewModal .catalog-modal-body {
        padding: 16px 18px;
        overflow-y: auto;
        max-height: calc(90vh - 130px);
    }
    #catalogViewModal .catalog-detail-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-bottom: 16px;
    }
    #catalogViewModal .catalog-detail-box {
        background: #f8fafc;
        border-radius: 8px;
        padding: 10px 12px;
    }
    #catalogViewModal .catalog-detail-box .dl {
        font-size: 11px;
        color: var(--text-muted, #64748b);
        margin-bottom: 4px;
    }
    #catalogViewModal .catalog-detail-box .dv {
        font-size: 14px;
        font-weight: 700;
    }
    #catalogViewModal .catalog-prices-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    #catalogViewModal .catalog-prices-table th,
    #catalogViewModal .catalog-prices-table td {
        border: 1px solid var(--border, #e2e8f0);
        padding: 8px 10px;
        text-align: center;
    }
    #catalogViewModal .catalog-prices-table th {
        background: #f1f5f9;
        font-weight: 800;
    }
    #catalogViewModal .catalog-prices-table .price-val {
        font-weight: 800;
        font-size: 15px;
        color: var(--primary-dark, #5b21b6);
    }
</style>

<script>
(function () {
    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    window.applySlimCatalogFilters = function () {
        var term = (document.getElementById('catalogSlimSearch')?.value || '').toLowerCase().trim();
        var catId = document.getElementById('catalogCategoryFilter')?.value || '';
        var visible = 0;
        var total = 0;

        document.querySelectorAll('#catalogSlimTable .catalog-slim-row').forEach(function (row) {
            total++;
            var hay = row.getAttribute('data-search') || '';
            var rowCat = row.getAttribute('data-category-id') || '';
            var matchSearch = !term || hay.indexOf(term) !== -1;
            var matchCat = !catId || rowCat === catId;
            var show = matchSearch && matchCat;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        var countEl = document.getElementById('catalogSlimCount');
        if (countEl) {
            countEl.textContent = (catId || term)
                ? (visible + ' من ' + total + ' صنف')
                : (total + ' صنف');
        }
    };

    window.filterSlimCatalog = function () {
        window.applySlimCatalogFilters();
    };

    window.addSlimPriceRow = function (amount) {
        var box = document.getElementById('slimExtraPrices');
        var row = document.createElement('div');
        row.className = 'slim-price-row';
        row.style.cssText = 'display:flex;gap:8px;align-items:center;';
        row.innerHTML =
            '<input type="number" min="0" step="0.01" class="slim-price-amount" placeholder="السعر" style="flex:1;padding:8px;border:1px solid var(--border);border-radius:8px;">' +
            '<button type="button" class="btn-action danger" onclick="this.closest(\'.slim-price-row\').remove()">×</button>';
        box.appendChild(row);
        if (amount != null) row.querySelector('.slim-price-amount').value = amount;
    };

    function setForm(v) {
        document.getElementById('slimCode').value = v.code || '';
        document.getElementById('slimName').value = v.name || '';
        document.getElementById('slimQty').value = v.qty != null ? v.qty : 0;
        document.getElementById('slimPrice').value = v.price != null ? v.price : 0;
        document.getElementById('slimEditId').value = v.id || '';
        document.getElementById('slimEditCode').value = v.code || '';
        document.getElementById('slimExtraPrices').innerHTML = '';
        (v.prices || []).forEach(function (p) { window.addSlimPriceRow(p.amount); });
        document.getElementById('slimCatalogError').style.display = 'none';
    }

    function collectExtraPrices() {
        var rows = [];
        document.querySelectorAll('#slimExtraPrices .slim-price-row').forEach(function (r) {
            var amount = parseFloat(r.querySelector('.slim-price-amount').value || '0');
            if (amount > 0) {
                rows.push({ amount: amount });
            }
        });
        return rows;
    }

    window.openSlimCatalogForm = function () {
        setForm({});
        document.getElementById('slimCode').disabled = false;
        document.getElementById('catalogSlimForm').style.display = 'block';
    };

    window.closeSlimCatalogForm = function () {
        document.getElementById('catalogSlimForm').style.display = 'none';
    };

    window.editSlimCatalog = function (btn) {
        var row = btn.closest('tr');
        var data = JSON.parse(row.getAttribute('data-item'));
        setForm(data);
        document.getElementById('slimCode').disabled = true; // الكود غير قابل للتعديل
        document.getElementById('catalogSlimForm').style.display = 'block';
    };

    function itemHighestPrice(item) {
        var amounts = [parseFloat(item.price) || 0];
        (item.prices || []).forEach(function (p) {
            amounts.push(parseFloat(p.amount) || 0);
        });
        return amounts.length ? Math.max.apply(null, amounts) : 0;
    }

    function itemAllPrices(item) {
        var rows = [{ label: 'السعر الأساسي', amount: parseFloat(item.price) || 0, primary: true }];
        (item.prices || []).forEach(function (p, idx) {
            rows.push({ label: 'سعر إضافي ' + (idx + 1), amount: parseFloat(p.amount) || 0, primary: false });
        });
        return rows.filter(function (r) { return r.amount > 0; });
    }

    function detailBox(label, value) {
        return '<div class="catalog-detail-box"><div class="dl">' + label + '</div><div class="dv">' + value + '</div></div>';
    }

    window.viewSlimCatalog = function (btn) {
        var row = btn.closest('tr');
        var item = JSON.parse(row.getAttribute('data-item'));
        var modal = document.getElementById('catalogViewModal');
        if (!modal) return;

        document.getElementById('catalogViewTitle').textContent = item.name || '—';
        document.getElementById('catalogViewCode').textContent = (item.code || '—') + (item.barcode ? ' · ' + item.barcode : '');

        var prices = itemAllPrices(item);
        var pricesHtml = prices.length
            ? '<table class="catalog-prices-table"><thead><tr><th>#</th><th>النوع</th><th>القيمة (ج.م)</th></tr></thead><tbody>'
                + prices.map(function (p, i) {
                    return '<tr><td>' + (i + 1) + '</td><td>' + p.label + '</td><td class="price-val">' + formatCatalogPrice(p.amount) + '</td></tr>';
                }).join('')
                + '</tbody></table>'
            : '<p style="color:var(--text-muted);text-align:center;">لا توجد أسعار مسجّلة</p>';

        document.getElementById('catalogViewBody').innerHTML =
            '<div class="catalog-detail-grid">'
            + detailBox('كود الصنف', item.code || '—')
            + detailBox('الباركود', item.barcode || '—')
            + detailBox('الكمية', String(parseInt(item.qty, 10) || 0))
            + detailBox('أعلى سعر', '<span class="catalog-price-cell">' + formatCatalogPrice(itemHighestPrice(item)) + '</span>')
            + '</div>'
            + '<h4 style="font-size:14px;font-weight:800;margin:0 0 10px;color:var(--secondary);">💰 جميع الأسعار</h4>'
            + pricesHtml;

        modal.classList.add('open');
        modal.removeAttribute('hidden');
    };

    function closeCatalogViewModal() {
        var modal = document.getElementById('catalogViewModal');
        if (!modal) return;
        modal.classList.remove('open');
        modal.setAttribute('hidden', '');
    }

    document.getElementById('catalogViewClose')?.addEventListener('click', closeCatalogViewModal);
    document.getElementById('catalogViewCloseBtn')?.addEventListener('click', closeCatalogViewModal);
    document.getElementById('catalogViewModal')?.addEventListener('click', function (e) {
        if (e.target === this) closeCatalogViewModal();
    });

    window.saveSlimCatalog = function () {
        var id = document.getElementById('slimEditId').value;
        var err = document.getElementById('slimCatalogError');
        var name = document.getElementById('slimName').value.trim();

        if (!name) {
            err.textContent = 'يرجى إدخال اسم الصنف.';
            err.style.display = 'block';
            return;
        }

        var payload = {
            name: name,
            qty: parseInt(document.getElementById('slimQty').value || '0', 10),
            price: parseFloat(document.getElementById('slimPrice').value || '0'),
            prices: collectExtraPrices(),
        };

        if (!id) {
            var code = document.getElementById('slimCode').value.trim();
            if (code) payload.code = code;
        }

        var url = id ? ('/admin/catalog/' + id) : '/admin/catalog';
        var method = id ? 'PUT' : 'POST';

        fetch(url, {
            method: method,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        })
        .then(function (r) { return r.ok ? r.json() : r.json().then(function (j) { throw j; }); })
        .then(function () { window.location.reload(); })
        .catch(function (e) {
            var msg = (e && e.message) ? e.message : 'تعذّر الحفظ.';
            if (e && e.errors) { msg = Object.values(e.errors)[0][0]; }
            err.textContent = msg;
            err.style.display = 'block';
        });
    };

    window.deleteSlimCatalog = function (id, name) {
        if (!confirm('حذف «' + name + '»؟')) return;
        fetch('/admin/catalog/' + id, {
            method: 'DELETE',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
        .then(function (r) { return r.ok ? r.json() : r.json().then(function (j) { throw j; }); })
        .then(function () { window.location.reload(); })
        .catch(function (e) { alert((e && e.message) ? e.message : 'تعذّر الحذف.'); });
    };

    function formatCatalogPrice(n) {
        return Number(n || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function escAttr(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
    }

    function catalogRowHtml(item) {
        var search = escAttr(((item.code || '') + ' ' + (item.name || '') + ' ' + (item.category || '')).toLowerCase());
        var dataAttr = escAttr(JSON.stringify(item));
        var highest = itemHighestPrice(item);
        var extra = (item.prices && item.prices.length)
            ? (item.prices.length + ' سعر')
            : '—';
        var labelsUrl = '/admin/catalog/' + item.id + '/labels';
        return '<tr class="catalog-slim-row" data-search="' + search + '" data-category-id="' + (item.category_id || '') + '" data-item="' + dataAttr + '" style="border-top:1px solid var(--border);">' +
            '<td style="padding:10px;direction:ltr;text-align:right;"><strong>' + (item.code || '') + '</strong></td>' +
            '<td style="padding:10px;">' + (item.name || '') + '</td>' +
            '<td style="padding:10px;text-align:center;">' + (parseInt(item.qty, 10) || 0) + '</td>' +
            '<td style="padding:10px;text-align:center;" class="catalog-price-cell">' + formatCatalogPrice(highest) + '</td>' +
            '<td style="padding:10px;text-align:center;color:var(--text-muted);">' + extra + '</td>' +
            '<td style="padding:10px;text-align:center;white-space:nowrap;">' +
            '<button type="button" class="btn-action" onclick="viewSlimCatalog(this)">👁️ عرض</button> ' +
            '<button type="button" class="btn-action" onclick="editSlimCatalog(this)">✏️ تعديل</button> ' +
            '<a class="btn-action" target="_blank" href="' + labelsUrl + '">🏷️ باركود</a> ' +
            '<button type="button" class="btn-action danger" onclick="deleteSlimCatalog(' + item.id + ', ' + JSON.stringify(item.name || '') + ')">🗑️</button>' +
            '</td></tr>';
    }

    window.renderSlimCatalogTable = function (items) {
        var tbody = document.getElementById('catalogSlimTable');
        var countEl = document.getElementById('catalogSlimCount');
        var badge = document.querySelector('#section-catalog .badge');
        var list = items || [];

        if (!tbody) return;

        if (!list.length) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:24px;">لا توجد أصناف — أضف صنفاً أو ارفع ملف CSV.</td></tr>';
        } else {
            tbody.innerHTML = list.map(catalogRowHtml).join('');
        }

        var label = list.length + ' صنف';
        if (countEl) countEl.textContent = label;
        if (badge) badge.textContent = label;
        window.applySlimCatalogFilters();
    };

    function showImportStatus(message, isError, errors) {
        var box = document.getElementById('catalogImportStatus');
        if (!box) return;
        box.style.display = 'block';
        box.style.background = isError ? '#fee2e2' : '#dcfce7';
        box.style.border = '1px solid ' + (isError ? '#fca5a5' : '#86efac');
        box.style.color = isError ? '#dc2626' : '#166534';
        var html = (isError ? '⚠️ ' : '✅ ') + message;
        if (errors && errors.length) {
            html += errors.map(function (e) { return '<div style="margin-top:6px;font-size:12px;">⚠️ ' + e + '</div>'; }).join('');
        }
        box.innerHTML = html;
    }

    var importForm = document.getElementById('catalogImportForm');
    var fileInput = document.getElementById('catalogImportFile');
    var importLabel = document.getElementById('catalogImportBtn');

    function uploadCatalogFile() {
        if (!importForm || !fileInput || !fileInput.files || !fileInput.files[0]) {
            showImportStatus('يرجى اختيار ملف CSV.', true);
            return;
        }

        var formData = new FormData(importForm);
        if (importLabel) {
            importLabel.textContent = 'جاري الرفع…';
            importLabel.style.pointerEvents = 'none';
            importLabel.style.opacity = '0.7';
        }

        fetch(importForm.action, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf(),
            },
            credentials: 'same-origin',
            body: formData,
        })
        .then(function (r) {
            return r.json().then(function (j) {
                if (!r.ok) throw j;
                return j;
            });
        })
        .then(function (data) {
            showImportStatus(data.message || 'تم الاستيراد بنجاح.', false, data.summary && data.summary.errors);
            window.renderSlimCatalogTable(data.items || []);
            fileInput.value = '';
        })
        .catch(function (err) {
            var msg = (err && err.message) ? err.message : 'تعذّر رفع الملف.';
            if (err && err.errors && err.errors.file) {
                msg = err.errors.file[0];
            }
            showImportStatus(msg, true, err && err.summary ? err.summary.errors : null);
        })
        .finally(function () {
            if (importLabel) {
                importLabel.textContent = '📤 ارفع ملف اكسيل';
                importLabel.style.pointerEvents = '';
                importLabel.style.opacity = '';
            }
        });
    }

    if (fileInput) {
        fileInput.addEventListener('change', function () {
            if (fileInput.files && fileInput.files[0]) {
                uploadCatalogFile();
            }
        });
    }

    if (importForm) {
        importForm.addEventListener('submit', function (e) {
            e.preventDefault();
            uploadCatalogFile();
        });
    }
})();
</script>
