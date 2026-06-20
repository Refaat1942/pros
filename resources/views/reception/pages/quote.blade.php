<div class="tab-content" id="tab-quote">
      <div id="analytics-quote" class="ck-analytics" data-static-ui="1">
        <div class="ck-stats">
          <div class="ck-stat">
            <div class="ck-stat-icon" style="background:rgba(5,150,105,0.1)">🧾</div>
            <div>
              <div class="ck-stat-label">عروض</div>
              <div class="ck-stat-value">…</div>
            </div>
          </div>
          <div class="ck-stat">
            <div class="ck-stat-icon" style="background:rgba(5,150,105,0.1)">✅</div>
            <div>
              <div class="ck-stat-label">معتمد</div>
              <div class="ck-stat-value" style="color:#059669">…</div>
            </div>
          </div>
          <div class="ck-stat">
            <div class="ck-stat-icon" style="background:rgba(217,119,6,0.1)">⏳</div>
            <div>
              <div class="ck-stat-label">بانتظار</div>
              <div class="ck-stat-value" style="color:#d97706">…</div>
            </div>
          </div>
        </div>
      </div>
      <div class="panel">
        <div class="panel-header">
          <h3>🧾 عروض الأسعار</h3>
          <span class="badge" id="quoteListCount">0</span>
        </div>
        <div class="data-toolbar">
          <input type="text" id="quoteSearch"
                 placeholder="امسح باركود/QR عرض السعر أو ابحث بالمريض..."
                 autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="100">
          <span class="toolbar-count" id="quoteFilterCount">0 عروض</span>
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
