<div class="panel inventory-wrap">
  <div class="panel-header">
    <h3>💰 طابور التكاليف</h3>
    <div style="display:flex;align-items:center;gap:10px;">
      <input type="search" id="costingSearch" placeholder="🔍 بحث..."
             class="form-control" style="max-width:200px;">
      <button type="button" class="btn-action primary" id="btnRefreshCosting">↻ تحديث</button>
      <span class="badge" id="costingBadge">0</span>
    </div>
  </div>
  <p style="padding:0 24px 12px;margin:0;color:var(--text-muted);font-size:13px;">
    الحالات الواردة من المعدلات — راجع التكلفة <strong>للقراءة فقط</strong> ثم أكّد لإصدار عرض السعر.
  </p>
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
