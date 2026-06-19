<div class="section-view" id="section-catalog">
      <div id="analytics-catalog">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '📦', 'label' => 'أصناف', 'value' => '0', 'bg' => 'rgba(124,58,237,0.1)'],
        ['icon' => '💰', 'label' => 'أسعار مسجلة', 'value' => '0', 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.1)'],
        ['icon' => '🏷️', 'label' => 'متعدد الأسعار', 'value' => '0', 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
        ['icon' => '📊', 'label' => 'فئات', 'value' => '0', 'bg' => 'rgba(217,119,6,0.1)'],
      ]])</div>
      <div class="panel">
        <div class="panel-header">
          <h3>📦 الأصناف والأسعار</h3>
          <span class="badge" id="catalogCount">0 صنف</span>
        </div>
        <div class="data-toolbar">
          <input type="text" id="catalogSearch" placeholder="🔍 بحث بالصنف أو الكود...">
          <select id="catalogCategoryFilter">
            <option value="all">كل الفئات</option>
            <option value="مفاصل">مفاصل</option>
            <option value="أقدام">أقدام</option>
            <option value="بطانات">بطانات</option>
            <option value="محولات">محولات</option>
            <option value="إكسسوارات">إكسسوارات</option>
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
                <option value="مفاصل">مفاصل</option>
                <option value="أقدام">أقدام</option>
                <option value="بطانات">بطانات</option>
                <option value="محولات">محولات</option>
                <option value="إكسسوارات">إكسسوارات</option>
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
