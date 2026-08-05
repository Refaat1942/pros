<div class="panel" id="stockKitsPanel">
    <div class="panel-header">
        <h3>🧩 أطقم جاهزة ومخصصات</h3>
        <button type="button" class="btn-add-rank" id="btnAddStockKit">➕ إضافة طقم</button>
    </div>
    <p class="catalog-table-hint" style="margin:12px 16px 0;">
        حدّد <strong>مجموعة التوصيف</strong> (مثل ركبة) — عند اختيارها في التوصيف تظهر مخصصاتها فقط في المعدلات تحت نفس المجموعة.
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
                    <th>المجموعة</th>
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

<div class="panel" id="stockKitGroupsPanel" style="margin-top:16px;">
    <div class="panel-header">
        <h3>🏷️ مجموعات التوصيف (قوالب الجروبينج)</h3>
        <button type="button" class="btn-add-rank" id="btnAddSpecGroup">➕ مجموعة جديدة</button>
    </div>
    <p class="catalog-table-hint" style="margin:12px 16px 0;">
        المجموعات تربط التشخيص (مثل ركبة) بالأطقم والمخصصات في التوصيف والمعدلات. لا تحذف مجموعة مرتبطة بأطقم.
    </p>
    <div class="panel-body">
        <table>
            <thead>
                <tr>
                    <th>المفتاح</th>
                    <th>الاسم</th>
                    <th>النوع الافتراضي</th>
                    <th>كلمات التعرف</th>
                    <th style="width:140px">إجراء</th>
                </tr>
            </thead>
            <tbody id="stockKitGroupsTable"></tbody>
        </table>
    </div>
</div>

<div class="catalog-modal-overlay" id="stockKitGroupModal" role="dialog" aria-modal="true" hidden>
    <div class="catalog-modal catalog-form-modal stock-kit-group-modal" onclick="event.stopPropagation()">
        <div class="catalog-modal-header">
            <div>
                <h3 id="stockKitGroupModalTitle">➕ مجموعة توصيف</h3>
                <div class="modal-code">ربط التشخيص (مثل ركبة) بالأطقم في التوصيف والمعدلات</div>
            </div>
            <button type="button" class="catalog-modal-close" id="closeStockKitGroupModal" aria-label="إغلاق">&times;</button>
        </div>
        <div class="catalog-modal-body stock-kit-group-modal__body">
            <input type="hidden" id="stockKitGroupPreviousKey">
            <div class="stock-kit-group-form">
                <div class="stock-kit-group-row stock-kit-group-row--split">
                    <div class="stock-kit-group-field stock-kit-group-field--icon">
                        <label class="stock-kit-group-label" for="stockKitGroupIcon">أيقونة</label>
                        <input type="text" id="stockKitGroupIcon" class="stock-kit-group-input stock-kit-group-input--icon" maxlength="4" placeholder="🦵">
                    </div>
                    <div class="stock-kit-group-field stock-kit-group-field--key">
                        <label class="stock-kit-group-label" for="stockKitGroupKey">المفتاح (إنجليزي)</label>
                        <input type="text" id="stockKitGroupKey" class="stock-kit-group-input" placeholder="knee" dir="ltr" spellcheck="false">
                    </div>
                </div>
                <div class="stock-kit-group-field">
                    <label class="stock-kit-group-label" for="stockKitGroupLabel">اسم المجموعة *</label>
                    <input type="text" id="stockKitGroupLabel" class="stock-kit-group-input" placeholder="ركبة">
                </div>
                <div class="stock-kit-group-field">
                    <label class="stock-kit-group-label" for="stockKitGroupDefaultType">النوع الافتراضي للطقم</label>
                    <select id="stockKitGroupDefaultType" class="stock-kit-group-input">
                        <option value="assembly">طقم جاهز</option>
                        <option value="accessory">مخصصات</option>
                    </select>
                </div>
                <div class="stock-kit-group-field">
                    <label class="stock-kit-group-label" for="stockKitGroupKeywords">كلمات التعرف (مفصولة بفاصلة)</label>
                    <input type="text" id="stockKitGroupKeywords" class="stock-kit-group-input" placeholder="ركبة, ركبه, knee, فخذ">
                    <p class="stock-kit-group-hint">تُستخدم لمطابقة التشخيص تلقائياً مع المجموعة في التوصيف.</p>
                </div>
            </div>
            <div id="stockKitGroupError" class="catalog-form-error" style="display:none;"></div>
        </div>
        <div class="catalog-modal-footer">
            <button type="button" class="btn-action" id="cancelStockKitGroupModal">إلغاء</button>
            <button type="button" class="btn-action success" id="saveStockKitGroupBtn">💾 حفظ</button>
        </div>
    </div>
</div>

