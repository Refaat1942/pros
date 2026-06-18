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
          📋 بعد حساب التكلفة يتوقف الطلب عند موافقة الأدمن — ثم يُحوَّل للاستقبال لإصدار عرض السعر
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
          <table class="pricing-table">
            <thead>
              <tr>
                <th>#</th>
                <th>رقم الطلب</th>
                <th>المريض</th>
                <th>التاريخ</th>
                <th class="col-center">البنود</th>
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
