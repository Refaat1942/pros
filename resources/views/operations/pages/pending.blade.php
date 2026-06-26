<div class="section-view" id="section-pending">
  <div id="analytics-pending">@include('partials.dashboard-analytics-empty', ['stats' => [
    ['icon' => '⏳', 'label' => 'بانتظار الموافقة', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
    ['icon' => '🧾', 'label' => 'عروض صادرة', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
    ['icon' => '🌐', 'label' => 'مدني', 'value' => '0', 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
    ['icon' => '🪖', 'label' => 'عسكري', 'value' => '0', 'color' => '#4338ca', 'bg' => 'rgba(67,56,202,0.1)'],
  ]])</div>

  <div class="panel inventory-wrap">
    <div class="panel-header">
      <h3>✅ مكتب التشغيل — موافقات وعروض الأسعار</h3>
      <div style="display:flex;align-items:center;gap:10px;">
        <input type="search" id="pendingSearch" placeholder="🔍 بحث رقم الحالة / العرض / مريض..."
               class="form-control" style="max-width:220px;">
        <button type="button" class="btn-action primary" id="btnRefreshPending">↻ تحديث</button>
        <span class="badge" id="pendingBadge">0</span>
      </div>
    </div>
    <p style="padding:0 24px 12px;margin:0;color:var(--text-muted);font-size:13px;line-height:1.7;">
      طابور الحالات التي وصلت بعروض أسعار. للمدني: <strong>إصدار عرض السعر</strong> للاستقبال ثم طباعته للعميل.
      بعد الإصدار يختفي <strong>الإرجاع للتعديل</strong> — تبقى الحالة بانتظار رجوع العميل بخطاب الموافقة.
      للعسكري: <strong>الموافقة واعتماد الصرف</strong> (حجز فوري للمواد) أو <strong>الإرجاع للتعديل</strong>.
    </p>
    <div class="bom-table-wrap">
      <table data-paginate="10" class="bom-table">
        <thead>
          <tr>
            <th>الحالة / العرض</th>
            <th>المريض</th>
            <th>النوع</th>
            <th>إجمالي العرض</th>
            <th class="col-actions">إجراء</th>
          </tr>
        </thead>
        <tbody id="pendingTable">
          <tr><td colspan="5" class="empty-cell">جاري تحميل الحالات…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
