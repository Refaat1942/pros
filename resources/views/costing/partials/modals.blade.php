<div class="modal-overlay" id="costingModal">
  <div class="modal costing-modal-lg" style="max-width:min(1180px,98vw);width:100%;">
    <div class="modal-header">
      <h3 id="costingModalTitle">💰 مراجعة التكلفة</h3>
      <button type="button" class="modal-close" id="closeCostingModal">&times;</button>
    </div>
    <div class="modal-body">
      <div id="costingMeta" style="margin-bottom:12px;font-size:13px;color:var(--text-muted);"></div>

      <div class="costing-grid">
        <div class="costing-grid__items">
          <div class="bom-table-wrap" style="max-height:min(52vh,540px);">
            <table class="bom-table">
              <thead>
                <tr>
                  <th>الكود</th>
                  <th>الصنف</th>
                  <th>الكمية</th>
                  <th>المعيار</th>
                  <th>الإجمالي</th>
                </tr>
              </thead>
              <tbody id="costingItemsBody"></tbody>
            </table>
          </div>
        </div>

        <div class="costing-grid__panel">
          <div class="costing-mode-picker">
            <label for="costingModeSelect" class="costing-mode-picker__label">🧮 نمط التكاليف</label>
            <select id="costingModeSelect" class="form-control"></select>
            <p class="costing-mode-picker__hint" id="costingModeHint"></p>
          </div>

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
        <button type="button" class="btn-action success" id="btnConfirmCosting">✅ تأكيد  </button>
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
  .costing-mode-picker {
    padding: 12px 14px;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    border-radius: 10px;
    margin-bottom: 12px;
  }
  .costing-mode-picker__label {
    display: block;
    font-weight: 800;
    font-size: 14px;
    color: #3730a3;
    margin-bottom: 6px;
  }
  .costing-mode-picker__hint {
    margin: 6px 0 0;
    font-size: 12px;
    color: #4f46e5;
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
</style>

<div class="toast" id="toast" aria-live="polite"></div>
