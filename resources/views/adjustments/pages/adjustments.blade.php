<div class="section-view" id="section-adjustments">
      <div id="analytics-adjustments">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '📏', 'label' => 'حالات بالمعدلات', 'value' => '0', 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.12)'],
        ['icon' => '🪖', 'label' => 'عسكري', 'value' => '0', 'color' => '#4338ca', 'bg' => 'rgba(67,56,202,0.1)'],
        ['icon' => '🌐', 'label' => 'مدني', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '📦', 'label' => 'متوسط البنود', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
      ]])</div>
      <div class="panel inventory-wrap">
        <div class="panel-header">
          <h3>📏 المعدلات — مراجعة وإضافة بنود قبل التكاليف</h3>
          <div style="display:flex;align-items:center;gap:10px;">
            <input type="search" id="adjSearch" placeholder="🔍 بحث رقم الحالة / الطلب / مريض..."
                   class="form-control" style="max-width:220px;">
            <button type="button" class="btn-action primary" id="btnRefreshAdj">↻ تحديث</button>
            <span class="badge" id="adjBadge">0</span>
          </div>
        </div>
        <div class="panel-hint panel-hint--adjustments" role="note" aria-label="تعليمات لوحة المعدلات">
          <p class="panel-hint__text">
            <span class="panel-hint__label">تنبيه</span>
            تصل الحالة هنا فور <strong>إرسال التوصيف الفني</strong>. بنود الفني <strong>للقراءة فقط</strong> —
            يمكن المعدلات <strong>إضافة</strong> مكوّنات جديدة دون تعديل أو حذف الأصلية، ثم
            <strong>إرسالها للتكاليف</strong> (تُقفل قائمة المواد وتُدفع لمحرّك التسعير).
          </p>
        </div>
        <div class="bom-table-wrap">
          <table data-paginate="10" class="bom-table">
            <thead>
              <tr>
                <th>الحالة / الطلب</th>
                <th>المريض</th>
                <th>النوع</th>
                <th>عدد البنود</th>
                <th class="col-actions">إجراء</th>
              </tr>
            </thead>
            <tbody id="adjustmentsTable">
              <tr><td colspan="5" class="empty-cell">جاري تحميل الحالات…</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
