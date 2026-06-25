  <div class="modal-overlay" id="adjModal">
    <div class="modal" style="max-width:720px;">
      <div class="modal-header">
        <h3 id="adjModalTitle">🧩 مراجعة المعدلات</h3>
        <button type="button" class="modal-close" id="closeAdjModal">&times;</button>
      </div>
      <div class="modal-body">
        <div id="adjReworkBanner" class="adj-rework-banner" hidden
             style="margin:0 0 14px;padding:12px 14px;border-radius:10px;border:1px solid #fecaca;background:#fef2f2;">
          <p class="adj-rework-title" id="adjReworkTitle" style="margin:0 0 6px;font-weight:700;color:#991b1b;">↩️ إرجاع من مكتب التشغيل</p>
          <p class="adj-rework-meta" id="adjReworkMeta" style="margin:0 0 8px;font-size:12px;color:#b91c1c;"></p>
          <p class="adj-rework-text" id="adjReworkReason" style="margin:0;font-size:13px;color:#7f1d1d;white-space:pre-wrap;line-height:1.6;"></p>
        </div>

        <p style="margin:0 0 12px;color:var(--text-muted);font-size:13px;">
          البنود الأصلية (الفني) للقراءة فقط. أضف بنوداً إضافية ثم أغلق المعدلات.
        </p>

        <div class="bom-table-wrap">
          <table class="bom-table">
            <thead>
              <tr>
                <th>الكود</th>
                <th>الصنف</th>
                <th>الكمية</th>
                <th>المصدر</th>
              </tr>
            </thead>
            <tbody id="adjBomItems"></tbody>
          </table>
        </div>

        <div style="margin-top:18px;padding-top:14px;border-top:1px solid var(--border,#e2e8f0);">
          <h4 style="margin:0 0 10px;font-size:14px;">➕ إضافة بند</h4>
          <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group" style="flex:1;min-width:140px;margin:0;">
              <label>كود الصنف</label>
              <input type="text" class="form-control" id="adjItemCode" list="adjCatalog" placeholder="STK-...">
              <datalist id="adjCatalog"></datalist>
            </div>
            <div class="form-group" style="flex:1;min-width:140px;margin:0;">
              <label>الاسم</label>
              <input type="text" class="form-control" id="adjItemName" placeholder="اسم الصنف">
            </div>
            <div class="form-group" style="width:90px;margin:0;">
              <label>الكمية</label>
              <input type="number" class="form-control" id="adjItemQty" min="1" value="1">
            </div>
            <button type="button" class="btn-action primary" id="btnAddAdjItem">إضافة</button>
          </div>
          <div id="adjFormError" class="adj-form-error" style="display:none;" role="alert"></div>
        </div>

        <div style="margin-top:18px;display:flex;gap:10px;justify-content:flex-end;">
          <button type="button" class="btn-view" id="btnCancelAdj">إغلاق النافذة</button>
          <button type="button" class="btn-action success" id="btnCompleteAdj">📤 إرسال للتكاليف</button>
        </div>
      </div>
    </div>
  </div>

  @include('partials.tech-notes-modal')

  <div class="toast" id="toast" aria-live="polite"></div>
