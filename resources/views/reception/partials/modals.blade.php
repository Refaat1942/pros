  <!-- QR Scan Modal -->
  <div class="modal-overlay" id="qrModal">
    <div class="modal">
      <div class="modal-header">
        <h3>📱 مسح QR Code</h3>
        <button class="modal-close" id="closeQrModal">&times;</button>
      </div>
      <div class="modal-body" style="text-align:center;">
        <div class="scan-animation">
          <div class="qr-mini"></div>
          <div class="scan-line"></div>
        </div>
        <p id="scanStatus">جاري المسح...</p>
        <p style="font-size:12px;color:var(--text-muted);margin-top:8px;" id="scanQuoteHint">—</p>
      </div>
    </div>
  </div>

  <!-- Quote Modal -->
  <div class="modal-overlay" id="quoteModal">
    <div class="modal modal-wide quote-modal">
      <div class="modal-header">
        <h3 id="quoteModalTitle">🧾 عرض السعر</h3>
        <button class="modal-close" id="closeQuoteModal">&times;</button>
      </div>
      <div class="modal-body quote-modal-body">
        <div class="quote-document" id="quoteModalBody"></div>
      </div>
      <div class="modal-footer quote-modal-footer">
        <button class="btn btn-secondary" id="btnCloseQuoteModal">إغلاق</button>
        <button class="btn btn-primary" onclick="window.print()">🖨️ طباعة عرض السعر</button>
      </div>
    </div>
  </div>

  <!-- Patient File Modal -->
  <div class="modal-overlay" id="patientFileModal">
    <div class="modal modal-wide">
      <div class="modal-header">
        <h3 id="patientFileTitle">👤 ملف المريض</h3>
        <button class="modal-close" id="closePatientFileModal">&times;</button>
      </div>
      <div class="modal-body">
        <div style="margin-bottom:16px;" id="patientFileStatus"></div>
        <div class="patient-file-meta" id="patientFileMeta"></div>
        <div class="patient-file-section">
          <h4>📋 آخر الزيارات</h4>
          <table data-paginate="10" class="patient-visits-table">
            <thead>
              <tr>
                <th>التاريخ</th>
                <th>الإجراء</th>
                <th>الحالة</th>
              </tr>
            </thead>
            <tbody id="patientFileVisits"></tbody>
          </table>
        </div>
        <div style="margin-top:20px;display:flex;gap:10px;justify-content:flex-end;">
          <button class="btn btn-secondary" id="btnClosePatientFile">إغلاق</button>
          <button class="btn btn-primary" id="btnPrintPatientFile" onclick="window.print()">🖨️ طباعة الملف</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Patient QR Card Modal -->
  <div class="modal-overlay" id="patientCardModal">
    <div class="modal">
      <div class="modal-header">
        <h3>🆔 بطاقة المريض الرقمية</h3>
        <button class="modal-close" id="closePatientCardModal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="patient-id-card" id="patientIdCard">
          <div class="pic-head">
            <span class="pic-logo">🦿 مركز الأطراف الصناعية</span>
            <span class="pic-type" id="picType">🌐 مدني</span>
          </div>
          <div class="pic-body">
            <div class="pic-info">
              <div class="pic-name" id="picName">—</div>
              <div class="pic-id">رقم المريض: <strong id="picId">—</strong></div>
              <div class="pic-queue" id="picQueueWrap">رقم الدور: <strong id="picQueue">—</strong></div>
              <div class="pic-company" id="picCompany">—</div>
              <div class="pic-rank" id="picRank" style="display:none;"></div>
            </div>
            <div class="pic-qr">
              <div class="pic-qr-image" id="picQr"></div>
              <small id="picQrText">—</small>
            </div>
          </div>
          <div class="pic-foot">امسح الكود لمتابعة حالة الطلب وموعد التسليم</div>
        </div>
        <div style="margin-top:18px;display:flex;gap:10px;justify-content:flex-end;">
          <button class="btn btn-secondary" id="btnClosePatientCard">إغلاق</button>
          <button class="btn btn-primary" onclick="window.print()">🖨️ طباعة البطاقة</button>
        </div>
      </div>
    </div>
  </div>

  <div class="toast" id="toast"></div>