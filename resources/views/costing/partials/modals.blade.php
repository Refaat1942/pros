<div class="modal-overlay" id="costingModal">
  <div class="modal costing-modal-lg" style="max-width:min(1280px,98vw);width:100%;">
    <div class="modal-header">
      <h3 id="costingModalTitle">✅ مراجعة الاعتماد</h3>
      <button type="button" class="modal-close" id="closeCostingModal">&times;</button>
    </div>
    <div class="modal-body">
      <div id="costingPatientBanner" class="costing-patient-banner hidden">
        <div class="costing-patient-banner__main costing-patient-banner__block">
          <h4 id="costingPatientName">—</h4>
          <p id="costingPatientMeta" class="costing-patient-banner__meta">—</p>
        </div>
        <div class="costing-patient-banner__spec costing-patient-banner__block">
          <p class="costing-patient-banner__label">🩺 توصية الطبيب</p>
          <p id="costingDoctorName" class="costing-patient-banner__doctor-name hidden"></p>
          <p id="costingDoctorMessage" class="costing-patient-banner__notes hidden"></p>
          <ul id="costingDoctorRecommendations" class="costing-patient-banner__items hidden"></ul>
        </div>
        <div class="costing-patient-banner__spec costing-patient-banner__block">
          <p class="costing-patient-banner__label">📐 التوصيف الفني</p>
          <ul id="costingSpecItems" class="costing-patient-banner__items"></ul>
          <p id="costingSpecNotes" class="costing-patient-banner__notes hidden"></p>
        </div>
      </div>

      <div id="costingMeta" style="margin-bottom:12px;font-size:13px;color:var(--text-muted);"></div>

      <div class="costing-grid">
        <div class="costing-grid__items">
          <div class="bom-table-wrap" style="max-height:min(58vh,620px);">
            <table class="bom-table">
              <thead>
                <tr>
                  <th>الكود</th>
                  <th>الصنف</th>
                  <th>الكمية</th>
                  <th>المعيار</th>
                  <th>السعر الأساسي</th>
                  <th>الإجمالي</th>
                </tr>
              </thead>
              <tbody id="costingItemsBody"></tbody>
            </table>
          </div>
        </div>

        <div class="costing-grid__panel">
          <div id="costingBreakdown" class="costing-breakdown">
            <h4 class="costing-breakdown__title" id="costingBreakdownTitle">📊 تفصيل التكلفة وسعر البيع</h4>
            {{-- التفاصيل الداخلية (نِسَب/مكوّنات/تكلفة) تظهر للأدمن فقط --}}
            <div id="costingInternalRows" style="display:contents;">
              <div class="costing-overhead-row">
                <span>إجمالي المواد (أعلى سعر شراء)</span>
                <strong id="costingMaterialsTotal">—</strong>
              </div>
              <div id="costingComponentLines" class="costing-breakdown__lines"></div>
              <div class="costing-overhead-row" id="costingComponentsTotalRow" style="display:none;">
                <span>إجمالي المكوّنات</span>
                <strong id="costingComponentsTotal">—</strong>
              </div>
              <div class="costing-overhead-row costing-overhead-row--highlight" id="costingBaseSellingRow" style="display:none;">
                <span id="costingBaseSellingLabel">سعر بيع الطرف الصناعي</span>
                <strong id="costingBaseSelling">—</strong>
              </div>
              <div class="costing-overhead-row" id="costingQuickRow" style="display:none;">
                <span id="costingQuickLabel">الصرف السريع</span>
                <strong id="costingQuickSelling">—</strong>
              </div>
              <div class="costing-overhead-row costing-overhead-row--highlight">
                <span>إجمالي التكلفة</span>
                <strong id="costingTotalCost">—</strong>
              </div>
              <div class="costing-overhead-row costing-overhead-row--muted" id="costingWacRow" style="display:none;">
                <span>التكلفة الداخلية (WAC)</span>
                <strong id="costingWacTotal">—</strong>
              </div>
              <div class="costing-overhead-row" id="costingProfitRow">
                <span id="costingProfitLabel">هامش الربح</span>
                <strong id="costingProfitAmount">—</strong>
              </div>
            </div>
            <div class="costing-overhead-row costing-overhead-row--final">
              <span>سعر البيع (عرض السعر)</span>
              <strong id="costingSellingPrice">—</strong>
            </div>
          </div>
        </div>
      </div>

      <div style="margin-top:16px;display:flex;gap:10px;justify-content:flex-end;">
        <button type="button" class="btn-view" id="btnCancelCosting">إغلاق</button>
        <button type="button" class="btn-action success" id="btnConfirmCosting">✅ تأكيد الاعتماد وإصدار العرض</button>
      </div>
    </div>
  </div>
