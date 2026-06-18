<aside class="sidebar">
    <div class="sidebar-brand">
      <div class="icon">🎯</div>
      <h2>مكتب التشغيل</h2>
      <span>أوامر الإنتاج والصرف</span>
    </div>
    <ul class="nav-menu">
      <li><a href="#" class="active" data-section="operations"><span class="nav-icon">🎯</span> أوامر التشغيل</a></li>
    </ul>
    <div class="sidebar-footer">
      <a href="{{ route('home') }}" class="btn-back">← العودة للصفحة الرئيسية</a>
    </div>
  </aside>

  <main class="main">
    <div class="page-header">
      <div>
        <h1 id="pageTitle">مكتب التشغيل — أوامر الصرف والإنتاج</h1>
        <p>م. سامح عبدالله — مسؤول مكتب التشغيل</p>
      </div>
      <div class="user-chip">
        <div class="avatar">س</div>
        <span>سامح عبدالله</span>
      </div>
    </div>

    <div class="section-view active" id="section-operations">
      <div class="panel inventory-wrap">
        <div class="panel-header">
          <h3>🎯 مكتب التشغيل — أوامر الصرف والإنتاج</h3>
          <span class="badge" id="opsBadge">0</span>
        </div>
        <p style="padding:0 24px 12px;margin:0;color:var(--text-muted);font-size:13px;">
          يلتقي هنا المساران (مدني بعد الموافقة · عسكري مباشرة). كل حالة لها <strong>رقم أمر تشغيل مركزي</strong>. الصرف للمخزن يتم بمسح الباركود.
        </p>
        <div class="bom-summary" id="opsSummary"></div>
        <div class="bom-table-wrap">
          <table class="bom-table">
            <thead>
              <tr>
                <th>أمر التشغيل</th>
                <th>المريض</th>
                <th>التصنيف</th>
                <th>مرحلة BOM / الشغل</th>
                <th>البنود</th>
                <th class="col-actions">إجراء</th>
              </tr>
            </thead>
            <tbody id="opsTable"></tbody>
          </table>
        </div>
      </div>
    </div>
  </main>

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

  <div class="toast" id="toast"></div>