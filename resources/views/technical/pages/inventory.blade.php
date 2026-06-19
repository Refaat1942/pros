<div class="section-view" id="section-inventory">
      <div id="analytics-inventory-charts">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '💚', 'label' => 'صحة المخزون', 'value' => '0/100', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '✅', 'label' => 'متوفر', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '⚠️', 'label' => 'منخفض', 'value' => '0', 'color' => '#dc2626', 'bg' => 'rgba(220,38,38,0.1)'],
        ['icon' => '🔒', 'label' => 'محجوز', 'value' => '0', 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
      ]])</div>
      <div class="panel inventory-wrap">
        <div class="panel-header">
          <h3>📦 توفر المخزون — الكميات المتاحة</h3>
          <div style="display:flex;align-items:center;gap:10px;">
            <button type="button" class="btn-action primary" id="btnReceiveStock">📥 استلام وارد</button>
            <span class="badge" id="inventoryBadge">0 صنف</span>
          </div>
        </div>

        <div class="inventory-summary">
          <div class="inv-stat">
            <div class="inv-stat-icon total">📦</div>
            <div>
              <div class="inv-stat-label">إجمالي الأصناف</div>
              <div class="inv-stat-value" id="invTotal">0</div>
            </div>
          </div>
          <div class="inv-stat">
            <div class="inv-stat-icon ok">✅</div>
            <div>
              <div class="inv-stat-label">متوفر</div>
              <div class="inv-stat-value" id="invOk" style="color:#047857">0</div>
            </div>
          </div>
          <div class="inv-stat">
            <div class="inv-stat-icon low">⚠️</div>
            <div>
              <div class="inv-stat-label">كمية منخفضة</div>
              <div class="inv-stat-value" id="invLow" style="color:#b91c1c">0</div>
            </div>
          </div>
          <div class="inv-stat">
            <div class="inv-stat-icon total">🔢</div>
            <div>
              <div class="inv-stat-label">إجمالي الوحدات</div>
              <div class="inv-stat-value" id="invUnits">0</div>
            </div>
          </div>
          <div class="inv-stat">
            <div class="inv-stat-icon reserved">🔒</div>
            <div>
              <div class="inv-stat-label">محجوز للطلبات</div>
              <div class="inv-stat-value" id="invReserved" style="color:#0e7490">0</div>
            </div>
          </div>
          <div class="inv-stat">
            <div class="inv-stat-icon critical">🚨</div>
            <div>
              <div class="inv-stat-label">حرج (≤20%)</div>
              <div class="inv-stat-value" id="invCritical" style="color:#b91c1c">0</div>
            </div>
          </div>
        </div>

        <div class="inventory-health-panel">
          <div class="health-gauge">
            <div class="health-gauge-ring" id="invHealthRing" style="background:conic-gradient(#e2e8f0 0 360deg)">
              <div class="health-gauge-ring-inner"><span id="invHealthScore">0</span><span>/100</span></div>
            </div>
            <div class="health-gauge-label">صحة المخزون</div>
            <div class="health-gauge-sub" id="invHealthLabel"></div>
          </div>
          <div class="health-details" id="invHealthDetails"></div>
        </div>

        <div class="inventory-alerts" id="invAlerts">
          <h4>⚠️ تنبيهات المخزون</h4>
        </div>

        <div class="category-chips" id="invCategories"></div>

        <div class="inventory-readonly-banner">
          <span>👁️</span>
          <span><strong>عرض فقط</strong> — تعريف الأصناف وأسعارها من <strong>لوحة الإدارة</strong>. المخزون يعرض الكميات والتوفر فقط.</span>
        </div>

        <div class="inventory-toolbar">
          <input type="text" id="inventorySearch" placeholder="بحث بالصنف أو المواصفات...">
          <div class="filter-pills" id="inventoryFilters">
            <button class="filter-pill active" data-filter="all">الكل</button>
            <button class="filter-pill" data-filter="ok">✓ متوفر</button>
            <button class="filter-pill" data-filter="low">⚠ منخفض</button>
          </div>
          <div class="export-btns">
            <button class="btn-export excel" onclick="exportInventory('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportInventory('pdf')">📄 PDF</button>
          </div>
        </div>

        <div class="stock-table-wrap">
          <table data-paginate="10" class="stock-table">
            <thead>
              <tr>
                <th>#</th>
                <th>الصنف</th>
                <th>المواصفات</th>
                <th class="col-qty">الكمية المتاحة</th>
                <th class="col-reserved">محجوز</th>
                <th class="col-status">الحالة</th>
              </tr>
            </thead>
            <tbody id="inventoryTable"></tbody>
            <tfoot>
              <tr>
                <td colspan="6" id="inventoryFooter"></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
