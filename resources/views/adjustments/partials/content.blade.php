<aside class="sidebar">
    <div class="sidebar-brand">
      <div class="icon">📏</div>
      <h2>لوحة المعدلات</h2>
      <span>تجارب التركيب والمقاسات</span>
    </div>
    <ul class="nav-menu">
      <li><a href="#" class="active" data-section="adjustments"><span class="nav-icon">📏</span> جدول المعدلات</a></li>
    </ul>
    <div class="sidebar-footer">
      <a href="{{ route('home') }}" class="btn-back">← العودة للصفحة الرئيسية</a>
    </div>
  </aside>

  <main class="main">
    <div class="page-header">
      <div>
        <h1 id="pageTitle">المعدلات — تجارب التركيب والمقاسات</h1>
        <p>أحمد سمير محمود — فني المقاسات</p>
      </div>
      <div class="user-chip">
        <div class="avatar">أ</div>
        <span>أحمد سمير</span>
      </div>
    </div>

    <div class="section-view active" id="section-adjustments">
      <div id="analytics-adjustments"></div>
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
          <table class="bom-table">
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