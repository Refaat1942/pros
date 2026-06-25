<div class="modal-overlay" id="costingModal">
  <div class="modal" style="max-width:720px;">
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
              <th>سعر الوحدة</th>
              <th id="costingWacHeader" style="display:none;">WAC</th>
              <th>الإجمالي</th>
            </tr>
          </thead>
          <tbody id="costingItemsBody"></tbody>
          <tfoot>
            <tr>
              <td colspan="3" style="font-weight:700;">إجمالي العرض</td>
              <td id="costingTotalDisplay" colspan="3" style="font-weight:800;color:#059669;"></td>
            </tr>
            <tr id="costingInternalRow" style="display:none;">
              <td colspan="3" style="font-weight:700;color:#64748b;">التكلفة الداخلية (WAC)</td>
              <td id="costingInternalDisplay" colspan="3" style="color:#64748b;"></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <div style="margin-top:16px;display:flex;gap:10px;justify-content:flex-end;">
        <button type="button" class="btn-view" id="btnCancelCosting">إغلاق</button>
        <button type="button" class="btn-action success" id="btnConfirmCosting">✅ تأكيد عرض سعر</button>
      </div>
    </div>
  </div>
</div>

@include('partials.tech-notes-modal')

<div class="toast" id="toast" aria-live="polite"></div>
