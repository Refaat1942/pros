<div class="section-view" id="section-pending">
  <div class="panel" id="workshopAssignmentPanel">
    <div class="panel-header"><h3>🏭 تخصيص الورشة عند الاعتماد</h3></div>
    <div class="panel-body" style="display:flex;gap:12px;flex-wrap:wrap;padding:16px;">
      <p class="text-muted" style="width:100%;margin:0 0 4px;">اختر القسم والفني قبل الضغط على «إصدار أمر الشغل» — يُطبَّق على الحالة التالية التي تعتمدها.</p>
      <div class="form-group" style="min-width:240px;">
        <label>قسم الورشة</label>
        <select id="approveWorkshopSection" class="form-control"><option value="">— بدون —</option></select>
      </div>
      <div class="form-group" style="min-width:240px;">
        <label>الفني</label>
        <select id="approveWorkshopTechnician" class="form-control"><option value="">— بدون —</option></select>
      </div>
    </div>
  </div>

  <div class="panel inventory-wrap" style="margin-top:16px;">
    <div class="panel-header">
      <h3>✅ مكتب التشغيل — إصدار أمر الشغل واعتماد الصرف</h3>
      <div style="display:flex;align-items:center;gap:10px;">
        <input type="search" id="pendingSearch" placeholder="🔍 بحث رقم الحالة / العرض / مريض..."
               class="form-control table-search-input">
        <button type="button" class="btn-action primary" id="btnRefreshPending">↻ تحديث</button>
      </div>
    </div>
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

@include('partials.contract-letter-modal')
