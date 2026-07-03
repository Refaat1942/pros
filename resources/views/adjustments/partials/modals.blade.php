  <div class="modal-overlay" id="adjModal">
    <div class="modal adj-modal">
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

        <div id="adjPendingBanner" class="adj-pending-banner" hidden
             style="margin:0 0 14px;padding:12px 14px;border-radius:10px;border:1px solid #fde68a;background:#fffbeb;">
          <p style="margin:0;font-size:13px;color:#92400e;font-weight:700;">⏳ يوجد طلب تعديل معلّق — بانتظار موافقة الإدارة.</p>
        </div>

        <div class="bom-table-wrap">
          <table class="bom-table">
            <thead>
              <tr>
                <th>الكود</th>
                <th>الصنف</th>
                <th>الكمية</th>
                <th>المصدر</th>
                <th class="adj-col-action" aria-label="إجراءات"></th>
              </tr>
            </thead>
            <tbody id="adjBomItems"></tbody>
          </table>
        </div>

        <div id="adjDirectModifySection" style="margin-top:18px;padding-top:14px;border-top:1px solid var(--border,#e2e8f0);">
          <div class="adj-add-item-row">
            <div class="form-group adj-item-field">
              <label for="adjItemPickerToggle">الصنف</label>
              <div class="adj-item-picker" id="adjItemPicker">
                <input type="hidden" id="adjItemValue" value="">
                <button type="button" class="adj-picker-toggle form-control" id="adjItemPickerToggle"
                        aria-haspopup="dialog" aria-expanded="false" aria-controls="adjCatalogModal">
                  <span id="adjItemPickerLabel">— اختر صنف/أصناف —</span>
                  <span class="adj-picker-caret" aria-hidden="true">▾</span>
                </button>
              </div>
            </div>
            <div class="form-group adj-qty-field">
              <label for="adjItemQty">الكمية</label>
              <input type="number" class="form-control" id="adjItemQty" min="1" value="1" disabled>
            </div>
            <button type="button" class="btn-action primary adj-add-btn" id="btnAddAdjItem" disabled>إضافة</button>
          </div>
          <div id="adjFormError" class="adj-form-error" style="display:none;" role="alert"></div>
        </div>

        <div style="margin-top:18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;">
          <button type="button" class="btn-view" id="btnCancelAdj">إغلاق النافذة</button>
          <button type="button" class="btn-action success" id="btnCompleteAdj">📤 إرسال للتكاليف</button>
          <button type="button" class="btn-action primary" id="btnSubmitAdjEditRequest" hidden>📨 إرسال طلب التعديل للإدارة</button>
        </div>
      </div>
    </div>
  </div>

  {{-- Popup كامل لاختيار الصنف --}}
  <div class="adj-catalog-overlay" id="adjCatalogModal" role="dialog" aria-modal="true"
       aria-labelledby="adjCatalogTitle" hidden>
    <div class="adj-catalog-dialog" id="adjCatalogDialog" onclick="event.stopPropagation()">
      <div class="adj-catalog-header">
        <h3 id="adjCatalogTitle">🔍 اختيار صنف من الكاتلوج</h3>
        <button type="button" class="adj-catalog-close" id="adjCatalogClose" aria-label="إغلاق">&times;</button>
      </div>
      <div class="adj-catalog-search-wrap">
        <input type="search" id="adjItemPickerSearch" class="adj-catalog-search form-control"
               placeholder="🔍 بحث بالكود أو الاسم..." autocomplete="off">
      </div>
      <ul class="adj-picker-list" id="adjItemPickerList" role="listbox"></ul>
      <div class="adj-catalog-footer">
        <span id="adjCatalogSelectedHint" class="adj-catalog-selected-hint">حدّد الأصناف بالـ checkbox</span>
        <div class="adj-catalog-footer-actions">
          <button type="button" class="btn-action" id="btnAdjCatalogCancel">إلغاء</button>
          <button type="button" class="btn-action primary" id="btnAdjCatalogAdd" disabled>إضافة المحدّد</button>
        </div>
      </div>
    </div>
  </div>

  @include('partials.tech-notes-modal')

  <div class="toast" id="toast" aria-live="polite"></div>

  <style>
    #adjModal .adj-modal {
      max-width: min(1280px, 98vw);
      width: 100%;
      max-height: min(96vh, 960px);
    }

    #adjModal .modal-body {
      overflow-x: hidden;
      overflow-y: auto;
      max-height: calc(96vh - 72px);
    }

    #adjModal .bom-table-wrap {
      max-height: min(52vh, 480px);
      overflow: auto;
    }

    #adjModal .adj-add-item-row {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: flex-end;
      position: relative;
      z-index: 1;
    }

    #adjModal .adj-item-field {
      flex: 1 1 420px;
      min-width: 0;
      margin: 0;
    }

    #adjModal .adj-qty-field {
      flex: 0 0 130px;
      margin: 0;
    }

    #adjModal .adj-add-btn {
      flex: 0 0 auto;
      margin-bottom: 2px;
      align-self: flex-end;
    }

    #adjModal .adj-item-picker {
      position: relative;
    }

    #adjModal .adj-picker-toggle {
      width: 100%;
      min-height: 48px;
      font-size: 15px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      text-align: right;
      cursor: pointer;
      background: #fff;
    }

    #adjModal .adj-picker-toggle span:first-child {
      flex: 1;
      min-width: 0;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    #adjModal .adj-picker-caret {
      color: var(--text-muted, #64748b);
      font-size: 12px;
      flex-shrink: 0;
    }

    #adjModal .adj-item-picker.is-open .adj-picker-toggle {
      border-color: var(--primary, #d97706);
      box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.12);
    }

    .adj-catalog-overlay {
      position: fixed;
      inset: 0;
      z-index: 2000;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 16px;
      background: rgba(15, 23, 42, 0.62);
      backdrop-filter: blur(4px);
    }

    .adj-catalog-overlay.is-open {
      display: flex;
    }

    body.adj-catalog-open {
      overflow: hidden;
    }

    .adj-catalog-dialog {
      width: min(720px, 96vw);
      max-height: min(88vh, 820px);
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 28px 64px rgba(15, 23, 42, 0.28);
      display: flex;
      flex-direction: column;
      overflow: hidden;
      animation: adjCatalogIn 0.22s ease;
    }

    @keyframes adjCatalogIn {
      from { opacity: 0; transform: translateY(12px) scale(0.98); }
      to { opacity: 1; transform: translateY(0) scale(1); }
    }

    .adj-catalog-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 16px 18px;
      border-bottom: 1px solid var(--border, #e2e8f0);
    }

    .adj-catalog-header h3 {
      margin: 0;
      font-size: 17px;
      font-weight: 800;
      color: #0f172a;
    }

    .adj-catalog-close {
      border: 0;
      background: transparent;
      font-size: 28px;
      line-height: 1;
      color: #94a3b8;
      cursor: pointer;
      padding: 0 4px;
    }

    .adj-catalog-close:hover {
      color: #475569;
    }

    .adj-catalog-search-wrap {
      padding: 12px 16px;
      border-bottom: 1px solid var(--border, #e2e8f0);
      flex-shrink: 0;
    }

    .adj-catalog-search {
      width: 100%;
      min-height: 48px;
      font-size: 15px;
      border-radius: 10px;
    }

    .adj-catalog-overlay .adj-picker-list {
      list-style: none !important;
      margin: 0;
      padding: 8px 0;
      flex: 1 1 auto;
      min-height: 280px;
      max-height: none;
      overflow-y: auto;
    }

    .adj-catalog-overlay .adj-picker-list li {
      list-style: none !important;
    }

    .adj-catalog-overlay .adj-picker-option {
      display: block;
      padding: 0;
      margin: 0;
      border-bottom: 1px solid rgba(226, 232, 240, 0.7);
      cursor: default;
    }

    .adj-catalog-overlay .adj-picker-option:last-child {
      border-bottom: 0;
    }

    .adj-catalog-overlay .adj-picker-check-label {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 14px 18px;
      cursor: pointer;
      margin: 0;
    }

    .adj-catalog-overlay .adj-picker-checkbox {
      width: 18px;
      height: 18px;
      margin-top: 4px;
      flex-shrink: 0;
      accent-color: var(--primary, #d97706);
      cursor: pointer;
    }

    .adj-catalog-overlay .adj-picker-check-body {
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .adj-catalog-overlay .adj-picker-option.is-disabled .adj-picker-check-label {
      cursor: not-allowed;
      opacity: 0.72;
    }

    .adj-catalog-overlay .adj-picker-option.is-selected .adj-picker-check-label {
      background: rgba(217, 119, 6, 0.08);
    }

    .adj-catalog-overlay .adj-picker-option:hover:not(.is-disabled) .adj-picker-check-label {
      background: rgba(217, 119, 6, 0.06);
    }

    .adj-catalog-footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 12px 16px;
      border-top: 1px solid var(--border, #e2e8f0);
      background: #fafafa;
      flex-shrink: 0;
    }

    .adj-catalog-selected-hint {
      font-size: 13px;
      font-weight: 700;
      color: var(--text-muted, #64748b);
    }

    .adj-catalog-footer-actions {
      display: flex;
      gap: 8px;
      flex-shrink: 0;
    }

    .adj-catalog-overlay .adj-picker-code {
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      font-size: 13px;
      font-weight: 700;
      color: #64748b;
      letter-spacing: 0.02em;
    }

    .adj-catalog-overlay .adj-picker-name {
      font-size: 16px;
      font-weight: 700;
      color: #0f172a;
    }

    .adj-catalog-overlay .adj-picker-option:hover,
    .adj-catalog-overlay .adj-picker-option.is-selected {
      background: transparent;
    }

    .adj-catalog-overlay .adj-picker-option.is-disabled {
      color: var(--text-muted, #94a3b8);
      cursor: not-allowed;
    }

    .adj-catalog-overlay .adj-picker-muted {
      font-size: 13px;
      font-weight: 600;
    }

    .adj-catalog-overlay .adj-picker-empty {
      padding: 24px 18px;
      text-align: center;
      color: var(--text-muted, #94a3b8);
      font-size: 15px;
    }

    #adjModal .adj-col-action {
      width: 48px;
      text-align: center;
    }

    #adjModal .adj-remove-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 34px;
      height: 34px;
      padding: 0;
      border: 1px solid #fecaca;
      border-radius: 10px;
      background: #fff5f5;
      color: #dc2626;
      cursor: pointer;
      transition: background 0.15s, border-color 0.15s, transform 0.1s;
    }

    #adjModal .adj-remove-btn:hover:not(:disabled) {
      background: #fee2e2;
      border-color: #f87171;
    }

    #adjModal .adj-remove-btn:active:not(:disabled) {
      transform: scale(0.96);
    }

    #adjModal .adj-remove-btn:disabled {
      opacity: 0.55;
      cursor: wait;
    }

    #adjModal .adj-remove-btn svg {
      width: 16px;
      height: 16px;
      display: block;
    }
  </style>
