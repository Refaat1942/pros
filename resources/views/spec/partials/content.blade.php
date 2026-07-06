<aside class="sidebar">
    <div class="sidebar-brand">
      <div class="icon">📐</div>
      <h2>لوحة التوصيف</h2>
      <span>أكواد وكميات — قبل التصنيع</span>
    </div>
    <ul class="nav-menu">
      <li><a href="#" class="active" data-section="orders"><span class="nav-icon">📥</span> طلبات التوصيف</a></li>
      <li><a href="#" data-section="spec"><span class="nav-icon">👁️</span> معاينة التوصيف</a></li>
      <li><a href="#" data-section="pricing"><span class="nav-icon">💰</span> إرسال للتسعير</a></li>
    </ul>

  </aside>

  <main class="main">
    <div class="page-header">
      <div>
        <h1 id="pageTitle">طلبات التوصيف — إرسال للتسعير</h1>
        <p></p>
      </div>
      <div class="user-chip">
        <div class="avatar"></div>
        <span></span>
      </div>
    </div>

    <!-- Orders Section -->
    <div class="section-view active" id="section-orders">
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
          <ul class="order-list" id="ordersList" data-paginate="10"></ul>
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
                <table data-paginate="10" class="stock-table">
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
                📤 اعتماد وإرسال
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
    </div>

    <!-- Spec Section (standalone) -->
    <div class="section-view" id="section-spec">
      <div id="analytics-spec">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '📤', 'label' => 'عمليات صرف', 'value' => '0', 'bg' => 'rgba(217,119,6,0.1)'],
        ['icon' => '📋', 'label' => 'للتسعير', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '🔩', 'label' => 'متوسط البنود', 'value' => '0', 'bg' => 'rgba(217,119,6,0.1)'],
        ['icon' => '⏱️', 'label' => 'متوسط الوقت', 'value' => '—', 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
      ]])</div>
      <div class="panel">
        <div class="panel-header">
          <h3>📦 معاينة التوصيف — بدون صرف مخزني</h3>
        </div>
        <div class="panel-body" style="padding:24px;">
          <p style="font-size:14px;color:var(--text-muted);margin-bottom:16px;">هذا القسم للمعاينة فقط — الصرف الفعلي يتم من <strong>لوحة المخزون (BOM)</strong> بعد موافقة العميل.</p>
          <ul class="order-list" id="ordersListSpec" style="margin-bottom:20px;border:1px solid var(--border);border-radius:10px;"></ul>
          <div id="specSectionHint" class="empty-state" style="padding:24px;">
            <div class="icon">📦</div>
            <p>اختر طلب صرف لعرض تفاصيل الأصناف المطلوبة</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Pricing Section -->
    <div class="section-view" id="section-pricing">
      <div id="analytics-pricing">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '📋', 'label' => 'طلبات', 'value' => '0', 'bg' => 'rgba(217,119,6,0.1)'],
        ['icon' => '⏳', 'label' => 'انتظار موافقة الأدمن', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
        ['icon' => '✅', 'label' => 'جاهز للاستقبال', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '🔩', 'label' => 'متوسط البنود', 'value' => '0', 'bg' => 'rgba(217,119,6,0.1)'],
      ]])</div>
      <div class="panel pricing-wrap">
        <div class="panel-header">
          <h3>💰 طلبات مرسلة للتسعير</h3>
          <span class="badge" id="pricingCount">0</span>
        </div>

        <div class="pricing-summary">
          <div class="pricing-stat">
            <div class="ps-icon" style="background:rgba(217,119,6,0.12)">📋</div>
            <div>
              <div class="ps-label">إجمالي الطلبات</div>
              <div class="ps-value" id="prTotal">0</div>
            </div>
          </div>
          <div class="pricing-stat">
            <div class="ps-icon" style="background:rgba(217,119,6,0.12)">⏳</div>
            <div>
              <div class="ps-label">في انتظار موافقة الأدمن</div>
              <div class="ps-value" id="prPending" style="color:#b45309">0</div>
            </div>
          </div>
          <div class="pricing-stat">
            <div class="ps-icon" style="background:rgba(5,150,105,0.12)">✅</div>
            <div>
              <div class="ps-label">أُرسل للاستقبال</div>
              <div class="ps-value" id="prSent" style="color:#047857">0</div>
            </div>
          </div>
        </div>

        <div class="pricing-info-banner">
          📋 بعد إرسال التوصيف تمر الحالة بالمعدلات ثم تتوقف عند <strong>التكاليف</strong> للمراجعة قبل إصدار عرض السعر.
        </div>

        <div class="pricing-toolbar">
          <input type="text" id="pricingSearch" placeholder="بحث برقم الطلب أو اسم المريض...">
          <div class="filter-pills" id="pricingFilters">
            <button class="filter-pill active" data-prfilter="all">الكل</button>
            <button class="filter-pill" data-prfilter="pending">⏳ في انتظار موافقة الأدمن</button>
            <button class="filter-pill" data-prfilter="sent">✅ معتمد — جاهز للاستقبال</button>
          </div>
          <div class="export-btns">
            <button class="btn-export excel" onclick="exportPricing('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportPricing('pdf')">📄 PDF</button>
          </div>
        </div>

        <div class="pricing-table-wrap">
          <table data-paginate="10" class="pricing-table">
            <thead>
              <tr>
                <th>#</th>
                <th>رقم الطلب</th>
                <th>المريض</th>
                <th>التاريخ</th>
                <th class="col-center">عدد الأصناف</th>
                <th class="col-center">الحالة / التقدم</th>
                <th class="col-actions">إجراء</th>
              </tr>
            </thead>
            <tbody id="pricingTable"></tbody>
            <tfoot>
              <tr>
                <td colspan="7" id="pricingFooter">عرض 3 من 3 طلبات</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

  </main>

  <!-- Pricing Detail Modal -->
  <div class="modal-overlay" id="pricingModal">
    <div class="modal">
      <div class="modal-header">
        <h3 id="pricingModalTitle">🧾 تفاصيل طلب التسعير</h3>
        <button type="button" class="modal-close" id="closePricingModal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="pricing-detail-meta" id="pricingModalMeta"></div>
        <div class="pricing-detail-steps-wrap">
          <h4>📊 مسار التسعير</h4>
          <div id="pricingModalSteps"></div>
        </div>
        <div class="pricing-detail-items">
          <h4>📦 الأصناف المطلوبة</h4>
          <div class="stock-table-wrap">
            <table data-paginate="10" class="stock-table">
              <thead>
                <tr>
                  <th>الصنف</th>
                  <th>الكود</th>
                  <th>الفئة</th>
                  <th class="col-qty">الكمية</th>
                  <th class="col-qty">المتاح</th>
                  <th class="col-status">التوفر</th>
                </tr>
              </thead>
              <tbody id="pricingModalItems"></tbody>
            </table>
          </div>
        </div>
        <div class="pricing-detail-note">
          📋 الطلب يظهر للإدارة للاعتماد — بعد الموافقة يُرسل تلقائياً للاستقبال.
        </div>
        <div style="margin-top:20px;display:flex;justify-content:flex-end;">
          <button type="button" class="btn-view" id="btnClosePricingModal">إغلاق</button>
        </div>
      </div>
    </div>
  </div>

  <div class="toast" id="toast"></div>