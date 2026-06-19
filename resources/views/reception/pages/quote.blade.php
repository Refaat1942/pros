<div class="tab-content" id="tab-quote">
      <div id="analytics-quote">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '🧾', 'label' => 'عروض', 'value' => '0', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '💰', 'label' => 'إجمالي', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '✅', 'label' => 'معتمد', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '⏳', 'label' => 'بانتظار', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
      ]])</div>
      <div class="panel">
        <div class="panel-header">
          <h3>🧾 عروض الأسعار</h3>
          <span class="badge" id="quoteListCount">0</span>
        </div>
        <div class="data-toolbar">
          <input type="text" id="quoteSearch" placeholder="🔍 بحث برقم العرض أو اسم المريض...">
          <span class="toolbar-count" id="quoteFilterCount">0 عروض</span>
          <button type="button" class="btn btn-secondary" id="btnSimulateReturn" style="padding:8px 16px;font-size:12px;white-space:nowrap;">📱 محاكاة عودة المريض (QR)</button>
        </div>
        <div class="panel-body">
          <table data-paginate="10">
            <thead>
              <tr>
                <th>رقم العرض</th>
                <th>المريض</th>
                <th>جهة التعاقد</th>
                <th>التاريخ</th>
                <th>الإجمالي</th>
                <th>الحالة</th>
                <th>إجراء</th>
              </tr>
            </thead>
            <tbody id="quotesTable"></tbody>
          </table>
        </div>
      </div>
    </div>
