<div class="section-view" id="section-overview">
    <div class="overview-cases-strip" id="overviewCasesStrip">
      <button type="button" class="overview-case-link" data-goto-cases="waiting_return">
        <strong>⏳ بانتظار رجوع العميل</strong>
        <span id="overviewWaitingCount" style="color:#d97706" data-server-rendered="1">{{ $case_strip['waiting_return'] ?? 0 }}</span>
      </button>
      <button type="button" class="overview-case-link" data-goto-cases="in_progress">
        <strong>🏭 تحت التنفيذ</strong>
        <span id="overviewProgressCount" style="color:#0e7490" data-server-rendered="1">{{ $case_strip['in_progress'] ?? 0 }}</span>
      </button>
      <button type="button" class="overview-case-link" data-goto-cases="delivered">
        <strong>✅ تم التسليم</strong>
        <span id="overviewDeliveredCount" style="color:#059669" data-server-rendered="1">{{ $case_strip['delivered'] ?? 0 }}</span>
      </button>
    </div>

    @include('partials.workshop-overview-panel')

    @include('partials.operations-overview-panel')

    @include('partials.visit-leaderboard-panel')

    <div class="panel overview-employees-panel" id="employees">
        <div class="panel-header">
          <h3>👥 إدارة الموظفين</h3>
          <span class="badge">{{ ($employees_preview ?? collect())->count() }} موظف</span>
        </div>
        <div class="panel-body">
          <table data-paginate="10">
            <thead>
              <tr>
                <th>الاسم</th>
                <th>اسم المستخدم</th>
                <th>الدور</th>
                <th>الحالة</th>
                <th>آخر دخول</th>
                <th>إجراء</th>
              </tr>
            </thead>
            <tbody id="employeesTable" data-server-rendered="1">
              @isset($employees_preview)
                @include('partials.employees-table-rows', [
                    'employees' => $employees_preview,
                    'show_bulk' => false,
                ])
              @endisset
            </tbody>
          </table>
        </div>
      </div>

    <div class="panel audit-panel">
      <div class="panel-header">
        <h3>🔒 آخر حركات — سجل الرقابة</h3>
        <span class="badge">آخر ٥</span>
      </div>
      <div class="panel-body" id="auditPreview" data-server-rendered="1">
        @include('partials.audit-log-preview', ['audit_preview' => $audit_preview ?? collect()])
      </div>
    </div>
    </div>
