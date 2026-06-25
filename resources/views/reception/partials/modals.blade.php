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
    <div class="modal quote-modal">
      <div class="modal-header">
        <h3 id="quoteModalTitle">🧾 عرض السعر</h3>
        <button class="modal-close" id="closeQuoteModal">&times;</button>
      </div>
      <div class="modal-body quote-modal-body">
        <div class="quote-document" id="quoteModalBody"></div>
      </div>
      <div class="modal-footer quote-modal-footer">
        <button class="btn btn-secondary" id="btnCloseQuoteModal">إغلاق</button>
        <button type="button" class="btn btn-primary" id="btnPrintQuoteModal">🖨️ طباعة عرض السعر</button>
      </div>
    </div>
  </div>

  {{-- ══════════════════════════════════════════════════════════════════════
       OCR Approval Modal — رفع خطاب الموافقة + Human Override + تأكيد الاعتماد
       Civilian pathway only.
       ══════════════════════════════════════════════════════════════════════ --}}
  <div class="modal-overlay" id="ocrApprovalModal"
       style="display:none;position:fixed;inset:0;z-index:600;background:rgba(15,23,42,.65);
              backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:16px;">
    <div style="background:#fff;border-radius:20px;width:100%;max-width:680px;
                box-shadow:0 24px 80px rgba(0,0,0,.25);overflow:hidden;display:flex;flex-direction:column;max-height:92vh;">

      {{-- Header --}}
      <div style="background:linear-gradient(135deg,#059669,#0d9488);color:#fff;padding:20px 24px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
        <div>
          <h3 style="font-size:16px;font-weight:700;margin:0;">📄 رفع خطاب موافقة الجهة الضامنة (OCR)</h3>
          <p style="font-size:12px;opacity:.85;margin:4px 0 0;" id="ocrQuoteRef">—</p>
        </div>
        <button type="button" id="btnCloseOcrModal"
                style="background:rgba(255,255,255,.2);border:none;border-radius:50%;width:32px;height:32px;
                       font-size:18px;cursor:pointer;color:#fff;display:flex;align-items:center;justify-content:center;">&times;</button>
      </div>

      {{-- Scrollable body --}}
      <div style="flex:1;overflow-y:auto;padding:24px;" id="ocrModalContent">

        {{-- Step 1: Upload zone --}}
        <div id="ocrStep1">
          <div id="ocrUploadZone"
               style="border:2px dashed #10b981;border-radius:14px;padding:36px 24px;text-align:center;
                      cursor:pointer;background:#f0fdf4;transition:background .2s;"
               onclick="document.getElementById('ocrFileInput').click();"
               ondragover="event.preventDefault();this.style.background='#d1fae5';"
               ondragleave="this.style.background='#f0fdf4';"
               ondrop="event.preventDefault();this.style.background='#f0fdf4';handleOcrFileDrop(event);">
            <div style="font-size:40px;margin-bottom:12px;">📤</div>
            <p style="font-weight:700;color:#065f46;margin:0 0 6px;">اسحب خطاب الموافقة هنا أو انقر للاختيار</p>
            <p style="font-size:12px;color:#6b7280;margin:0;">يدعم: JPG, PNG, PDF — حجم أقصى 10 ميجا</p>
          </div>
          <input type="file" id="ocrFileInput" accept="image/*,.pdf" style="display:none;">
        </div>

        {{-- Step 2: Loading --}}
        <div id="ocrStep2" style="display:none;text-align:center;padding:32px;">
          <div style="width:48px;height:48px;border:4px solid #e2e8f0;border-top-color:#059669;
                      border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 16px;"></div>
          <p style="font-weight:600;color:#374151;">جاري قراءة الخطاب واستخراج البيانات...</p>
          <p style="font-size:12px;color:#9ca3af;margin-top:4px;">اسم المريض — المبلغ — رقم الخطاب — جهة التعاقد</p>
        </div>

        {{-- Step 3: Human Override verification --}}
        <div id="ocrStep3" style="display:none;">
          <div style="background:#fefce8;border:1px solid #fde047;border-radius:10px;padding:14px 16px;margin-bottom:20px;display:flex;gap:10px;align-items:flex-start;">
            <span style="font-size:18px;flex-shrink:0;">⚠️</span>
            <p style="font-size:13px;color:#78350f;margin:0;line-height:1.6;">
              <strong>برجاء مراجعة البيانات المستخرجة ومطابقتها مع الخطاب الورقي قبل التأكيد النهائي.</strong>
              يمكنك تعديل أي حقل يدوياً في حال وجود خطأ في القراءة.
            </p>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
            <div>
              <label style="display:block;font-size:13px;font-weight:700;color:#374151;margin-bottom:6px;">
                👤 اسم المريض المستخرج
              </label>
              <input type="text" id="ocrConfirmName"
                     style="width:100%;padding:10px 12px;border:2px solid #10b981;border-radius:8px;font-family:inherit;font-size:14px;box-sizing:border-box;"
                     placeholder="اسم المريض">
            </div>
            <div>
              <label style="display:block;font-size:13px;font-weight:700;color:#374151;margin-bottom:6px;">
                💰 المبلغ المعتمد (ج.م)
              </label>
              <input type="number" id="ocrConfirmAmount" step="0.01" min="0"
                     style="width:100%;padding:10px 12px;border:2px solid #10b981;border-radius:8px;font-family:inherit;font-size:14px;box-sizing:border-box;"
                     placeholder="المبلغ المالي">
            </div>
            <div>
              <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">
                🏢 جهة التعاقد
              </label>
              <input type="text" id="ocrConfirmCompany"
                     style="width:100%;padding:10px 12px;border:2px solid #10b981;border-radius:8px;font-family:inherit;font-size:14px;box-sizing:border-box;"
                     placeholder="جهة التعاقد">
            </div>
            <div>
              <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">
                🔢 رقم خطاب الموافقة (اختياري)
              </label>
              <input type="text" id="ocrLetterRef"
                     style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:14px;box-sizing:border-box;"
                     placeholder="مثال: 1234/2026">
            </div>
          </div>

          <div id="ocrError"
               style="display:none;background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:12px 14px;margin-bottom:16px;color:#dc2626;font-size:13px;font-weight:600;"></div>

          <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button type="button" id="btnResetOcrModal"
                    style="padding:10px 20px;border-radius:8px;border:1px solid #e2e8f0;background:#f8fafc;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;color:#64748b;">
              🔄 إعادة الرفع
            </button>
            <button type="button" id="btnConfirmOcr"
                    style="padding:10px 24px;border-radius:8px;border:none;background:#059669;color:#fff;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;">
              ✅ تأكيد واعتماد مالي — توليد أمر الشغل
            </button>
          </div>
        </div>

        {{-- Step 4: Success --}}
        <div id="ocrStep4" style="display:none;text-align:center;padding:32px;">
          <div style="font-size:52px;margin-bottom:16px;">🎉</div>
          <h4 style="font-size:18px;font-weight:700;color:#065f46;margin:0 0 8px;">تم الاعتماد المالي بنجاح!</h4>
          <p style="color:#374151;margin:0 0 6px;" id="ocrSuccessText">—</p>
          <p style="font-family:monospace;font-size:14px;font-weight:700;color:#059669;
                    background:#f0fdf4;border-radius:8px;padding:8px 16px;display:inline-block;margin-top:8px;" id="ocrSuccessWO">—</p>
          <p style="font-size:12px;color:#6b7280;margin-top:12px;">تم إرسال الحالة إلى لوحة المخزن — يمكن الآن صرف مواد الـ BOM.</p>
          <button type="button" id="btnCloseOcrSuccess"
                  style="margin-top:20px;padding:10px 28px;border-radius:8px;border:none;background:#059669;color:#fff;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;">
            حسناً
          </button>
        </div>

      </div>{{-- end scrollable body --}}
    </div>
  </div>

  <style>
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>

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
          <button class="btn btn-primary" id="btnPrintPatientCard" type="button">🖨️ طباعة البطاقة</button>
        </div>
      </div>
    </div>
  </div>

  <div class="toast" id="toast"></div>