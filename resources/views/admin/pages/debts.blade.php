<div class="section-view" id="section-debts">
      <div id="analytics-debts">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '📋', 'label' => 'جهات', 'value' => '0', 'bg' => 'rgba(124,58,237,0.1)'],
        ['icon' => '💳', 'label' => 'المستحق', 'value' => '0', 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.1)'],
        ['icon' => '✅', 'label' => 'المحصّل', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '⏳', 'label' => 'المتبقي', 'value' => '0', 'color' => '#dc2626', 'bg' => 'rgba(220,38,38,0.1)'],
      ]])</div>
      <div class="panel">
        <div class="panel-header">
          <h3>💰 مديونيات شركات التعاقد</h3>
          <span class="badge" id="debtsSectionBadge">0 جهة</span>
        </div>
        <div class="data-toolbar">
          <input type="text" id="debtSearch" placeholder="🔍 بحث بجهة التعاقد...">
          <select id="debtStatusFilter">
            <option value="paid">مسدد</option>
          </select>
          <span class="toolbar-count" id="debtCount">0 جهة</span>
          <div class="export-btns">
            <button class="btn-export excel" onclick="exportDebts('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportDebts('pdf')">📄 PDF</button>
          </div>
        </div>
        <div class="panel-body">
          <table>
            <thead>
              <tr>
                <th>جهة التعاقد</th>
                <th>المستحق</th>
                <th>الحالة</th>
              </tr>
            </thead>
            <tbody id="debtsTableFull"></tbody>
          </table>
        </div>
      </div>
      <div class="panel" style="margin-top:20px;">
        <div class="panel-header">
          <h3>📄 إشعارات الدائن (Credit Notes) — مسار مدني بعد التسليم</h3>
          <span class="badge" id="creditNotesBadge">0</span>
        </div>
        <p style="padding:0 24px 12px;margin:0;color:var(--text-muted);font-size:13px;">
          كامل أو جزئي — يخصم من مديونية جهة التعاقد. يتطلب <strong>موافقة الإدارة</strong>. المسار العسكري: تكلفة سيادية (لا Credit Note).
        </p>
        <div class="data-toolbar">
          <button type="button" class="btn-action" id="btnNewCreditNote">➕ إنشاء إشعار دائن</button>
        </div>
        <div class="panel-body">
          <table>
            <thead>
              <tr>
                <th>رقم CN</th>
                <th>الحالة / المريض</th>
                <th>جهة التعاقد</th>
                <th>النوع</th>
                <th>المبلغ</th>
                <th>الحالة</th>
                <th>إجراء</th>
              </tr>
            </thead>
            <tbody id="creditNotesTable"></tbody>
          </table>
        </div>
      </div>
    </div>
