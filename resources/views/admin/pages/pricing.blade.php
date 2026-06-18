<div class="section-view" id="section-pricing">
      <div id="analytics-pricing">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '⏳', 'label' => 'انتظار موافقة الأدمن', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
        ['icon' => '✅', 'label' => 'جاهز لعرض السعر', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '📋', 'label' => 'إجمالي الطلبات', 'value' => '0', 'bg' => 'rgba(124,58,237,0.1)'],
        ['icon' => '💰', 'label' => 'قيمة معلقة', 'value' => '0', 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.1)'],
      ]])</div>
      <div class="panel">
        <div class="panel-header">
          <h3>✅ اعتماد طلبات التسعير</h3>
          <span class="badge" id="pricingApprovalBadge">0</span>
        </div>
        <div class="data-toolbar">
          <input type="text" id="pricingApprovalSearch" placeholder="🔍 بحث برقم الطلب أو اسم المريض...">
          <select id="pricingApprovalFilter">
            <option value="pending">في انتظار موافقة الأدمن</option>
            <option value="sent">معتمد — جاهز لعرض السعر</option>
            <option value="all">الكل</option>
          </select>
          <span class="toolbar-count" id="pricingApprovalCount">0 طلب</span>
          <div class="export-btns">
            <button class="btn-export excel" onclick="exportPricingApproval('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportPricingApproval('pdf')">📄 PDF</button>
          </div>
        </div>
        <div class="panel-body">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>رقم الطلب</th>
                <th>المريض</th>
                <th>التاريخ</th>
                <th>البنود</th>
                <th>التقدير (Highest Batch)</th>
                <th>الحالة</th>
                <th>إجراء</th>
              </tr>
            </thead>
            <tbody id="pricingApprovalTable"></tbody>
          </table>
        </div>
      </div>
    </div>
