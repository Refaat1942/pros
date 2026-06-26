@php
    $counts = $admin_case_counts ?? ['waiting_return' => 0, 'in_progress' => 0, 'delivered' => 0];
    $buckets = $admin_case_buckets ?? ['waiting_return' => [], 'in_progress' => [], 'delivered' => []];
@endphp
<div class="section-view" id="section-cases">
      <div class="cases-quick-grid" id="casesQuickGrid">
        <button type="button" class="cases-quick-btn waiting active" data-cases-filter="waiting_return">
          <span class="cq-icon">⏳</span>
          <span class="cq-title">بانتظار رجوع العميل</span>
          <span class="cq-desc">تم إصدار عرض السعر وخرج المريض — لم يعد بعد بخطاب الموافقة</span>
          <span class="cq-count" id="casesWaitingCount">{{ $counts['waiting_return'] ?? 0 }}</span>
        </button>
        <button type="button" class="cases-quick-btn progress" data-cases-filter="in_progress">
          <span class="cq-icon">🏭</span>
          <span class="cq-title">تحت التنفيذ</span>
          <span class="cq-desc">رجع بخطاب الموافقة — جاري التصنيع والصرف</span>
          <span class="cq-count" id="casesProgressCount">{{ $counts['in_progress'] ?? 0 }}</span>
        </button>
        <button type="button" class="cases-quick-btn delivered" data-cases-filter="delivered">
          <span class="cq-icon">✅</span>
          <span class="cq-title">تم التسليم</span>
          <span class="cq-desc">حالات مكتملة — تقرير مالي (إجمالي التكلفة)</span>
          <span class="cq-count" id="casesDeliveredCount">{{ $counts['delivered'] ?? 0 }}</span>
        </button>
      </div>
      <div class="panel">
        <div class="panel-header">
          <h3 id="casesPanelTitle">📁 الحالات — بانتظار رجوع العميل</h3>
          <span class="badge" id="casesPanelBadge">{{ ($counts['waiting_return'] ?? 0) }} حالة</span>
        </div>
        <p class="cases-panel-hint" id="casesPanelHint" style="display:none"></p>
        <div class="data-toolbar">
          <input type="text" id="casesSearch" placeholder="🔍 بحث بالمريض أو الهاتف أو رقم عرض السعر...">
          <select id="casesPatientTypeFilter" class="patient-track-filter-select" aria-label="فلتر النوع">
            <option value="">مدني وعسكري</option>
            <option value="civilian">🌐 مدني</option>
            <option value="military">🪖 عسكري</option>
          </select>
          <span class="toolbar-count" id="casesFilterCount">{{ ($counts['waiting_return'] ?? 0) }} حالة</span>
          <div class="export-btns">
            <button class="btn-export excel" onclick="exportCases('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportCases('pdf')">📄 PDF</button>
          </div>
        </div>
        <div class="panel-body">
          <table data-paginate="10">
            <thead id="casesTableHead"></thead>
            <tbody id="casesTableBody" data-server-cases="1"></tbody>
          </table>
        </div>
      </div>
    </div>
<script>
window.__ADMIN_CASE_BUCKETS = @json($buckets);
</script>

@include('partials.contract-letter-modal')

<div class="catalog-modal-overlay case-detail-modal" id="caseDetailModal" role="dialog" aria-modal="true" aria-labelledby="caseDetailModalTitle">
  <div class="catalog-modal case-detail-modal__dialog" onclick="event.stopPropagation()">
    <div class="catalog-modal-header">
      <div class="case-detail-modal__head-text">
        <h3 id="caseDetailModalTitle">📋 تفاصيل الحالة</h3>
        <div class="modal-code" id="caseDetailModalRef"></div>
      </div>
      <button type="button" class="catalog-modal-close" id="closeCaseDetailModal" aria-label="إغلاق">&times;</button>
    </div>
    <div class="catalog-modal-body case-detail-modal__body" id="caseDetailModalBody">
      <p class="case-detail-loading">جاري التحميل...</p>
    </div>
    <div class="catalog-modal-footer">
      <button type="button" class="btn-action primary" id="btnCloseCaseDetailModal">إغلاق</button>
    </div>
  </div>
</div>
