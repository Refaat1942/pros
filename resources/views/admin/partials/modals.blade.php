  <!-- اعتماد التسعير — تفاصيل -->
  <div class="catalog-modal-overlay" id="pricingApprovalModal" role="dialog" aria-modal="true">
    <div class="catalog-modal" onclick="event.stopPropagation()">
      <div class="catalog-modal-header">
        <div>
          <h3 id="pricingApprovalModalTitle">🧾 تفاصيل طلب التسعير</h3>
          <div class="modal-code" id="pricingApprovalModalRef"></div>
        </div>
        <button type="button" class="catalog-modal-close" id="closePricingApprovalModal">&times;</button>
      </div>
      <div class="catalog-modal-body">
        <div class="catalog-detail-grid" id="pricingApprovalModalMeta"></div>
        <h4 style="font-size:14px;font-weight:800;margin:16px 0 10px;color:var(--secondary);">📦 البنود والأسعار</h4>
        <table data-paginate="10">
          <thead>
            <tr>
              <th>الصنف</th>
              <th>الكود</th>
              <th>الكمية</th>
              <th>أعلى سعر دفعة</th>
              <th>الإجمالي</th>
            </tr>
          </thead>
          <tbody id="pricingApprovalModalItems"></tbody>
          <tfoot>
            <tr>
              <td colspan="4" style="text-align:left;font-weight:700;">الإجمالي التقديري</td>
              <td id="pricingApprovalModalTotal" style="font-weight:800;color:var(--primary-dark);"></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <div class="catalog-modal-footer">
        <button type="button" class="btn-action" id="btnClosePricingApprovalModal">إغلاق</button>
        <button type="button" class="btn-action approve" id="btnApprovePricingModal" style="display:none;">✅ موافقة الأدمن — إرسال للاستقبال</button>
      </div>
    </div>
  </div>

  <!-- تفاصيل الصنف — Popup -->
  <div class="catalog-modal-overlay" id="catalogDetailModal" role="dialog" aria-modal="true" aria-labelledby="catalogModalTitle">
    <div class="catalog-modal" onclick="event.stopPropagation()">
      <div class="catalog-modal-header">
        <div>
          <h3 id="catalogModalTitle">—</h3>
          <div class="modal-code" id="catalogModalCode">—</div>
        </div>
        <button type="button" class="catalog-modal-close" id="catalogModalClose" aria-label="إغلاق">&times;</button>
      </div>
      <div class="catalog-modal-body" id="catalogModalBody"></div>
      <div class="catalog-modal-footer">
        <button type="button" class="btn-action" id="catalogModalEdit">✏️ تعديل الصنف</button>
        <button type="button" class="btn-action" id="catalogModalCloseBtn">إغلاق</button>
      </div>
    </div>
  </div>

  </div>

  <!-- Credit Note Modal -->
  <div class="catalog-modal-overlay" id="creditNoteModal">
    <div class="catalog-modal">
      <div class="catalog-modal-header">
        <div>
          <h3>📄 إنشاء إشعار دائن</h3>
        </div>
        <button type="button" class="catalog-modal-close" id="closeCreditNoteModal">&times;</button>
      </div>
      <div class="catalog-modal-body">
        <div style="margin-bottom:14px;"><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">حالة مسلّمة (مدني)</label>
          <select id="cnCaseSelect" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;"></select>
        </div>
        <div style="margin-bottom:14px;"><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">نوع الإشعار</label>
          <select id="cnType" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
            <option value="partial">جزئي</option>
            <option value="full">كامل — إلغاء المطالبة بالكامل</option>
          </select>
        </div>
        <div style="margin-bottom:14px;" id="cnAmountGroup"><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">مبلغ الخصم (ج.م)</label>
          <input type="number" id="cnAmount" min="1" value="10000" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
        </div>
        <div style="margin-bottom:14px;"><label style="display:block;font-size:13px;font-weight:700;margin-bottom:6px;">السبب</label>
          <input type="text" id="cnReason" placeholder="مثال: رفض جزئي لبند غير مطابق" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-family:inherit;">
        </div>
        <div id="cnPreview" style="font-size:13px;color:var(--text-muted);margin-top:8px;">—</div>
        <div style="margin-top:16px;display:flex;gap:10px;justify-content:flex-end;">
          <button type="button" class="btn-view" id="btnCancelCreditNote">إلغاء</button>
          <button type="button" class="btn-action success" id="btnConfirmCreditNote">إرسال للموافقة</button>
        </div>
      </div>
    </div>
  </div>