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
    <div class="catalog-modal stock-kit-modal" onclick="event.stopPropagation()">
        <div class="catalog-modal-header">
            <div>
                <h3 id="stockKitModalTitle">➕ طقم جديد</h3>
                <div class="modal-code" id="stockKitModalSubtitle">ابحث عن الأصناف وأضفها للطقم — مثل شاشة التوصيف</div>
            </div>
            <button type="button" class="catalog-modal-close" id="closeStockKitModal" aria-label="إغلاق">&times;</button>
        </div>
        <div class="catalog-modal-body stock-kit-modal__body">
            <input type="hidden" id="stockKitId">

            <div class="stock-kit-modal__grid">
                <section class="catalog-form-card">
                    <h4 class="catalog-form-card__title">📋 بيانات الطقم</h4>
                    <div class="catalog-form-grid catalog-form-grid--basic">
                        <div class="catalog-form-grid__full">
                            <label class="catalog-form-label" for="stockKitName">اسم الطقم *</label>
                            <input type="text" id="stockKitName" class="catalog-form-input" maxlength="255" placeholder="مثال: طقم ركبة كاملة">
                        </div>
                        <div>
                            <label class="catalog-form-label" for="stockKitType">النوع</label>
                            <select id="stockKitType" class="catalog-form-input">
                                <option value="assembly">طقم جاهز (تجميع)</option>
                                <option value="accessory">مخصصات</option>
                            </select>
                        </div>
                        <div>
                            <label class="catalog-form-label catalog-quick-dispense-label" style="margin-top:28px;">
                                <input type="checkbox" id="stockKitActive" checked>
                                <span>نشط — يظهر في التوصيف والمعدلات</span>
                            </label>
                        </div>
                        <div class="catalog-form-grid__full">
                            <label class="catalog-form-label" for="stockKitDescription">وصف (اختياري)</label>
                            <textarea id="stockKitDescription" class="catalog-form-input" rows="2" placeholder="ملاحظات عن الطقم..."></textarea>
                        </div>
                    </div>
                </section>

                <section class="catalog-form-card stock-kit-picker-card">
                    <h4 class="catalog-form-card__title">🔍 إضافة مكوّنات من الكتالوج</h4>
                    <p class="stock-kit-picker-hint">اكتب اسم الصنف أو الكود (مثل: ركبة) — اختر من النتائج، ويمكنك إضافة أكثر من صنف.</p>
                    <input type="search" id="stockKitItemSearch" class="catalog-form-input stock-kit-item-search" placeholder="بحث بالاسم أو الكود أو رقم الصفحة..." autocomplete="off">
                    <div id="stockKitItemResults" class="stock-kit-item-results" role="listbox" aria-label="نتائج البحث"></div>
                </section>
            </div>

            <section class="catalog-form-card stock-kit-components-card">
                <div class="stock-kit-components-head">
                    <h4 class="catalog-form-card__title catalog-form-card__title--inline">📦 مكوّنات الطقم</h4>
                    <span class="stock-kit-components-count" id="stockKitComponentsCount">0 صنف</span>
                </div>
                <div class="stock-kit-components-table-wrap">
                    <table class="stock-kit-components-table">
                        <thead>
                            <tr>
                                <th>الكود</th>
                                <th>اسم الصنف</th>
                                <th>الوحدة</th>
                                <th style="width:100px;">الكمية</th>
                                <th style="width:56px;"></th>
                            </tr>
                        </thead>
                        <tbody id="stockKitComponentsBody">
                            <tr id="stockKitComponentsEmpty">
                                <td colspan="5" class="stock-kit-empty-cell">ابحث عن الأصناف وأضفها من القائمة أعلاه</td>
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
    #stockKitsPanel .stock-kit-modal {
        max-width: min(1100px, 96vw);
        width: 100%;
        max-height: 92vh;
    }
    #stockKitsPanel .stock-kit-modal__body {
        max-height: calc(92vh - 130px);
        overflow-y: auto;
    }
    #stockKitsPanel .stock-kit-modal__grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 16px;
    }
    @media (max-width: 900px) {
        #stockKitsPanel .stock-kit-modal__grid { grid-template-columns: 1fr; }
    }
    #stockKitsPanel .catalog-form-card {
        background: var(--surface, #fff);
        border: 1px solid var(--border, #e5e7eb);
        border-radius: 12px;
        padding: 16px 18px;
    }
    #stockKitsPanel .catalog-form-card__title {
        margin: 0 0 12px;
        font-size: 15px;
        font-weight: 700;
        color: var(--text, #1e293b);
    }
    #stockKitsPanel .catalog-form-card__title--inline { margin-bottom: 0; }
    #stockKitsPanel .catalog-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px 14px;
    }
    #stockKitsPanel .catalog-form-grid__full { grid-column: 1 / -1; }
    #stockKitsPanel .catalog-form-label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 6px;
        color: var(--text-muted, #64748b);
    }
    #stockKitsPanel .catalog-form-input {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--border, #cbd5e1);
        border-radius: 8px;
        font-size: 14px;
        background: #fff;
    }
    #stockKitsPanel .stock-kit-picker-hint {
        margin: 0 0 10px;
        font-size: 13px;
        color: var(--text-muted, #64748b);
        line-height: 1.6;
    }
    #stockKitsPanel .stock-kit-item-search {
        margin-bottom: 10px;
    }
    #stockKitsPanel .stock-kit-item-results {
        max-height: 280px;
        overflow-y: auto;
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 10px;
        background: #f8fafc;
    }
    #stockKitsPanel .stock-kit-item-result {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        width: 100%;
        text-align: right;
        padding: 10px 12px;
        border: none;
        border-bottom: 1px solid #e2e8f0;
        background: transparent;
        cursor: pointer;
        transition: background 0.15s;
    }
    #stockKitsPanel .stock-kit-item-result:hover,
    #stockKitsPanel .stock-kit-item-result:focus {
        background: #eff6ff;
        outline: none;
    }
    #stockKitsPanel .stock-kit-item-result.is-added {
        opacity: 0.55;
        background: #f1f5f9;
    }
    #stockKitsPanel .stock-kit-item-result__meta {
        flex: 1;
        min-width: 0;
    }
    #stockKitsPanel .stock-kit-item-result__code {
        font-family: ui-monospace, monospace;
        font-size: 12px;
        color: #64748b;
        direction: ltr;
        text-align: right;
    }
    #stockKitsPanel .stock-kit-item-result__name {
        font-weight: 700;
        font-size: 14px;
        color: #0f172a;
    }
    #stockKitsPanel .stock-kit-item-result__page {
        font-size: 11px;
        color: #94a3b8;
    }
    #stockKitsPanel .stock-kit-item-result__add {
        flex-shrink: 0;
        font-size: 12px;
        font-weight: 700;
        color: #2563eb;
        white-space: nowrap;
    }
    #stockKitsPanel .stock-kit-components-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 10px;
    }
    #stockKitsPanel .stock-kit-components-count {
        font-size: 13px;
        color: var(--text-muted, #64748b);
        font-weight: 600;
    }
    #stockKitsPanel .stock-kit-components-table-wrap {
        overflow-x: auto;
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 10px;
    }
    #stockKitsPanel .stock-kit-components-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    #stockKitsPanel .stock-kit-components-table th {
        background: #f1f5f9;
        padding: 10px 12px;
        text-align: right;
        font-weight: 700;
        color: #475569;
        border-bottom: 1px solid #e2e8f0;
    }
    #stockKitsPanel .stock-kit-components-table td {
        padding: 8px 12px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    #stockKitsPanel .stock-kit-components-table tr:last-child td { border-bottom: none; }
    #stockKitsPanel .stock-kit-qty-input {
        width: 72px;
        padding: 6px 8px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        text-align: center;
    }
    #stockKitsPanel .stock-kit-empty-cell,
    #stockKitsPanel .empty-cell {
        text-align: center;
        color: var(--text-muted, #94a3b8);
        padding: 24px 12px !important;
    }
    #stockKitsPanel .stock-kit-results-empty {
        padding: 28px 16px;
        text-align: center;
        color: #94a3b8;
        font-size: 13px;
    }
</style>
