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
          <div class="adj-add-item-row">
            <div class="form-group adj-item-field">
              <label for="adjItemPickerSearch">الصنف</label>
              <div class="adj-item-picker" id="adjItemPicker">
                <input type="hidden" id="adjItemValue" value="">
                <button type="button" class="adj-picker-toggle form-control" id="adjItemPickerToggle"
                        aria-haspopup="listbox" aria-expanded="false">
                  <span id="adjItemPickerLabel">— اختر الصنف —</span>
                  <span class="adj-picker-caret" aria-hidden="true">▾</span>
                </button>
                <div class="adj-picker-panel" id="adjItemPickerPanel">
                  <input type="search" id="adjItemPickerSearch" class="adj-picker-search form-control"
                         placeholder="🔍 بحث بالكود أو الاسم..." autocomplete="off">
                  <ul class="adj-picker-list" id="adjItemPickerList" role="listbox"></ul>
                </div>
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

        <div style="margin-top:18px;display:flex;gap:10px;justify-content:flex-end;">
          <button type="button" class="btn-view" id="btnCancelAdj">إغلاق النافذة</button>
          <button type="button" class="btn-action success" id="btnCompleteAdj">📤 إرسال للتكاليف</button>
        </div>
      </div>
    </div>
  </div>

  @include('partials.tech-notes-modal')

  <div class="toast" id="toast" aria-live="polite"></div>

  <style>
    #adjModal.adj-picker-open .modal {
      overflow: visible;
    }

    #adjModal .modal-body {
      overflow-x: hidden;
    }

    #adjModal.adj-picker-open .modal-body {
      overflow: visible;
    }

    #adjModal .bom-table-wrap {
      max-height: min(36vh, 280px);
      overflow: auto;
    }

    #adjModal .adj-add-item-row {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: flex-end;
      position: relative;
      z-index: 5;
    }

    #adjModal .adj-item-field {
      flex: 1 1 280px;
      min-width: 0;
      margin: 0;
    }

    #adjModal .adj-qty-field {
      flex: 0 0 110px;
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
      min-height: 42px;
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

    #adjModal .adj-picker-panel {
      display: none;
      position: absolute;
      top: calc(100% + 6px);
      right: 0;
      left: 0;
      z-index: 1200;
      background: #fff;
      border: 1px solid var(--border, #e2e8f0);
      border-radius: 10px;
      box-shadow: 0 16px 40px rgba(15, 23, 42, 0.18);
      overflow: hidden;
    }

    #adjModal .adj-item-picker.is-open .adj-picker-panel {
      display: block;
    }

    #adjModal .adj-picker-search {
      width: 100%;
      border: 0;
      border-bottom: 1px solid var(--border, #e2e8f0);
      border-radius: 0;
      min-height: 42px;
      box-shadow: none;
    }

    #adjModal .adj-picker-search:focus {
      outline: none;
      box-shadow: inset 0 -2px 0 var(--primary, #d97706);
    }

    #adjModal .adj-picker-list {
      list-style: none !important;
      margin: 0;
      padding: 6px 0;
      max-height: 220px;
      overflow-y: auto;
    }

    #adjModal .adj-picker-list li {
      list-style: none !important;
    }

    #adjModal .adj-picker-option {
      padding: 10px 14px;
      font-size: 13px;
      cursor: pointer;
      transition: background 0.15s;
    }

    #adjModal .adj-picker-option:hover,
    #adjModal .adj-picker-option.is-selected {
      background: rgba(217, 119, 6, 0.08);
    }

    #adjModal .adj-picker-option.is-disabled {
      color: var(--text-muted, #94a3b8);
      cursor: not-allowed;
    }

    #adjModal .adj-picker-muted {
      font-size: 12px;
    }

    #adjModal .adj-picker-empty {
      padding: 14px;
      text-align: center;
      color: var(--text-muted, #94a3b8);
      font-size: 13px;
    }
  </style>
