<div class="panel" id="stockKitsPanel">
    <div class="panel-header">
        <h3>🧩 أطقم جاهزة ومخصصات</h3>
        <button type="button" class="btn-add-rank" id="btnAddStockKit">➕ إضافة طقم</button>
    </div>
    <p class="catalog-table-hint" style="margin:12px 16px 0;">
        الطقم الجاهز يُختار في التوصيف أو المعدلات فيُفكّك تلقائياً إلى مكوّناته. «مخصصات» = مجموعة إكسسوارات جاهزة بكمياتها.
    </p>
    <div class="data-toolbar">
        <input type="text" id="stockKitSearch" placeholder="🔍 بحث في الأطقم...">
        <span class="toolbar-count" id="stockKitCount">0 طقم</span>
    </div>
    <div class="panel-body">
        <table>
            <thead>
                <tr>
                    <th>الاسم</th>
                    <th>الكود</th>
                    <th>النوع</th>
                    <th>المكوّنات</th>
                    <th>الحالة</th>
                    <th style="width:160px">إجراء</th>
                </tr>
            </thead>
            <tbody id="stockKitsTable"></tbody>
        </table>
    </div>
</div>

<div class="catalog-modal-overlay" id="stockKitModal" role="dialog" aria-modal="true" hidden>
    <div class="catalog-modal catalog-form-modal stock-kit-modal" onclick="event.stopPropagation()">
        <div class="catalog-modal-header">
            <div>
                <h3 id="stockKitModalTitle">➕ طقم جديد</h3>
                <div class="modal-code" id="stockKitModalSubtitle">ابحث في المربع أدناه (مثال: رك) — ليس في اسم الطقم</div>
            </div>
            <button type="button" class="catalog-modal-close" id="closeStockKitModal" aria-label="إغلاق">&times;</button>
        </div>
        <div class="catalog-modal-body stock-kit-modal__body">
            <input type="hidden" id="stockKitId">

            <section class="stock-kit-meta-card">
                <div class="stock-kit-meta-grid">
                    <div class="stock-kit-field stock-kit-field--wide">
                        <label class="stock-kit-label" for="stockKitName">اسم الطقم *</label>
                        <input type="text" id="stockKitName" class="stock-kit-input" maxlength="255" placeholder="مثال: طقم ركبة كاملة">
                    </div>
                    <div class="stock-kit-field">
                        <label class="stock-kit-label" for="stockKitType">النوع</label>
                        <select id="stockKitType" class="stock-kit-input">
                            <option value="assembly">طقم جاهز (تجميع)</option>
                            <option value="accessory">مخصصات</option>
                        </select>
                    </div>
                    <div class="stock-kit-field stock-kit-field--check">
                        <label class="stock-kit-check">
                            <input type="checkbox" id="stockKitActive" checked>
                            <span>نشط — يظهر في التوصيف والمعدلات</span>
                        </label>
                    </div>
                    <div class="stock-kit-field stock-kit-field--wide">
                        <label class="stock-kit-label" for="stockKitDescription">وصف (اختياري)</label>
                        <input type="text" id="stockKitDescription" class="stock-kit-input" placeholder="ملاحظات قصيرة...">
                    </div>
                </div>
            </section>

            <section class="stock-kit-search-card">
                <div class="stock-kit-search-head">
                    <h4>🔍 إضافة مكوّنات من كتالوج الأصناف</h4>
                    <span class="stock-kit-search-badge">اكتب هنا — مثال: رك ، كف ، مفصل</span>
                </div>
                <input type="search" id="stockKitItemSearch" class="stock-kit-input stock-kit-input--search" placeholder="بحث بالاسم أو الكود أو رقم الصفحة..." autocomplete="off">
                <div id="stockKitItemResults" class="stock-kit-item-results" role="listbox" aria-label="نتائج البحث"></div>
            </section>

            <section class="stock-kit-table-card">
                <div class="stock-kit-components-head">
                    <h4>📦 مكوّنات الطقم</h4>
                    <span class="stock-kit-components-count" id="stockKitComponentsCount">0 صنف</span>
                </div>
                <div class="stock-kit-components-table-wrap">
                    <table class="stock-kit-components-table">
                        <thead>
                            <tr>
                                <th>الكود</th>
                                <th>اسم الصنف</th>
                                <th>رقم الصفحة</th>
                                <th>الوحدة</th>
                                <th style="width:110px;">الكمية</th>
                                <th style="width:64px;"></th>
                            </tr>
                        </thead>
                        <tbody id="stockKitComponentsBody">
                            <tr id="stockKitComponentsEmpty">
                                <td colspan="6" class="stock-kit-empty-cell">ابحث أعلاه وأضف الأصناف — يمكنك إضافة أكثر من صنف</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div id="stockKitError" class="catalog-form-error" style="display:none;"></div>
        </div>
        <div class="catalog-modal-footer">
            <button type="button" class="btn-action" id="cancelStockKitModal">إلغاء</button>
            <button type="button" class="btn-action success" id="saveStockKitBtn">💾 حفظ الطقم</button>
        </div>
    </div>
</div>

