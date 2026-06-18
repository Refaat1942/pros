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
          <input type="text" id="barcodeInput" placeholder="امسح أو اكتب باركود الصنف ثم Enter (مثل BC-001)">
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

  <!-- Receive Stock (Inward) Modal -->
  <div class="modal-overlay" id="receiveModal">
    <div class="modal">
      <div class="modal-header">
        <h3>📥 حركة وارد بالباركود — تحديث WAC</h3>
        <button type="button" class="modal-close" id="closeReceiveModal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="receive-grid">
          <div class="form-group"><label>الصنف</label>
            <select class="form-control" id="rcvItem"></select>
          </div>
          <div class="form-group"><label>الكمية الواردة</label>
            <input type="number" class="form-control" id="rcvQty" min="1" value="10">
          </div>
          <div class="form-group"><label>سعر الشراء (للوحدة)</label>
            <input type="number" class="form-control" id="rcvAmount" min="0" value="0">
          </div>
          <div class="form-group"><label>المورد</label>
            <input type="text" class="form-control" id="rcvSupplier" placeholder="اسم المورد">
          </div>
          <div class="form-group"><label>رقم فاتورة الشراء</label>
            <input type="text" class="form-control" id="rcvInvoice" placeholder="INV-...">
          </div>
          <div class="form-group"><label>تاريخ التوريد</label>
            <input type="text" class="form-control" id="rcvDate" value="">
          </div>
        </div>
        <div class="receive-wac" id="rcvWacPreview">—</div>
        <div style="margin-top:16px;display:flex;gap:10px;justify-content:flex-end;">
          <button type="button" class="btn-view" id="btnCancelReceive">إلغاء</button>
          <button type="button" class="btn-action success" id="btnConfirmReceive">تأكيد الاستلام</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Create Return Note Modal -->
  <div class="modal-overlay" id="returnCreateModal">
    <div class="modal">
      <div class="modal-header">
        <h3>➕ إنشاء إذن ارتجاع</h3>
        <button type="button" class="modal-close" id="closeReturnCreateModal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group"><label>BOM (تحت التشغيل فقط)</label>
          <select class="form-control" id="returnBomSelect"></select>
        </div>
        <div id="returnLinesPicker"></div>
        <div class="form-group"><label>سبب الارتجاع</label>
          <input type="text" class="form-control" id="returnReason" placeholder="مثال: فائض عن الحاجة في الورشة">
        </div>
        <div style="margin-top:16px;display:flex;gap:10px;justify-content:flex-end;">
          <button type="button" class="btn-view" id="btnCancelReturnCreate">إلغاء</button>
          <button type="button" class="btn-action success" id="btnConfirmReturnCreate">إصدار الإذن</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Return Barcode Scan Modal -->
  <div class="modal-overlay" id="returnScanModal">
    <div class="modal">
      <div class="modal-header">
        <h3>↩️ مسح باركود الارتجاع — استعادة للمخزن</h3>
        <button type="button" class="modal-close" id="closeReturnScanModal">&times;</button>
      </div>
      <div class="modal-body">
        <div id="returnScanInfo"></div>
        <div class="barcode-scan-row">
          <input type="text" id="returnBarcodeInput" placeholder="امسح باركود الصنف (مثل BC-005)">
          <input type="number" id="returnQtyInput" min="1" value="1" style="width:80px;" title="الكمية">
          <button type="button" class="btn-view" id="btnReturnScan">تسجيل ارتجاع</button>
        </div>
        <div class="barcode-alarm" id="returnScanAlarm" style="display:none;">
          ⛔ <span id="returnScanAlarmText">باركود غير مطابق!</span>
        </div>
        <div style="margin-top:16px;display:flex;gap:10px;justify-content:flex-end;">
          <button type="button" class="btn-view" id="btnCloseReturnScan">إغلاق</button>
        </div>
      </div>
    </div>
  </div>

  <div class="toast" id="toast"></div>