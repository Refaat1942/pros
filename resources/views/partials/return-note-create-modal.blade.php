<div class="modal-overlay" id="returnCreateModal">
  <div class="modal modal-return">
    <div class="modal-header return-modal-header">
      <div class="return-modal-title-wrap">
        <h3>↩️ طلب ارتجاع مواد للمخزن</h3>
        <p class="modal-subtitle">إرسال مواد من الورشة إلى المخزن — قائمة تحت التشغيل فقط وبنود مُصرفة فعلاً</p>
      </div>
      <button type="button" class="modal-close" id="closeReturnCreateModal" aria-label="إغلاق">&times;</button>
    </div>
    <div class="modal-body return-modal-body">
      <div class="form-group return-bom-field">
        <label for="returnBomSelect">قائمة المواد <span class="label-hint">(تحت التشغيل فقط)</span></label>
        <select class="form-control return-bom-select" id="returnBomSelect" data-v-rules="required,select"></select>
        <div id="returnBomMeta" class="return-bom-meta" hidden></div>
      </div>

      <div class="return-lines-section">
        <div class="return-lines-header">
          <span class="return-lines-title">البنود القابلة للارتجاع</span>
          <div class="return-lines-actions">
            <button type="button" class="return-lines-action" id="returnSelectAll">تحديد الكل</button>
            <span class="return-lines-divider">|</span>
            <button type="button" class="return-lines-action" id="returnDeselectAll">إلغاء التحديد</button>
          </div>
        </div>
        <div id="returnLinesPicker" class="return-lines-list">
          <p class="return-lines-empty">اختر قائمة مواد لعرض البنود</p>
        </div>
      </div>

      <div class="form-group return-reason-field">
        <label for="returnReason">سبب الارتجاع <span class="required">*</span></label>
        <textarea class="form-control return-reason-input" id="returnReason" rows="2"
                  placeholder="مثال: فائض عن الحاجة في الورشة، أو تغيير في المواصفات"
                  data-v-rules="required,min:3,max:500" maxlength="500"></textarea>
      </div>
    </div>
    <div class="modal-footer return-modal-footer">
      <button type="button" class="btn-view" id="btnCancelReturnCreate">إلغاء</button>
      <button type="button" class="btn-action success" id="btnConfirmReturnCreate">📤 إرسال للمخزن</button>
    </div>
  </div>
</div>
