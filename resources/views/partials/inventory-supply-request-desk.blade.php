@php
    $deskSectionId = $desk_section_id ?? 'section-supply-request';
    $deskTitle = $desk_title ?? '🛒 طلب التوريد — أصناف تحتاج توريد';
    $defaultFilter = $default_filter ?? 'backorder';
    $invStats = $inventory_stats ?? [
        ['icon' => '📦', 'label' => 'إجمالي الأصناف', 'value' => '0', 'color' => '#4338ca', 'bg' => 'rgba(67,56,202,0.1)'],
        ['icon' => '✅', 'label' => 'متوفر', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '🛒', 'label' => 'طلبات توريد', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.12)'],
        ['icon' => '⚠️', 'label' => 'كمية منخفضة', 'value' => '0', 'color' => '#dc2626', 'bg' => 'rgba(220,38,38,0.1)'],
    ];
    $inventoryListColumns = $inventory_list_columns ?? ['code', 'name', 'available', 'status'];
    $inventoryListColumnLabels = $inventory_list_column_labels ?? [];
    $listUrl = $inventory_list_url ?? '/technical/inventory/list';
    $supplyRequestsUrl = $supply_requests_url ?? '/technical/supply/requests';
    $supplyRequestsStoreUrl = $supply_requests_store_url ?? '/technical/supply/requests';
    $supplySearchItemsUrl = $supply_search_items_url ?? '/technical/supply/search-items';
    $supplyResolveUrlBase = $supply_resolve_url_base ?? '/technical/supply/requests';
    $supplyPrintUrl = $supply_print_url ?? null;
    $addCatalogItemUrl = $add_catalog_item_url ?? null;
    $receiveInboundUrl = $receive_inbound_url ?? null;
    $supplySwapsUrl = $supply_swaps_url ?? str_replace('/supply/requests', '/supply/swaps', $supplyRequestsStoreUrl);
    $canManageSwaps = auth()->user()?->can('manage-item-swaps') ?? false;
