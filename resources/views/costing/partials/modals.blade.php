<div class="modal-overlay" id="costingModal">
  <div class="modal" style="max-width:820px;">
    <div class="modal-header">
      <h3 id="costingModalTitle">💰 مراجعة التكلفة</h3>
      <button type="button" class="modal-close" id="closeCostingModal">&times;</button>
    </div>
    <div class="modal-body">
      <div id="costingMeta" style="margin-bottom:12px;font-size:13px;color:var(--text-muted);"></div>
      <div class="bom-table-wrap">
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

      <div id="costingOverheadSummary" class="costing-overhead-summary" style="display:none;">
        <h4 class="costing-overhead-summary__title">📊 تفصيل التكلفة والعرض</h4>
        <div id="costingOverheadLines" class="costing-overhead-summary__lines"></div>
        <div class="costing-overhead-summary__totals">
          <div class="costing-overhead-row costing-overhead-row--muted" id="costingWacRow">
            <span>التكلفة الداخلية</span>
            <strong id="costingWacTotal">—</strong>
          </div>
          <div class="costing-overhead-row costing-overhead-row--highlight">
            <span>إجمالي السعر قبل الخصم</span>
            <strong id="costingGrossTotal">—</strong>
          </div>
          <div class="costing-overhead-row" id="costingDiscountRow" style="display:none;">
            <span id="costingDiscountLabel">خصم جهة التعاقد</span>
            <strong id="costingDiscountAmount">—</strong>
          </div>
          <div class="costing-overhead-row costing-overhead-row--final">
            <span>الاجمالي</span>
            <strong id="costingNetTotal">—</strong>
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
  .costing-overhead-summary {
    margin-top: 16px;
    padding: 14px;
    background: var(--surface-2, #f8fafc);
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 10px;
  }
  .costing-overhead-summary__title {
    margin: 0 0 12px;
    font-size: 14px;
    font-weight: 800;
    color: var(--secondary, #334155);
  }
  .costing-overhead-summary__lines {
    display: grid;
    gap: 8px;
    margin-bottom: 12px;
    padding-bottom: 12px;
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
