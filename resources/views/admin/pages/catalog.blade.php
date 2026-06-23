@php
    /** قائمة مُنسّقة من StockCatalogService::formatItem (مصفوفات). */
    $items = collect($stock_items ?? []);
@endphp
<div class="section-view" id="section-catalog">
    <div class="panel">
        <div class="panel-header">
            <h3>📦 الأصناف والأسعار</h3>
            <span class="badge">{{ $items->count() }} صنف</span>
        </div>

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
            <input type="text" id="catalogSlimSearch" placeholder="🔍 بحث بالصنف أو الكود..." onkeyup="filterSlimCatalog(this.value)">
            <button type="button" class="btn-action" style="background:var(--primary);color:#fff;border:none;" onclick="openSlimCatalogForm()">➕ إضافة صنف</button>

            @can('import-inventory')
                <a class="btn-action" href="{{ route('admin.catalog.template') }}">⬇️ تنزيل القالب</a>
                <form method="POST" action="{{ route('admin.catalog.import') }}" enctype="multipart/form-data"
                      style="display:inline-flex;gap:6px;align-items:center;">
                    @csrf
                    <input type="file" name="file" accept=".csv,text/csv" required
                           style="font-size:12px;max-width:200px;">
                    <button type="submit" class="btn-action success">📤 رفع جماعي</button>
                </form>
            @endcan

            <span class="toolbar-count">{{ $items->count() }} صنف</span>
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
                    <label style="font-size:12px;font-weight:700;">أسعار إضافية (لو ليه أكثر من سعر)</label>
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

        <div class="panel-body" style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="background:var(--surface-2,#f8fafc);">
                        <th style="padding:9px;text-align:right;">الكود</th>
                        <th style="padding:9px;text-align:right;">الصنف</th>
                        <th style="padding:9px;text-align:center;">الكمية</th>
                        <th style="padding:9px;text-align:center;">السعر</th>
                        <th style="padding:9px;text-align:center;">أسعار إضافية</th>
                        <th style="padding:9px;text-align:center;width:240px;">إجراء</th>
                    </tr>
                </thead>
                <tbody id="catalogSlimTable">
                    @forelse ($items as $item)
                        <tr class="catalog-slim-row" data-search="{{ strtolower(($item['code'] ?? '') . ' ' . ($item['name'] ?? '')) }}"
                            data-item="{{ json_encode($item, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) }}"
                            style="border-top:1px solid var(--border);">
                            <td style="padding:8px;direction:ltr;text-align:right;"><strong>{{ $item['code'] ?? '' }}</strong></td>
                            <td style="padding:8px;">{{ $item['name'] ?? '' }}</td>
                            <td style="padding:8px;text-align:center;">{{ (int) ($item['qty'] ?? 0) }}</td>
                            <td style="padding:8px;text-align:center;">{{ number_format((float) ($item['price'] ?? 0), 2) }}</td>
                            <td style="padding:8px;text-align:center;color:var(--text-muted);">
                                @if (!empty($item['prices']))
                                    {{ count($item['prices']) }} سعر
                                @else
                                    —
                                @endif
                            </td>
                            <td style="padding:8px;text-align:center;white-space:nowrap;">
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

<script>
(function () {
    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    window.filterSlimCatalog = function (term) {
        term = (term || '').toLowerCase().trim();
        document.querySelectorAll('#catalogSlimTable .catalog-slim-row').forEach(function (row) {
            var hay = row.getAttribute('data-search') || '';
            row.style.display = (!term || hay.indexOf(term) !== -1) ? '' : 'none';
        });
    };

    window.addSlimPriceRow = function (label, amount) {
        var box = document.getElementById('slimExtraPrices');
        var row = document.createElement('div');
        row.className = 'slim-price-row';
        row.style.cssText = 'display:flex;gap:8px;align-items:center;';
        row.innerHTML =
            '<input type="text" class="slim-price-label" placeholder="تسمية (اختياري)" style="flex:1;padding:8px;border:1px solid var(--border);border-radius:8px;">' +
            '<input type="number" min="0" step="0.01" class="slim-price-amount" placeholder="السعر" style="width:120px;padding:8px;border:1px solid var(--border);border-radius:8px;">' +
            '<button type="button" class="btn-action danger" onclick="this.closest(\'.slim-price-row\').remove()">×</button>';
        box.appendChild(row);
        row.querySelector('.slim-price-label').value = label || '';
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
        (v.prices || []).forEach(function (p) { window.addSlimPriceRow(p.label, p.amount); });
        document.getElementById('slimCatalogError').style.display = 'none';
    }

    function collectExtraPrices() {
        var rows = [];
        document.querySelectorAll('#slimExtraPrices .slim-price-row').forEach(function (r) {
            var amount = parseFloat(r.querySelector('.slim-price-amount').value || '0');
            if (amount > 0) {
                rows.push({ label: r.querySelector('.slim-price-label').value.trim(), amount: amount });
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
})();
</script>
