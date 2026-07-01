<div class="panel inventory-wrap">
  <div class="panel-header">
    <h3>💰 التكاليف</h3>
    <div style="display:flex;align-items:center;gap:10px;">
      <input type="search" id="costingSearch" placeholder="🔍 بحث..."
             class="form-control table-search-input">
      <button type="button" class="btn-action primary" id="btnRefreshCosting">↻ تحديث</button>
      <span class="badge" id="costingBadge">0</span>
    </div>
  </div>

  <div class="panel-hint" role="note" aria-label="تعليمات لوحة التكاليف">
    {{-- <p class="panel-hint__text">
      <span class="panel-hint__label">تنبيه</span>
      الحالات الواردة من <strong>المعدلات</strong> — راجع التكلفة <strong>للقراءة فقط</strong>،
      ثم من نافذة المراجعة اضغط <strong>«تأكيد عرض سعر»</strong> لإصدار العرض وتحويل الحالة لمكتب التشغيل.
    </p> --}}
  </div>

  <div class="bom-table-wrap">
    <table data-paginate="10" class="bom-table">
      <thead>
        <tr>
          <th>الحالة</th>
          <th>المريض</th>
          <th>النوع</th>
          <th>إجمالي العرض</th>
          <th class="col-actions">إجراء</th>
        </tr>
      </thead>
      <tbody id="costingTable">
        <tr><td colspan="5" class="empty-cell">جاري التحميل…</td></tr>
      </tbody>
    </table>
  </div>
</div>
