<aside class="sidebar">
    <div class="sidebar-brand">
      <div class="icon">📦</div>
      <h2>لوحة المخزون</h2>
      <span>إدارة الأصناف والكميات</span>
    </div>
    <!-- <div class="privacy-notice">
      <strong>🔒 بدون أسعار</strong>
      هذه الشاشة تعرض مواصفات المخزون والكميات فقط — جميع البيانات المالية محجوبة.
    </div> -->
    <ul class="nav-menu">
      <li><a href="#" class="active" data-section="inventory"><span class="nav-icon">📦</span> المخزون</a></li>
      <li><a href="#" data-section="bom"><span class="nav-icon">📋</span> صرف المواد للورشة</a></li>
      <li><a href="#" data-section="returns"><span class="nav-icon">↩️</span> إذن ارتجاع</a></li>
    </ul>

  </aside>

  <main class="main">
    <div class="page-header">
      <div>
        <h1 id="pageTitle">المخزون — توفر الأصناف</h1>
        <p></p>
      </div>
      <div class="user-chip">
        <div class="avatar"></div>
        <span></span>
      </div>
    </div>

    <!-- Inventory Section -->
    <div class="section-view active" id="section-inventory">
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

    <!-- BOM Section -->
    <div class="section-view" id="section-bom">
      <div id="analytics-bom">@include('partials.dashboard-analytics-empty', ['hide_charts' => true, 'stats' => [
        ['icon' => '📦', 'label' => 'خام', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
        ['icon' => '🏭', 'label' => 'تحت التشغيل', 'value' => '0', 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
        ['icon' => '✅', 'label' => 'تام', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '💰', 'label' => 'قيمة إجمالية', 'value' => '0', 'bg' => 'rgba(124,58,237,0.1)'],
      ]])</div>
      <div class="panel inventory-wrap">
        <div class="panel-header">
          <h3>📋 قوائم صرف المواد — خام → تحت التشغيل → تام</h3>
          <span class="badge" id="bomBadge">0 قوائم</span>
        </div>

        <div class="bom-summary" id="bomSummary"></div>

        <div class="inventory-toolbar bom-toolbar">
          <input type="text" id="bomSearch" placeholder="بحث بالمريض أو أمر التشغيل...">
          <div class="filter-pills" id="bomFilters">
            <button class="filter-pill active" data-bomfilter="all">الكل</button>
            <button class="filter-pill" data-bomfilter="raw">📦 خام</button>
            <button class="filter-pill" data-bomfilter="wip">🏭 تحت التشغيل</button>
            <button class="filter-pill" data-bomfilter="finished">✅ تام</button>
          </div>
          <div class="export-btns">
            <button class="btn-export excel" onclick="exportBom('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportBom('pdf')">📄 PDF</button>
          </div>
        </div>

        <div class="bom-table-wrap">
          <table data-paginate="10" class="bom-table">
            <thead>
              <tr>
                <th>رقم القائمة</th>
                <th>المريض</th>
                <th>أمر التشغيل</th>
                <th>المرحلة</th>
                <th>البنود</th>
                <th class="col-actions">إجراء</th>
              </tr>
            </thead>
            <tbody id="bomTable"></tbody>
            <tfoot>
              <tr>
                <td colspan="6" id="bomFooter">—</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

    <!-- Returns Section -->
    <div class="section-view" id="section-returns">
      <div class="panel inventory-wrap">
        <div class="panel-header">
          <h3>↩️ إذن ارتجاع — ورشة → مخزن</h3>
          <span class="badge" id="returnsBadge">0</span>
        </div>
        <p style="padding:0 24px 12px;margin:0;color:var(--text-muted);font-size:13px;">
          ارتجاع داخلي مرتبط بـ <strong>BOM وأمر التشغيل</strong> — كامل أو جزئي. متاح فقط أثناء «تحت التشغيل». لا يؤثر على مديونية الجهة.
        </p>
        <div class="bom-summary" id="returnsSummary"></div>
        <div class="inventory-toolbar bom-toolbar">
          <button type="button" class="btn-view" id="btnNewReturn">➕ إنشاء إذن ارتجاع</button>
        </div>
        <div class="bom-table-wrap">
          <table data-paginate="10" class="bom-table">
            <thead>
              <tr>
                <th>رقم الإذن</th>
                <th>أمر التشغيل</th>
                <th>المريض</th>
                <th>البنود</th>
                <th>الحالة</th>
                <th class="col-actions">إجراء</th>
              </tr>
            </thead>
            <tbody id="returnsTable"></tbody>
          </table>
        </div>
      </div>
    </div>
  </main>

  <!-- Barcode Issue Modal -->
  <div class="modal-overlay" id="barcodeModal">
    <div class="modal">
      <div class="modal-header">
        <h3>صرف بالباركود — مطابقة أمر التشغيل</h3>
        <button type="button" class="modal-close" id="closeBarcodeModal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="barcode-required" id="barcodeRequired"></div>
        <div class="barcode-scan-row">
          <input type="text" id="barcodeInput" placeholder="امسح أو اكتب باركود الصنف ثم Enter (مثل BC-001)">
          <button type="button" class="btn-view" id="btnAddScan">إضافة مسح</button>
        </div>
        <div class="barcode-sim-row">
          <button type="button" class="btn-action primary" id="btnSimCorrect">✓ محاكاة مسح صحيح (كل البنود)</button>
          <button type="button" class="btn-action" id="btnSimWrong" style="background:#fee2e2;color:#b91c1c;">✗ محاكاة باركود خاطئ</button>
        </div>
        <div class="barcode-scanned" id="barcodeScanned"></div>
        <div class="barcode-alarm" id="barcodeAlarm" style="display:none;">
          ⛔ <span id="barcodeAlarmText">باركود غير مطابق لأمر التشغيل! تم إيقاف الصرف.</span>
        </div>
        <div style="margin-top:18px;display:flex;gap:10px;justify-content:flex-end;">
          <button type="button" class="btn-view" id="btnCancelBarcode">إلغاء</button>
          <button type="button" class="btn-action success" id="btnConfirmIssue">تأكيد الصرف</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Receive Stock (Inward) Modal -->
  <div class="modal-overlay" id="receiveModal">
    <div class="modal">
      <div class="modal-header">
        <h3>📥 حركة وارد بالباركود — تحديث WAC</h3>
        <button type="button" class="modal-close" id="closeReceiveModal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="receive-grid">
          <div class="form-group"><label>الصنف</label>
            <select class="form-control" id="rcvItem"></select>
          </div>
          <div class="form-group"><label>الكمية الواردة</label>
            <input type="number" class="form-control" id="rcvQty" min="1" value="10">
          </div>
          <div class="form-group"><label>سعر الشراء (للوحدة)</label>
            <input type="number" class="form-control" id="rcvAmount" min="0" value="0">
          </div>
          <div class="form-group"><label>المورد</label>
            <input type="text" class="form-control" id="rcvSupplier" placeholder="اسم المورد">
          </div>
          <div class="form-group"><label>رقم فاتورة الشراء</label>
            <input type="text" class="form-control" id="rcvInvoice" placeholder="INV-...">
          </div>
          <div class="form-group"><label>تاريخ التوريد</label>
            <input type="text" class="form-control" id="rcvDate" value="">
          </div>
        </div>
        <div class="receive-wac" id="rcvWacPreview">—</div>
        <div style="margin-top:16px;display:flex;gap:10px;justify-content:flex-end;">
          <button type="button" class="btn-view" id="btnCancelReceive">إلغاء</button>
          <button type="button" class="btn-action success" id="btnConfirmReceive">تأكيد الاستلام</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Create Return Note Modal -->
  <div class="modal-overlay" id="returnCreateModal">
    <div class="modal">
      <div class="modal-header">
        <h3>➕ إنشاء إذن ارتجاع</h3>
        <button type="button" class="modal-close" id="closeReturnCreateModal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group"><label>BOM (تحت التشغيل فقط)</label>
          <select class="form-control" id="returnBomSelect"></select>
        </div>
        <div id="returnLinesPicker"></div>
        <div class="form-group"><label>سبب الارتجاع</label>
          <input type="text" class="form-control" id="returnReason" placeholder="مثال: فائض عن الحاجة في الورشة">
        </div>
        <div style="margin-top:16px;display:flex;gap:10px;justify-content:flex-end;">
          <button type="button" class="btn-view" id="btnCancelReturnCreate">إلغاء</button>
          <button type="button" class="btn-action success" id="btnConfirmReturnCreate">إصدار الإذن</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Return Barcode Scan Modal -->
  <div class="modal-overlay" id="returnScanModal">
    <div class="modal">
      <div class="modal-header">
        <h3>↩️ مسح باركود الارتجاع — استعادة للمخزن</h3>
        <button type="button" class="modal-close" id="closeReturnScanModal">&times;</button>
      </div>
      <div class="modal-body">
        <div id="returnScanInfo"></div>
        <div class="barcode-scan-row">
          <input type="text" id="returnBarcodeInput" placeholder="امسح باركود الصنف (مثل BC-005)">
          <input type="number" id="returnQtyInput" min="1" value="1" style="width:80px;" title="الكمية">
          <button type="button" class="btn-view" id="btnReturnScan">تسجيل ارتجاع</button>
        </div>
        <div class="barcode-alarm" id="returnScanAlarm" style="display:none;">
          ⛔ <span id="returnScanAlarmText">باركود غير مطابق!</span>
        </div>
        <div style="margin-top:16px;display:flex;gap:10px;justify-content:flex-end;">
          <button type="button" class="btn-view" id="btnCloseReturnScan">إغلاق</button>
        </div>
      </div>
    </div>
  </div>

  <div class="toast" id="toast"></div>