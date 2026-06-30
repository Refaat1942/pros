<div class="section-view" id="section-quotes-awaiting">
  <div id="analytics-quotes-awaiting">@include('partials.dashboard-analytics-empty', ['stats' => [
    ['icon' => '💰', 'label' => 'بانتظار الموافقة', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
    ['icon' => '📤', 'label' => 'صُدرت للعميل', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
    ['icon' => '🏭', 'label' => 'بالمخزن', 'value' => '0', 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
    ['icon' => '📋', 'label' => 'إجمالي العروض', 'value' => '0', 'color' => '#4338ca', 'bg' => 'rgba(67,56,202,0.1)'],
  ]])</div>

  <div class="panel inventory-wrap">
    <div class="panel-header">
      <h3>💰 عروض الأسعار — بانتظار موافقة الجهة</h3>
      <div style="display:flex;align-items:center;gap:10px;">
        <input type="search" id="quotesAwaitingSearch" placeholder="🔍 بحث سريال عرض السعر / المريض / الجهة..."
               class="form-control table-search-input">
        <button type="button" class="btn-action primary" id="btnRefreshQuotesAwaiting">↻ تحديث</button>
        <span class="badge" id="quotesAwaitingBadge">0</span>
      </div>
    </div>
    <p style="padding:0 24px 12px;margin:0;color:var(--text-muted);font-size:13px;line-height:1.7;">
      العروض التي <strong>أُرسلت للاستقبال/العميل</strong> ولم تُرجَع بعد بموافقة الجهة (خطاب التأمين).
      بعد اعتماد الموافقة تختفي من هذا الطابور تلقائياً.
    </p>
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