<style>
    /* الـ modal خارج #stockKitsPanel — التنسيق مربوط بـ #stockKitModal مباشرة */
    #stockKitModal.catalog-modal-overlay {
        z-index: 1250;
        padding: 12px;
    }
    #stockKitModal.catalog-modal-overlay.open {
        display: flex;
    }
    #stockKitModal .catalog-modal.stock-kit-modal {
        width: min(1240px, 98vw);
        max-width: none;
        max-height: 96vh;
        font-family: inherit;
    }
    #stockKitModal .catalog-modal-body.stock-kit-modal__body {
        padding: 18px 22px 22px;
        overflow-y: auto;
        max-height: calc(96vh - 130px);
        display: flex;
        flex-direction: column;
        gap: 16px;
        background: #f8fafc;
    }
    #stockKitModal .stock-kit-meta-card,
    #stockKitModal .stock-kit-search-card,
    #stockKitModal .stock-kit-table-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 18px 20px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    }
    #stockKitModal .stock-kit-meta-grid {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr;
        gap: 14px 16px;
        align-items: end;
    }
    #stockKitModal .stock-kit-field--wide {
        grid-column: span 1;
    }
    @media (max-width: 960px) {
        #stockKitModal .stock-kit-meta-grid {
            grid-template-columns: 1fr;
        }
        #stockKitModal .stock-kit-field--wide { grid-column: 1; }
    }
    #stockKitModal .stock-kit-label {
        display: block;
        font-size: 14px;
        font-weight: 700;
        color: #334155;
        margin-bottom: 8px;
    }
    #stockKitModal .stock-kit-input {
        width: 100%;
        box-sizing: border-box;
        padding: 12px 14px;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        font-size: 15px;
        line-height: 1.4;
        font-family: inherit;
        color: #0f172a;
        background: #fff;
    }
    #stockKitModal .stock-kit-input:focus {
        outline: none;
        border-color: #7c3aed;
        box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.15);
    }
    #stockKitModal .stock-kit-input--search {
        font-size: 16px;
        padding: 14px 16px;
        border-width: 2px;
        border-color: #a78bfa;
        background: #faf5ff;
    }
    #stockKitModal .stock-kit-check {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
        font-weight: 600;
        color: #475569;
        cursor: pointer;
        padding: 12px 0;
    }
    #stockKitModal .stock-kit-check input {
        width: 18px;
        height: 18px;
    }
    #stockKitModal .stock-kit-search-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 12px;
    }
    #stockKitModal .stock-kit-search-head h4 {
        margin: 0;
        font-size: 16px;
        font-weight: 800;
        color: #1e293b;
    }
    #stockKitModal .stock-kit-search-badge {
        font-size: 13px;
        font-weight: 700;
        color: #7c3aed;
        background: #f3e8ff;
        padding: 6px 12px;
        border-radius: 999px;
    }
    #stockKitModal .stock-kit-item-results {
        min-height: 220px;
        max-height: 360px;
        margin-top: 12px;
        overflow-y: auto;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        background: #fff;
    }
    #stockKitModal .stock-kit-item-result {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        width: 100%;
        text-align: right;
        padding: 14px 16px;
        border: none;
        border-bottom: 1px solid #f1f5f9;
        background: #fff;
        cursor: pointer;
        font-family: inherit;
        transition: background 0.12s;
    }
    #stockKitModal .stock-kit-item-result:hover,
    #stockKitModal .stock-kit-item-result:focus {
        background: #eff6ff;
        outline: none;
    }
    #stockKitModal .stock-kit-item-result.is-added {
        background: #f8fafc;
        opacity: 0.7;
    }
    #stockKitModal .stock-kit-item-result__name {
        display: block;
        font-weight: 800;
        font-size: 15px;
        color: #0f172a;
        line-height: 1.45;
    }
    #stockKitModal .stock-kit-item-result__code {
        display: block;
        font-family: ui-monospace, monospace;
        font-size: 13px;
        color: #64748b;
        direction: ltr;
        text-align: right;
        margin-bottom: 2px;
    }
    #stockKitModal .stock-kit-item-result__page {
        display: block;
        font-size: 12px;
        color: #94a3b8;
        margin-top: 2px;
    }
    #stockKitModal .stock-kit-item-result__add {
        flex-shrink: 0;
        font-size: 13px;
        font-weight: 800;
        color: #2563eb;
        white-space: nowrap;
        padding: 8px 12px;
        background: #eff6ff;
        border-radius: 8px;
    }
    #stockKitModal .stock-kit-components-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
    }
    #stockKitModal .stock-kit-components-head h4 {
        margin: 0;
        font-size: 16px;
        font-weight: 800;
        color: #1e293b;
    }
    #stockKitModal .stock-kit-components-count {
        font-size: 14px;
        font-weight: 700;
        color: #64748b;
    }
    #stockKitModal .stock-kit-components-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 15px;
    }
    #stockKitModal .stock-kit-components-table th {
        background: #f1f5f9;
        padding: 12px 14px;
        text-align: right;
        font-weight: 800;
        color: #475569;
        border-bottom: 2px solid #e2e8f0;
    }
    #stockKitModal .stock-kit-components-table td {
        padding: 10px 14px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
        color: #0f172a;
    }
    #stockKitModal .stock-kit-qty-input {
        width: 80px;
        padding: 8px 10px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        text-align: center;
        font-size: 15px;
    }
    #stockKitModal .stock-kit-empty-cell,
    #stockKitsPanel .empty-cell {
        text-align: center;
        color: #94a3b8;
        padding: 28px 16px !important;
        font-size: 14px;
    }
    #stockKitModal .stock-kit-results-empty,
    #stockKitModal .stock-kit-results-loading {
        padding: 40px 20px;
        text-align: center;
        color: #64748b;
        font-size: 15px;
        line-height: 1.7;
    }
    #stockKitModal .stock-kit-results-error {
        padding: 24px 20px;
        text-align: center;
        color: #b91c1c;
        font-size: 14px;
        line-height: 1.6;
    }
    #stockKitModal .catalog-form-error {
        margin-top: 4px;
        padding: 12px 14px;
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 10px;
        color: #b91c1c;
        font-size: 14px;
        font-weight: 600;
    }
</style>