</div>

<style>
  .costing-grid {
    display: grid;
    grid-template-columns: 1.35fr 1fr;
    gap: 16px;
    align-items: start;
  }
  @media (max-width: 860px) {
    .costing-grid { grid-template-columns: 1fr; }
  }
  .costing-breakdown {
    padding: 14px;
    background: var(--surface-2, #f8fafc);
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 10px;
    display: grid;
    gap: 8px;
  }
  .costing-breakdown__title {
    margin: 0 0 6px;
    font-size: 14px;
    font-weight: 800;
    color: var(--secondary, #334155);
  }
  .costing-breakdown__lines {
    display: grid;
    gap: 6px;
    padding: 8px 0;
    border-top: 1px dashed var(--border, #e2e8f0);
    border-bottom: 1px dashed var(--border, #e2e8f0);
  }
  .costing-overhead-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    font-size: 13px;
  }
  .costing-overhead-row strong {
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
  }
  .costing-overhead-row--muted {
    color: #64748b;
  }
  .costing-overhead-row--highlight strong {
    color: #059669;
    font-size: 15px;
  }
  .costing-overhead-row--final {
    margin-top: 8px;
    padding-top: 10px;
    border-top: 1px solid var(--border, #e2e8f0);
    font-weight: 800;
  }
  .costing-overhead-row--final strong {
    color: var(--primary-dark, #5b21b6);
    font-size: 16px;
  }
  .costing-criteria-cell {
    max-width: 280px;
    font-size: 12px;
    line-height: 1.6;
    color: var(--text-muted, #64748b);
    white-space: normal;
  }
  .costing-patient-banner {
    display: grid;
    grid-template-columns: 1.1fr 1fr 1fr;
    gap: 12px;
    margin-bottom: 16px;
    padding: 14px;
    border-radius: 12px;
    background: #fff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(15,23,42,.06);
  }
  @media (max-width: 980px) {
    .costing-patient-banner { grid-template-columns: 1fr; }
  }
  .costing-patient-banner__block {
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #f8fafc;
    min-height: 120px;
  }
  .costing-grid .bom-table-wrap {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
  }
  .costing-grid .bom-table {
    width: 100%;
    border-collapse: collapse;
  }
  .costing-grid .bom-table th,
  .costing-grid .bom-table td {
    border: 1px solid #e2e8f0;
    padding: 8px 10px;
    font-size: 13px;
  }
  .costing-grid .bom-table thead th {
    background: #f1f5f9;
    font-weight: 800;
  }
  .costing-patient-banner__main h4 {
    margin: 0 0 6px;
    font-size: 20px;
    font-weight: 800;
    color: #312e81;
  }
  .costing-patient-banner__meta {
    margin: 0;
    font-size: 13px;
    color: #475569;
  }
  .costing-patient-banner__label {
    margin: 0 0 8px;
    font-size: 13px;
    font-weight: 800;
    color: #334155;
  }
  .costing-patient-banner__items {
    margin: 0;
    padding: 0;
    list-style: none;
    display: grid;
    gap: 4px;
    max-height: 120px;
    overflow-y: auto;
    font-size: 12px;
  }
  .costing-patient-banner__items li {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    padding: 4px 0;
    border-bottom: 1px dashed #e2e8f0;
  }
  .costing-patient-banner__doctor-name {
    margin: 0 0 6px;
    font-size: 12px;
    font-weight: 700;
    color: #0f766e;
  }
  .costing-patient-banner__notes {
    margin: 8px 0 0;
    font-size: 12px;
    color: #64748b;
    white-space: pre-wrap;
  }
</style>

<div class="toast" id="toast" aria-live="polite"></div>
