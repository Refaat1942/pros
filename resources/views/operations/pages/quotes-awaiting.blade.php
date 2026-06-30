<div class="section-view" id="section-quotes-awaiting">
  <div class="panel inventory-wrap">
    <div class="panel-header">
      <h3>💰 عروض بانتظار الموافقة</h3>
      <div style="display:flex;align-items:center;gap:10px;">
        <input type="search" id="quotesAwaitingSearch" placeholder="🔍 بحث سريال عرض السعر / المريض / الجهة..."
               class="form-control table-search-input">
        <button type="button" class="btn-action primary" id="btnRefreshQuotesAwaiting">↻ تحديث</button>
      </div>
    </div>
    <div class="bom-table-wrap">
      <table data-paginate="10" class="bom-table">
        <thead>
          <tr>
            <th>سريال عرض السعر</th>
            <th>المريض / الجهة</th>
            <th>مرحلة الحالة</th>
            <th>إجمالي العرض</th>
            <th class="col-actions">إجراء</th>
          </tr>
        </thead>
        <tbody id="quotesAwaitingTable">
          <tr><td colspan="5" class="empty-cell">جاري تحميل العروض…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
