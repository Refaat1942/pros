<div class="section-view" id="section-overview">
    <div id="analytics-overview">@include('partials.dashboard-analytics-empty', ['stats' => [
      ['icon' => '💵', 'label' => 'إيرادات', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
      ['icon' => '👤', 'label' => 'مرضى', 'value' => '0', 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
      ['icon' => '📦', 'label' => 'صحة المخزون', 'value' => '0%', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
      ['icon' => '💰', 'label' => 'مديونيات', 'value' => '0', 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.1)'],
    ]])</div>

    <div class="overview-cases-strip" id="overviewCasesStrip">
      <button type="button" class="overview-case-link" data-goto-cases="waiting_return">
        <strong>⏳ بانتظار رجوع العميل</strong>
        <span id="overviewWaitingCount" style="color:#d97706">0</span>
      </button>
      <button type="button" class="overview-case-link" data-goto-cases="in_progress">
        <strong>🏭 تحت التنفيذ</strong>
        <span id="overviewProgressCount" style="color:#0e7490">0</span>
      </button>
      <button type="button" class="overview-case-link" data-goto-cases="delivered">
        <strong>✅ تم التسليم</strong>
        <span id="overviewDeliveredCount" style="color:#059669">0</span>
      </button>
    </div>

    <div class="panels-grid">
      <div class="panel" id="employees">
        <div class="panel-header">
          <h3>👥 إدارة الموظفين والصلاحيات</h3>
          <span class="badge" id="employeesOverviewBadge">0 موظف</span>
        </div>
        <div class="panel-body">
          <table>
            <thead>
              <tr>
                <th>الاسم</th>
                <th>الدور</th>
                <th>الحالة</th>
                <th>آخر دخول</th>
                <th>إجراء</th>
              </tr>
            </thead>
            <tbody id="employeesTable">
            </tbody>
          </table>
        </div>
      </div>

      <div class="panel" id="debts">
        <div class="panel-header">
          <h3>💰 مديونيات شركات التعاقد</h3>
          <span class="badge" id="debtsOverviewBadge">0 جهة</span>
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
            <tbody id="debtsTable">
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="panel audit-panel">
      <div class="panel-header">
        <h3>🔒 آخر حركات — سجل الرقابة</h3>
        <span class="badge">آخر ٥</span>
      </div>
      <div class="panel-body" id="auditPreview"></div>
    </div>
    </div>
