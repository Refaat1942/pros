  <!-- Barcode Issue Modal -->
  <div class="modal-overlay" id="barcodeModal">
    <div class="modal">
      <div class="modal-header">
        <h3>صرف بالباركود — مطابقة أمر التشغيل</h3>
        <button type="button" class="modal-close" id="closeBarcodeModal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="barcode-required" id="barcodeRequired"></div>
        <div class="barcode-scan-row">
          <input type="text" id="barcodeInput" placeholder="امسح أو اكتب باركود الصنف ثم Enter (مثل BC-001)"
                 data-v-rules="required,barcode" maxlength="100">
          <button type="button" class="btn-view" id="btnAddScan">إضافة مسح</button>
        </div>
        <div class="barcode-sim-row">
          <button type="button" class="btn-action primary" id="btnSimCorrect">✓ محاكاة مسح صحيح (كل البنود)</button>
          <button type="button" class="btn-action" id="btnSimWrong" style="background:#fee2e2;color:#b91c1c;">✗ محاكاة باركود خاطئ</button>
        </div>
        <div class="barcode-scanned" id="barcodeScanned"></div>
        <div class="barcode-alarm" id="barcodeAlarm" style="display:none;">
          ⛔ <span id="barcodeAlarmText">باركود غير مطابق لأمر التشغيل! تم إيقاف الصرف.</span>
        </div>
        <div style="margin-top:18px;display:flex;gap:10px;justify-content:flex-end;">
          <button type="button" class="btn-view" id="btnCancelBarcode">إلغاء</button>
          <button type="button" class="btn-action success" id="btnConfirmIssue">تأكيد الصرف</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Return receipt — barcode confirm -->
  <div class="modal-overlay" id="returnScanModal">
    <div class="modal modal-return modal-return-scan">
      <div class="modal-header return-modal-header">
        <div class="return-modal-title-wrap">
          <h3>📥 تأكيد استلام الارتجاع</h3>
          <p class="modal-subtitle">امسح باركود كل صنف لمطابقة الطلب الوارد من الورشة</p>
        </div>
        <button type="button" class="modal-close" id="closeReturnScanModal" aria-label="إغلاق">&times;</button>
      </div>
      <div class="modal-body return-modal-body">
        <div id="returnScanInfo" class="return-scan-info"></div>
        <div class="return-scan-field">
          <label for="returnBarcodeInput">باركود الصنف</label>
          <div class="barcode-scan-row return-barcode-row">
            <input type="text" id="returnBarcodeInput" class="form-control"
                   placeholder="امسح أو اكتب الباركود (مثل BC-008) ثم Enter"
                   data-v-rules="required,barcode" maxlength="100" autocomplete="off">
            <div class="return-qty-inline">
              <label for="returnQtyInput">الكمية</label>
              <input type="number" id="returnQtyInput" class="form-control return-qty-input"
                     min="1" max="999999" value="1"
                     data-v-rules="required,integer,minValue:1,maxValue:999999">
            </div>
            <button type="button" class="btn-action success" id="btnReturnScan">✓ تأكيد الاستلام</button>
          </div>
        </div>
        <div class="barcode-alarm" id="returnScanAlarm" style="display:none;">
          ⛔ <span id="returnScanAlarmText">باركود غير مطابق!</span>
        </div>
      </div>
      <div class="modal-footer return-modal-footer">
        <button type="button" class="btn-view" id="btnCloseReturnScan">إغلاق</button>
      </div>
    </div>
  </div>

  <!-- Return note — full detail view -->
  <div class="modal-overlay" id="returnDetailModal">
    <div class="modal modal-return modal-return-detail" onclick="event.stopPropagation()">
      <div class="modal-header return-modal-header">
        <div class="return-modal-title-wrap">
          <h3 id="returnDetailTitle">↩️ تفاصيل طلب الارتجاع</h3>
          <p class="modal-subtitle" id="returnDetailSubtitle"></p>
        </div>
        <button type="button" class="modal-close" id="closeReturnDetailModal" aria-label="إغلاق">&times;</button>
      </div>
      <div class="modal-body return-detail-body" id="returnDetailBody"></div>
      <div class="modal-footer return-modal-footer">
        <button type="button" class="btn-action primary" id="btnCloseReturnDetail">إغلاق</button>
      </div>
    </div>
  </div>

  <div class="toast" id="toast"></div>