<aside class="sidebar">
    <div class="sidebar-brand">
      <div class="icon">🧩</div>
      <h2>لوحة المعدلات</h2>
      <span>مراجعة وإضافة بنود قبل التكاليف</span>
    </div>
    <ul class="nav-menu">
      <li><a href="#" class="active" data-section="adjustments"><span class="nav-icon">🧩</span> طابور المعدلات</a></li>
    </ul>

  </aside>

  <main class="main">
    <div class="page-header">
      <div>
        <h1 id="pageTitle">المعدلات — مراجعة بنود الفني وإضافة المكوّنات</h1>
        <p></p>
      </div>
      <div class="user-chip">
        <div class="avatar"></div>
        <span></span>
      </div>
    </div>

    <div class="section-view active" id="section-adjustments">
      <div id="analytics-adjustments">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '🧩', 'label' => 'حالات بالمعدلات', 'value' => '0', 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.12)'],
        ['icon' => '🪖', 'label' => 'عسكري', 'value' => '0', 'color' => '#4f46e5', 'bg' => 'rgba(79,70,229,0.1)'],
        ['icon' => '🌐', 'label' => 'مدني', 'value' => '0', 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
        ['icon' => '📋', 'label' => 'متوسط البنود', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
      ]])</div>
      <div class="panel inventory-wrap">
        <div class="panel-header">
          <h3>🧩 طابور المعدلات</h3>
          <span class="badge" id="adjBadge">0</span>
        </div>
        <p style="padding:0 24px 12px;margin:0;color:var(--text-muted);font-size:13px;">
          بنود الفني تُعرض <strong>للقراءة فقط</strong> ولا يمكن تعديلها أو حذفها. يمكن لمستشار المعدلات
          <strong>إضافة بنود/مكوّنات فنية</strong> إلى نفس القائمة، ثم إغلاق المعدلات لدفع القائمة المجمّعة لمحرك التكاليف.
        </p>
        <div style="padding:0 24px 12px;">
          <input type="search" class="form-control" id="adjSearch" placeholder="🔎 بحث بالحالة / المريض / أمر التشغيل">
        </div>
        <div class="bom-summary" id="adjSummary"></div>
        <div class="bom-table-wrap">
          <table data-paginate="10" class="bom-table">
            <thead>
              <tr>
                <th>الحالة</th>
                <th>المريض</th>
                <th>المسار</th>
                <th>عدد الأصناف</th>
                <th class="col-actions">إجراء</th>
              </tr>
            </thead>
            <tbody id="adjustmentsTable"></tbody>
          </table>
        </div>
        <div style="padding:12px 24px;">
          <button type="button" class="btn-view" id="btnRefreshAdj">↻ تحديث</button>
        </div>
      </div>
    </div>
  </main>

  <div class="toast" id="toast"></div>
