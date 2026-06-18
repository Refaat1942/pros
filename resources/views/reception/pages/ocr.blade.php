<div class="tab-content" id="tab-ocr">
      <div class="panel">
        <div class="panel-header">
          <h3>📄 رفع خطاب الموافقة المالية</h3>
        </div>
        <div class="panel-body" style="padding:24px;">
          <div class="qr-scan-banner">
            <div>
              <strong>📱 مسار المريض بدون موافقة مسبقة</strong>
              <p>عند عودة المريض بعرض السعر المطبوع، امسح رمز QR المطبوع على الورقة لاسترجاع الطلب الأصلي والسعر المثبت فوراً</p>
            </div>
            <button type="button" class="btn btn-primary" id="btnScanQR">📱 مسح QR Code</button>
          </div>

          <div class="upload-zone" id="uploadZone">
            <div class="upload-icon">📤</div>
            <p><strong>اسحب صورة خطاب الموافقة هنا</strong> أو انقر للاختيار</p>
            <p class="hint">يدعم: JPG, PNG, PDF — قراءة تلقائية للنص العربي</p>
            <input type="file" id="fileInput" accept="image/*,.pdf" style="display:none;">
          </div>

          <div class="ocr-loading" id="ocrLoading">
            <div class="spinner"></div>
            <p>جاري القراءة الضوئية المحلية (OCR)...</p>
            <p style="font-size:12px;color:var(--text-muted);margin-top:8px;">استخراج: الاسم، القيمة المالية، جهة التعاقد</p>
          </div>

          <div class="ocr-results" id="ocrResults">
            <h4>✅ نتائج القراءة الضوئية التلقائية</h4>
            <div class="ocr-field">
              <span class="label">اسم المريض</span>
              <span class="value" id="ocrName">—</span>
            </div>
            <div class="ocr-field">
              <span class="label">القيمة المالية المعتمدة</span>
              <span class="value" id="ocrAmount">—</span>
            </div>
            <div class="ocr-field">
              <span class="label">جهة التعاقد</span>
              <span class="value" id="ocrCompany">—</span>
            </div>
            <div class="ocr-field">
              <span class="label">رقم خطاب الموافقة</span>
              <span class="value" id="ocrRef">—</span>
            </div>
            <div class="ocr-field">
              <span class="label">تاريخ الخطاب</span>
              <span class="value" id="ocrDate">—</span>
            </div>
          </div>

          <div class="form-row" id="ocrForm" style="display:none;margin-top:20px;">
            <div class="form-group" style="padding:0;">
              <label>تأكيد اسم المريض</label>
              <input type="text" class="form-control" id="confirmName" readonly>
            </div>
            <div class="form-group" style="padding:0;">
              <label>تأكيد القيمة (ج.م)</label>
              <input type="text" class="form-control" id="confirmAmount" readonly>
            </div>
          </div>

          <div class="form-actions" id="ocrActions" style="display:none;">
            <button class="btn btn-primary" id="btnBypass">
              ⚡ تأكيد وتخطي المسار — إرسال للمخزن
            </button>
            <button class="btn btn-secondary" id="btnResetOcr">إعادة الرفع</button>
          </div>
        </div>
      </div>
    </div>