@endphp
<div class="section-view" id="{{ $deskSectionId }}">
    <div id="analytics-inventory-charts">@include('partials.dashboard-analytics-empty', ['stats' => $invStats, 'hide_charts' => true])</div>

    <div class="panel inventory-wrap" style="margin-bottom:16px;">
        <div class="panel-header">
            <h3>➕ إنشاء طلب توريد</h3>
        </div>
        <form id="supplyRequestCreateForm" class="panel-body" style="padding:16px;">
            <div style="margin-bottom:12px;display:flex;flex-wrap:wrap;gap:16px;">
                <label style="display:flex;align-items:center;gap:6px;font-size:14px;">
                    <input type="radio" name="supplyLineType" value="catalog" checked> صنف موجود بالكتالوج
                </label>
                <label style="display:flex;align-items:center;gap:6px;font-size:14px;">
                    <input type="radio" name="supplyLineType" value="non_catalog"> صنف غير مكود
                </label>
            </div>

            <div id="supplyCatalogFields" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
                <div class="form-group">
                    <label for="supplyCatalogSearch">بحث صنف كتالوج</label>
                    <input type="text" id="supplyCatalogSearch" class="form-control" placeholder="اكتب الكود أو الاسم (بدون تحميل الكتالوج الكامل)..." autocomplete="off">
                    <input type="hidden" id="supplyCatalogItemId" name="stock_item_id">
                    <div id="supplyCatalogSearchResults" class="supply-search-results" style="display:none;"></div>
                    <p id="supplyCatalogPickLabel" style="margin:6px 0 0;font-size:12px;color:var(--text-muted);">لم يُختَر صنف بعد.</p>
                </div>
            </div>

            <div id="supplyNonCatalogFields" style="display:none;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
                <div class="form-group">
                    <label for="supplyDescription">اسم / وصف الصنف</label>
                    <input type="text" id="supplyDescription" class="form-control" maxlength="500" placeholder="مثال: كرسي متحرك موديل X">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-top:12px;">
                <div class="form-group">
                    <label for="supplyQty">الكمية</label>
                    <input type="number" min="1" id="supplyQty" class="form-control" required value="1">
                </div>
                <div class="form-group">
                    <label for="supplyUom">الوحدة</label>
                    <input type="text" id="supplyUom" class="form-control" maxlength="50" placeholder="قطعة / زوج / …">
                </div>
                <div class="form-group">
                    <label for="supplySpec">المواصفات / الملاحظات</label>
                    <input type="text" id="supplySpec" class="form-control" maxlength="2000" placeholder="تفاصيل إضافية للمورد">
                </div>
            </div>

            {{-- طلب واحد بأكثر من صنف: «أضف للطلب» لكل صنف ثم «تسجيل طلب التوريد» مرة واحدة. --}}
            <div style="margin-top:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <button type="button" class="btn-action" id="btnAddSupplyCartLine">➕ أضف للطلب</button>
                <span style="font-size:12px;color:var(--text-muted);">أضف كل الأصناف المطلوبة ثم سجّل الطلب مرة واحدة برقم واحد.</span>
            </div>

            <div id="supplyCartWrap" style="display:none;margin-top:12px;">
                <table data-no-paginate class="stock-table" style="width:100%;">
                    <thead>
                        <tr><th>#</th><th>الصنف</th><th>الكمية</th><th>الوحدة</th><th>المواصفات</th><th></th></tr>
                    </thead>
                    <tbody id="supplyCartBody"></tbody>
                </table>
                <div class="form-group" style="margin-top:10px;">
                    <label for="supplyRequestNotes">ملاحظات الطلب (اختياري)</label>
                    <input type="text" id="supplyRequestNotes" class="form-control" maxlength="2000" placeholder="مثال: مطلوب خلال أسبوع">
                </div>
            </div>

            <div style="margin-top:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <button type="submit" class="btn-action success" id="btnSubmitSupplyRequest">💾 تسجيل طلب التوريد</button>
                <span id="supplyCartCount" style="font-size:13px;font-weight:700;"></span>
                <span id="supplyRequestFormMessage" style="font-size:13px;"></span>
            </div>
        </form>
    </div>

    @if ($canManageSwaps)
    {{-- تبديل صنف مع المورد: يتسجّل هنا ويتنفّذ (خصم القديم + إضافة الجديد) بعد اعتماد أدمن المخازن من «اعتمادات المخزن». --}}
    <div class="panel inventory-wrap" style="margin-bottom:16px;" id="supplySwapPanel">
        <div class="panel-header">
            <h3>🔁 تبديل صنف مع المورد</h3>
        </div>
        <form id="supplySwapForm" class="panel-body" style="padding:16px;">
            <p style="margin:0 0 12px;font-size:13px;color:var(--text-muted);line-height:1.7;">
                بنسلّم المورد صنف من مخزوننا وبناخد بداله صنف تاني. بعد الاعتماد: الصنف الخارج يتخصم من المخزون بتكلفته الفعلية، والبديل يتضاف (ولو جديد يتعمل في الكتالوج تلقائياً باسمه ومواصفاته). مديونية المورد بتزيد بفرق السعر بس.
            </p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
                <div class="form-group">
                    <label for="swapOutSearch">الصنف الخارج من مخزوننا</label>
                    <input type="text" id="swapOutSearch" class="form-control" placeholder="كود أو اسم..." autocomplete="off">
                    <input type="hidden" id="swapOutItemId">
                    <div id="swapOutResults" class="supply-search-results" style="display:none;"></div>
                </div>
                <div class="form-group">
                    <label for="swapOutQty">الكمية الخارجة</label>
                    <input type="number" id="swapOutQty" class="form-control" min="0.01" step="any" value="1">
                </div>
                <div class="form-group">
                    <label for="swapSupplierId">المورد</label>
                    <select id="swapSupplierId" class="form-control">
                        <option value="">— اختر المورد —</option>
                        @foreach (\App\Models\Supplier::query()->orderBy('name')->get(['id', 'name']) as $sup)
                            <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="margin:12px 0;display:flex;flex-wrap:wrap;gap:16px;">
                <label style="display:flex;align-items:center;gap:6px;font-size:14px;">
                    <input type="radio" name="swapInMode" value="existing" checked> البديل صنف موجود بالكتالوج
                </label>
                <label style="display:flex;align-items:center;gap:6px;font-size:14px;">
                    <input type="radio" name="swapInMode" value="new"> البديل صنف جديد
                </label>
            </div>

            <div id="swapInExisting" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
                <div class="form-group">
                    <label for="swapInSearch">الصنف البديل</label>
                    <input type="text" id="swapInSearch" class="form-control" placeholder="كود أو اسم..." autocomplete="off">
                    <input type="hidden" id="swapInItemId">
                    <div id="swapInResults" class="supply-search-results" style="display:none;"></div>
                </div>
            </div>
            <div id="swapInNew" style="display:none;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
                <div class="form-group"><label for="swapNewName">اسم الصنف الجديد</label><input type="text" id="swapNewName" class="form-control" maxlength="255"></div>
                <div class="form-group"><label for="swapNewUom">الوحدة</label><input type="text" id="swapNewUom" class="form-control" maxlength="50" placeholder="نفس وحدة الخارج لو فاضية"></div>
                <div class="form-group"><label for="swapNewBrand">الماركة</label><input type="text" id="swapNewBrand" class="form-control" maxlength="255"></div>
                <div class="form-group"><label for="swapNewSpec">المواصفات</label><input type="text" id="swapNewSpec" class="form-control" maxlength="1000"></div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-top:12px;">
                <div class="form-group"><label for="swapInQty">الكمية الداخلة</label><input type="number" id="swapInQty" class="form-control" min="0.01" step="any" value="1"></div>
                <div class="form-group"><label for="swapPriceDiff">فرق السعر للمورد (ج.م)</label><input type="number" id="swapPriceDiff" class="form-control" min="0" step="0.01" value="0"></div>
                <div class="form-group"><label for="swapNotes">ملاحظات</label><input type="text" id="swapNotes" class="form-control" maxlength="1000"></div>
            </div>
            <div style="margin-top:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <button type="submit" class="btn-action success" id="btnSubmitSwap">🔁 تسجيل طلب التبديل</button>
                <span id="swapFormMessage" style="font-size:13px;"></span>
            </div>
        </form>
    </div>
    <script>
    (function () {
      var form = document.getElementById('supplySwapForm');
      if (!form) return;
      var swapUrl = @json($supplySwapsUrl);
      var csrf = document.querySelector('meta[name="csrf-token"]');
      function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
      function wire(inputId, hiddenId, resultsId) {
        var input = document.getElementById(inputId), hidden = document.getElementById(hiddenId), box = document.getElementById(resultsId), t;
        input.addEventListener('input', function () {
          hidden.value = '';
          clearTimeout(t);
          var q = input.value.trim();
          if (q.length < 1) { box.style.display = 'none'; return; }
          t = setTimeout(function () {
            fetch(((window.__SUPPLY_REQUEST_API || {}).search || '') + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
              .then(function (r) { return r.json(); })
              .then(function (res) {
                var items = res.items || res.data || res || [];
                if (!Array.isArray(items) || !items.length) { box.innerHTML = '<div style="padding:8px;font-size:12px;color:var(--text-muted);">لا نتائج</div>'; box.style.display = 'block'; return; }
                box.innerHTML = items.map(function (it) {
                  var label = (it.code ? it.code + ' — ' : '') + it.name;
                  return '<button type="button" data-id="' + it.id + '" data-label="' + esc(label) + '">' + esc(label) + (it.uom ? ' <small>(' + esc(it.uom) + ')</small>' : '') + '</button>';
                }).join('');
                box.style.display = 'block';
              }).catch(function () { box.style.display = 'none'; });
          }, 250);
        });
        box.addEventListener('click', function (e) {
          var b = e.target.closest('button[data-id]');
          if (!b) return;
          hidden.value = b.dataset.id;
          input.value = b.dataset.label;
          box.style.display = 'none';
        });
      }
      wire('swapOutSearch', 'swapOutItemId', 'swapOutResults');
      wire('swapInSearch', 'swapInItemId', 'swapInResults');
      function mode() { var r = form.querySelector('input[name="swapInMode"]:checked'); return r ? r.value : 'existing'; }
      form.querySelectorAll('input[name="swapInMode"]').forEach(function (r) {
        r.addEventListener('change', function () {
          document.getElementById('swapInExisting').style.display = mode() === 'existing' ? 'grid' : 'none';
          document.getElementById('swapInNew').style.display = mode() === 'new' ? 'grid' : 'none';
        });
      });
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var msg = document.getElementById('swapFormMessage');
        function show(text, color) { msg.textContent = text; msg.style.color = color; }
        var body = {
          out_stock_item_id: document.getElementById('swapOutItemId').value,
          out_qty: document.getElementById('swapOutQty').value,
          supplier_id: document.getElementById('swapSupplierId').value,
          in_qty: document.getElementById('swapInQty').value,
          price_difference: document.getElementById('swapPriceDiff').value || 0,
          notes: document.getElementById('swapNotes').value,
        };
        if (!body.out_stock_item_id) return show('اختر الصنف الخارج من نتائج البحث.', '#dc2626');
        if (!body.supplier_id) return show('اختر المورد.', '#dc2626');
        if (mode() === 'existing') {
          body.in_stock_item_id = document.getElementById('swapInItemId').value;
          if (!body.in_stock_item_id) return show('اختر الصنف البديل من نتائج البحث أو اختار «صنف جديد».', '#dc2626');
        } else {
          body.new_item = {
            name: document.getElementById('swapNewName').value.trim(),
            uom: document.getElementById('swapNewUom').value.trim(),
            brand: document.getElementById('swapNewBrand').value.trim(),
            spec: document.getElementById('swapNewSpec').value.trim(),
          };
          if (!body.new_item.name) return show('اكتب اسم الصنف الجديد.', '#dc2626');
        }
        var btn = document.getElementById('btnSubmitSwap');
        btn.disabled = true;
        fetch(swapUrl, {
          method: 'POST',
          headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : '', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
          body: JSON.stringify(body),
        }).then(function (r) { return r.json().then(function (j) { if (!r.ok) throw j; return j; }); })
          .then(function (res) {
            show(res.message || 'تم تسجيل طلب التبديل.', '#b45309');
            form.reset();
            document.getElementById('swapOutItemId').value = '';
            document.getElementById('swapInItemId').value = '';
            document.getElementById('swapInExisting').style.display = 'grid';
            document.getElementById('swapInNew').style.display = 'none';
          })
          .catch(function (err) {
            var m = err && err.errors ? Object.values(err.errors)[0][0] : (err && err.message) || 'تعذّر تسجيل طلب التبديل';
            show(m, '#dc2626');
          })
          .finally(function () { btn.disabled = false; });
      });
    })();
    </script>
    @endif

    <div class="panel inventory-wrap" style="margin-bottom:16px;">
        <div class="panel-header">
            <h3>📋 طلبات توريد مسجّلة</h3>
            <div style="display:flex;align-items:center;gap:10px;">
                <button type="button" class="btn-action primary" id="btnPrintSupplyRequests">🖨️ طباعة الطلبات</button>
                <span class="badge" id="supplyOpenLinesBadge">0</span>
            </div>
        </div>
        <div class="stock-table-wrap" style="padding:0 16px 16px;">
            <table class="stock-table" id="supplyOpenLinesTable">
                <thead>
                    <tr>
                        <th>رقم الطلب</th>
                        <th>النوع</th>
                        <th>الصنف / الوصف</th>
                        <th class="col-qty">الكمية</th>
                        <th>الوحدة</th>
                        <th>الحالة</th>
                        <th>تاريخ الطلب</th>
                        <th>تاريخ الاستلام</th>
                        <th>إجراء</th>
                    </tr>
                </thead>
                <tbody id="supplyOpenLinesBody">
                    <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:16px;">لا توجد طلبات مفتوحة.</td></tr>
                </tbody>
            </table>
        </div>
        <div id="supplyResolvePanel" class="panel-body" style="padding:16px;border-top:1px solid var(--border);display:none;">
            <h4 style="margin:0 0 8px;">🔗 ربط / ترميز صنف غير مكود</h4>
            <p id="supplyResolveLineLabel" style="font-size:13px;color:var(--text-muted);margin:0 0 12px;"></p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;align-items:end;">
                <div class="form-group">
                    <label>بحث صنف كتالوج للربط</label>
                    <input type="text" id="supplyResolveSearch" class="form-control" placeholder="كود أو اسم..." autocomplete="off">
                    <input type="hidden" id="supplyResolveStockItemId">
                    <div id="supplyResolveSearchResults" class="supply-search-results" style="display:none;"></div>
                </div>
                <div class="form-group">
                    <button type="button" class="btn-action primary" id="btnConfirmSupplyResolve">تأكيد الربط</button>
                    <button type="button" class="btn-view" id="btnCancelSupplyResolve">إلغاء</button>
                </div>
            </div>
            @if ($addCatalogItemUrl)
            <p style="margin:12px 0 0;font-size:12px;color:var(--text-muted);">
                أو <a href="{{ $addCatalogItemUrl }}" target="_blank" rel="noopener">إضافة صنف جديد</a> ثم ابحث عنه هنا للربط.
            </p>
            @endif
            <div id="supplyResolveMessage" style="margin-top:8px;font-size:13px;"></div>
        </div>
    </div>

    <div class="panel inventory-wrap">
        <div class="panel-header">
            <h3>{{ $deskTitle }}</h3>
            <div style="display:flex;align-items:center;gap:10px;">
                <span class="badge" id="inventoryBadge">{{ ($inventory_items_total ?? count($inventory_items ?? [])) }} صنف</span>
            </div>
        </div>

        <p style="padding:0 16px 8px;margin:0;color:var(--text-muted);font-size:13px;line-height:1.6;">
            الأصناف ذات المتاح السالب أو المحجوز أكثر من الرصيد تظهر هنا كـ «طلب توريد» من حجز المخزن. الطلبات اليدوية (مكودة أو غير مكودة) تُسجَّل أعلاه. سجّل الاستلام من صفحة «استلام الوارد» عند وصول الفاتورة.
        </p>

        <div class="inventory-toolbar">
            <input type="text" id="inventorySearch" placeholder="بحث بالكود، الاسم، أو الباركود (امسح و Enter)...">
            <div class="filter-pills" id="inventoryFilters">
                <button class="filter-pill{{ $defaultFilter === 'all' ? ' active' : '' }}" data-filter="all">الكل</button>
                <button class="filter-pill{{ $defaultFilter === 'ok' ? ' active' : '' }}" data-filter="ok">✓ متوفر</button>
                <button class="filter-pill{{ $defaultFilter === 'low' ? ' active' : '' }}" data-filter="low">⚠ منخفض</button>
                <button class="filter-pill{{ $defaultFilter === 'backorder' ? ' active' : '' }}" data-filter="backorder">🛒 طلب توريد</button>
            </div>
            <div class="export-btns">
                <button class="btn-export excel" onclick="exportInventory('excel')">📊 Excel</button>
                <button class="btn-export pdf" onclick="exportInventory('pdf')">📄 PDF</button>
            </div>
        </div>

        <div class="stock-table-wrap">
            @unless ($inventory_list_enabled ?? true)
                <p style="text-align:center;color:var(--text-muted);padding:24px;">
                    قائمة المخزن غير مفعّلة لدورك — راجع «عرض قوائم الأصناف» في الإعدادات.
                </p>
            @else
            <table data-paginate="10" class="stock-table">
                <thead>
                    <tr id="inventoryTableHead">
                        @foreach ($inventoryListColumns as $colKey)
                            <th class="{{ in_array($colKey, ['available', 'status', 'qty', 'reserved'], true) ? 'col-qty' : '' }}">
                                {{ $inventoryListColumnLabels[$colKey]['label'] ?? $colKey }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody id="inventoryTable" data-server-inventory="1"></tbody>
                <tfoot>
                    <tr>
                        <td colspan="{{ max(1, count($inventoryListColumns)) }}" id="inventoryFooter"></td>
                    </tr>
                </tfoot>
            </table>
            @endunless
        </div>
    </div>
</div>
<style>
.supply-search-results {
    margin-top:6px;
    border:1px solid var(--border);
    border-radius:8px;
    max-height:220px;
    overflow:auto;
    background:var(--surface, #fff);
}
.supply-search-results button {
    display:block;
    width:100%;
    text-align:left;
    padding:8px 10px;
    border:none;
    background:transparent;
    cursor:pointer;
    font-size:13px;
}
.supply-search-results button:hover { background:rgba(37,99,235,0.08); }
</style>
<script>
window.__INVENTORY_ITEMS = @json($inventory_items ?? []);
window.__INVENTORY_LIST_COLUMNS = @json($inventoryListColumns);
window.__INVENTORY_DEFAULT_FILTER = @json($defaultFilter);
window.__INVENTORY_LIST_URL = @json($listUrl);
window.__SUPPLY_REQUEST_API = {
    list: @json($supplyRequestsUrl),
    store: @json($supplyRequestsStoreUrl),
    search: @json($supplySearchItemsUrl),
    resolveBase: @json($supplyResolveUrlBase),
    print: @json($supplyPrintUrl),
    receiveInbound: @json($receiveInboundUrl),
};
</script>
