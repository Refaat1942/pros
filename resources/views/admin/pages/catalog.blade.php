<div class="section-view" id="section-catalog">
@php
    $categories = $stock_categories ?? collect();
    $catalogSuppliers = $suppliers ?? collect();
@endphp
      <div class="panel">
        <div class="panel-header">
          <h3>📦 الأصناف والأسعار</h3>
          <span class="badge" id="catalogCount">0 صنف</span>
        </div>
        <div class="data-toolbar">
          <input type="text" id="catalogSearch" placeholder="🔍 بحث بالصنف أو الكود...">
          <select id="catalogCategoryFilter">
            <option value="all">كل الفئات</option>
            @foreach ($categories as $cat)
              <option value="{{ $cat->id }}">{{ $cat->name }}</option>
            @endforeach
          </select>
          <button type="button" class="btn-action" id="btnToggleCatalogForm" style="background:var(--primary);color:white;border:none;padding:9px 16px;border-radius:8px;cursor:pointer;font-family:'Tajawal',sans-serif;font-weight:600;">➕ إضافة صنف</button>
          <span class="toolbar-count" id="catalogFilteredCount">0 صنف</span>
          <div class="export-btns">
            <button class="btn-export excel" onclick="exportCatalog('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportCatalog('pdf')">📄 PDF</button>
          </div>
        </div>

        <div class="catalog-form" id="catalogForm">
          <input type="hidden" id="catalogEditCode" value="">
          <div class="catalog-form-grid">
            <div>
              <label>اسم الصنف *</label>
              <input type="text" id="catalogName" placeholder="مثال: ركبة هيدروليكية">
            </div>
            <div>
              <label>المواصفات</label>
              <input type="text" id="catalogSpec" placeholder="Medium — Ottobock">
            </div>
            <div>
              <label>الفئة *</label>
              <select id="catalogCategory">
                <option value="">— اختر الفئة —</option>
                @foreach ($categories as $cat)
                  <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label>الكمية الابتدائية</label>
              <input type="number" id="catalogQty" min="0" value="0">
            </div>
          </div>
          <div class="prices-block">
            <h4>💰 أسعار الصنف (متعددة — أكواد / موردين)</h4>
            <div id="itemPricesList"></div>
            <button type="button" class="btn-add-price" id="btnAddPriceRow">+ إضافة سعر</button>
          </div>
          <div class="catalog-form-actions">
            <button type="button" class="btn-action" id="btnCancelCatalog">إلغاء</button>
            <button type="button" class="btn-action" id="btnSaveCatalog" style="background:var(--primary);color:white;border:none;">💾 حفظ الصنف</button>
          </div>
        </div>

        <div class="panel-body">
          <table data-paginate="10">
            <thead>
              <tr>
                <th>الكود</th>
                <th>الصنف</th>
                <th>الفئة</th>
                <th>المواصفات</th>
                <th>الكمية</th>
                <th>إجراء</th>
              </tr>
            </thead>
            <tbody id="catalogTable"></tbody>
          </table>
        </div>
      </div>
    </div>
<script>
window.__CATALOG_SUPPLIERS = @json($catalogSuppliers->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values());
</script>
