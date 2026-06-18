<div id="analytics-orders">@include('partials.dashboard-analytics-empty', ['stats' => [
      ['icon' => '📥', 'label' => 'طلبات صرف', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
      ['icon' => '📅', 'label' => 'طلبات اليوم', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
      ['icon' => '📊', 'label' => '—', 'value' => '0', 'bg' => 'rgba(100,116,139,0.1)'],
      ['icon' => '📊', 'label' => '—', 'value' => '0', 'bg' => 'rgba(100,116,139,0.1)'],
    ]])</div>

<div class="section-view" id="section-orders">
    <div id="analytics-orders">@include('partials.dashboard-analytics-empty', ['stats' => [
      ['icon' => '📥', 'label' => 'طلبات صرف', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
      ['icon' => '📅', 'label' => 'طلبات اليوم', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
      ['icon' => '📊', 'label' => '—', 'value' => '0', 'bg' => 'rgba(100,116,139,0.1)'],
      ['icon' => '📊', 'label' => '—', 'value' => '0', 'bg' => 'rgba(100,116,139,0.1)'],
    ]])</div>
    <div class="content-grid">
      <div class="panel">
        <div class="panel-header">
          <h3>📥 طلبات التوصيف — إرسال للتسعير (قبل التصنيع)</h3>
          <span class="badge" id="ordersCount">0</span>
        </div>
        <div class="orders-toolbar">
          <input type="text" id="ordersSearch" placeholder="🔍 بحث برقم الطلب أو اسم المريض...">
          <div class="export-btns">
            <button class="btn-export excel" onclick="exportOrders('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportOrders('pdf')">📄 PDF</button>
          </div>
        </div>
        <div class="panel-body">
          <ul class="order-list" id="ordersList"></ul>
        </div>
      </div>

      <div class="panel">
        <div class="panel-header">
          <h3>📋 تفاصيل طلب الصرف</h3>
        </div>
        <div class="panel-body">
          <div id="emptyState" class="empty-state">
            <div class="icon">📥</div>
            <p>اختر طلب صرف من القائمة لعرض تفاصيل الأصناف المطلوبة</p>
          </div>

          <form id="specForm" class="spec-form" style="display:none;">
            <div class="patient-banner visible" id="patientBanner">
              <h4 id="bannerName">—</h4>
              <div class="details">
                <span>رقم الطلب: <strong id="bannerOrderId">—</strong></span>
                <span>الطبيب: <strong id="bannerDoctor">—</strong></span>
                <span>التاريخ: <strong id="bannerDate">—</strong></span>
              </div>
            </div>

            <div class="order-detail-grid">
              <div class="order-detail-item">
                <div class="label">جهة التعاقد</div>
                <div class="value" id="bannerCompany">—</div>
              </div>
              <div class="order-detail-item">
                <div class="label">عدد الأصناف</div>
                <div class="value" id="bannerItemCount">—</div>
              </div>
              <div class="order-detail-item">
                <div class="label">حالة الطلب</div>
                <div class="value" style="color:var(--warning);">بانتظار الصرف</div>
              </div>
            </div>

            <div class="form-section">
              <div class="form-section-title">📦 الأصناف المطلوبة من العيادة</div>
              <div class="stock-table-wrap">
                <table class="stock-table">
                  <thead>
                    <tr>
                      <th>الصنف</th>
                      <th>الكود</th>
                      <th>الفئة</th>
                      <th class="col-qty">الكمية المطلوبة</th>
                      <th class="col-qty">المتوفر</th>
                      <th class="col-status">حالة المخزون</th>
                    </tr>
                  </thead>
                  <tbody id="orderItemsBody"></tbody>
                </table>
              </div>
            </div>

            <div class="form-group">
              <label>ملاحظات مخزون (اختياري)</label>
              <textarea class="form-control" id="techNotes" rows="3" placeholder="أي ملاحظات خاصة بالصرف أو الكميات..."></textarea>
            </div>

            <div class="form-actions">
              <button type="submit" class="btn btn-primary" id="submitSpec">
                📤 اعتماد التوصيف وإرسال للتسعير
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
    </div>