<div class="catalog-modal-overlay" id="stockKitModal" role="dialog" aria-modal="true" hidden>
    <div class="catalog-modal catalog-form-modal stock-kit-modal" onclick="event.stopPropagation()">
        <div class="catalog-modal-header">
            <div>
                <h3 id="stockKitModalTitle">➕ طقم جديد</h3>
                <div class="modal-code" id="stockKitModalSubtitle">اختر مجموعة التوصيف ثم ابحث عن المكوّنات في المربع البنفسجي</div>
            </div>
            <button type="button" class="catalog-modal-close" id="closeStockKitModal" aria-label="إغلاق">&times;</button>
        </div>
        <div class="catalog-modal-body stock-kit-modal__body">
            <input type="hidden" id="stockKitId">

            <section class="stock-kit-meta-card">
                <div class="stock-kit-templates-head">
                    <span class="stock-kit-label">قالب سريع — مجموعة التوصيف</span>
                    <div class="stock-kit-template-chips" id="stockKitTemplateChips"></div>
                </div>
                <div class="stock-kit-meta-grid">
                    <div class="stock-kit-field">
                        <label class="stock-kit-label" for="stockKitSpecGroup">مجموعة التوصيف *</label>
                        <select id="stockKitSpecGroup" class="stock-kit-input" required>
                            <option value="">— اختر المجموعة —</option>
                            @foreach ($stock_kit_groups ?? [] as $group)
                                <option value="{{ $group['key'] }}">{{ $group['icon'] ?? '📦' }} {{ $group['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="stock-kit-field stock-kit-field--wide">
                        <label class="stock-kit-label" for="stockKitName">اسم الطقم *</label>
                        <input type="text" id="stockKitName" class="stock-kit-input" maxlength="255" placeholder="مثال: طقم ركبة — ميكانيكي">
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
    #stockKitGroupModal.catalog-modal-overlay {
        z-index: 1260;
        padding: 12px;
    }
    #stockKitGroupModal.catalog-modal-overlay.open {
        display: flex;
    }
    #stockKitGroupModal .catalog-modal.stock-kit-group-modal {
        width: min(560px, 96vw);
        max-width: none;
        font-family: inherit;
    }
    #stockKitGroupModal .catalog-modal-body.stock-kit-group-modal__body {
        padding: 20px 24px 24px;
        background: #f8fafc;
    }
    #stockKitGroupModal .stock-kit-group-form {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    #stockKitGroupModal .stock-kit-group-row--split {
        display: grid;
        grid-template-columns: 96px 1fr;
        gap: 12px;
        align-items: end;
    }
    #stockKitGroupModal .stock-kit-group-field {
        display: flex;
        flex-direction: column;
        gap: 8px;
        min-width: 0;
    }
    #stockKitGroupModal .stock-kit-group-label {
        display: block;
        font-size: 14px;
        font-weight: 700;
        color: #334155;
        line-height: 1.3;
    }
    #stockKitGroupModal .stock-kit-group-input {
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
    #stockKitGroupModal .stock-kit-group-input--icon {
        text-align: center;
        font-size: 22px;
        padding: 8px 10px;
    }
    #stockKitGroupModal .stock-kit-group-input:focus {
        outline: none;
        border-color: #7c3aed;
        box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.15);
    }
    #stockKitGroupModal .stock-kit-group-input:disabled {
        background: #f1f5f9;
        color: #64748b;
        cursor: not-allowed;
    }
    #stockKitGroupModal .stock-kit-group-hint {
        margin: 0;
        font-size: 12px;
        color: #64748b;
        line-height: 1.5;
    }
    #stockKitGroupModal .catalog-form-error {
        margin-top: 14px;
        padding: 12px 14px;
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 10px;
        color: #b91c1c;
        font-size: 14px;
        font-weight: 600;
    }
    @media (max-width: 480px) {
        #stockKitGroupModal .stock-kit-group-row--split {
            grid-template-columns: 1fr;
        }
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
        grid-template-columns: 1fr 2fr 1fr;
        gap: 14px 16px;
        align-items: end;
    }
    #stockKitModal .stock-kit-templates-head {
        margin-bottom: 14px;
        padding-bottom: 14px;
        border-bottom: 1px dashed #e2e8f0;
    }
    #stockKitModal .stock-kit-template-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 8px;
    }
    #stockKitModal .stock-kit-template-chip {
        border: 1px solid #c4b5fd;
        background: #faf5ff;
        color: #6d28d9;
        border-radius: 999px;
        padding: 8px 14px;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        font-family: inherit;
    }
    #stockKitModal .stock-kit-template-chip:hover,
    #stockKitModal .stock-kit-template-chip.is-active {
        background: #7c3aed;
        color: #fff;
        border-color: #7c3aed;
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

<script>
    window.__STOCK_KIT_GROUPS__ = @json($stock_kit_groups ?? []);
</script>
