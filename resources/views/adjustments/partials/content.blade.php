<aside class="sidebar">
    <div class="sidebar-brand">
      <div class="icon">📏</div>
      <h2>لوحة المعدلات</h2>
      <span>تجارب التركيب والمقاسات</span>
    </div>
    <ul class="nav-menu">
      <li><a href="#" class="active" data-section="adjustments"><span class="nav-icon">📏</span> جدول المعدلات</a></li>
    </ul>

  </aside>

  <main class="main">
    <div class="page-header">
      <div>
        <h1 id="pageTitle">المعدلات — تجارب التركيب والمقاسات</h1>
        <p></p>
      </div>
      <div class="user-chip">
        <div class="avatar"></div>
        <span></span>
      </div>
    </div>

    <div class="section-view active" id="section-adjustments">
      <div id="analytics-adjustments">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '📏', 'label' => 'حالات للمعد', 'value' => '0', 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.12)'],
        ['icon' => '1️⃣', 'label' => 'تجربة أولى', 'value' => '0', 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
        ['icon' => '2️⃣', 'label' => 'تجربة ثانية', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '⏳', 'label' => 'بانتظار تجربة', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
      ]])</div>
      <div class="panel inventory-wrap">
        <div class="panel-header">
          <h3>📏 جدول المعدلات والتجارب</h3>
          <span class="badge" id="adjBadge">0</span>
        </div>
        <p style="padding:0 24px 12px;margin:0;color:var(--text-muted);font-size:13px;">
          تسجيل مواعيد <strong>التجربة الأولى والثانية</strong>، ملاحظات المقاسات، ومتابعة إذن الشغل بعد خروج الحالة من الورشة.
        </p>
        <div class="bom-summary" id="adjSummary"></div>
        <div class="bom-table-wrap">
          <table data-paginate="10" class="bom-table">
            <thead>
              <tr>
                <th>أمر التشغيل</th>
                <th>المريض</th>
                <th>المرحلة</th>
                <th>تجربة 1</th>
                <th>تجربة 2</th>
                <th>ملاحظات</th>
                <th class="col-actions">إجراء</th>
              </tr>
            </thead>
            <tbody id="adjustmentsTable"></tbody>
          </table>
        </div>
      </div>
    </div>
  </main>

  <div class="modal-overlay" id="fittingModal">
    <div class="modal">
      <div class="modal-header">
        <h3 id="fittingModalTitle">📏 تسجيل تجربة تركيب</h3>
        <button type="button" class="modal-close" id="closeFittingModal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>تاريخ التجربة الأولى</label>
          <input type="text" class="form-control" id="fittingTrial1" placeholder="مثال: 08/06/2026">
        </div>
        <div class="form-group">
          <label>تاريخ التجربة الثانية</label>
          <input type="text" class="form-control" id="fittingTrial2" placeholder="مثال: 12/06/2026">
        </div>
        <div class="form-group">
          <label>ملاحظات المقاسات والتعديلات</label>
          <textarea class="form-control" id="fittingNotes" rows="4" placeholder="مثال: تعديل بطانة الساق — ضغط خفيف عند الركبة"></textarea>
        </div>
        <div style="margin-top:16px;display:flex;gap:10px;justify-content:flex-end;">
          <button type="button" class="btn-view" id="btnCancelFitting">إلغاء</button>
          <button type="button" class="btn-action success" id="btnSaveFitting">حفظ</button>
        </div>
      </div>
    </div>
  </div>

  <div class="toast" id="toast"></div>