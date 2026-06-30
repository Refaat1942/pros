@php
    $categories = collect($stock_categories ?? []);
@endphp
<div class="section-view stock-categories-page" id="section-stock-categories" data-page-mode="1">
    <div class="panel">
        <div class="panel-header">
            <h3>🏷️ أقسام الأصناف</h3>
            <div style="display:flex;align-items:center;gap:10px;">
                <button type="button" class="btn-action primary" id="btnAddStockCategory">➕ قسم جديد</button>
                <span class="badge">{{ $categories->count() }} قسم</span>
            </div>
        </div>

        <p class="stock-categories-intro">
            عرّف لكل قسم الحقول الخاصة به (نص، رقم، قائمة، …). عند إضافة صنف في
            <a href="{{ route('admin.catalog') }}">الأصناف والأسعار</a> يختار المستخدم القسم وتظهر حقوله تلقائياً.
        </p>

        <div class="stock-categories-layout">
            <div class="stock-categories-list-wrap">
                <h4 class="stock-categories-subtitle">الأقسام المسجّلة</h4>
                <div id="stockCategoriesList"></div>
            </div>

            <div class="stock-categories-editor-wrap">
                <div id="stockCategoryEditPanel" class="stock-categories-editor" style="display:none;">
                    <div class="stock-categories-editor-card">
                        <h4 class="stock-categories-subtitle" id="stockCategoryEditTitle">قسم جديد</h4>
                        <input type="hidden" id="editStockCategoryId">

                        <div class="sc-form-group">
                            <label class="sc-form-label" for="editStockCategoryName">اسم القسم <span class="sc-form-required">*</span></label>
                            <input type="text" id="editStockCategoryName" class="sc-form-control" maxlength="100" placeholder="مثال: مفاصل صناعية">
                        </div>

                        <div class="stock-categories-fields-section">
                            <div class="stock-categories-fields-head">
                                <div>
                                    <strong class="stock-categories-fields-title">حقول القسم</strong>
                                    <p class="stock-categories-fields-hint">حدّد الخصائص التي تظهر عند إضافة صنف لهذا القسم</p>
                                </div>
                                <button type="button" class="btn-action primary" id="btnAddCategoryField">+ حقل جديد</button>
                            </div>
                            <div id="stockCategoryFieldsBuilder" class="stock-categories-fields-builder"></div>
                        </div>

                        <div id="stockCategoryEditError" class="sc-form-error" style="display:none;"></div>
                        <div class="stock-categories-editor-actions">
                            <button type="button" class="btn-action" id="btnCancelStockCategory">إلغاء</button>
                            <button type="button" class="btn-action success" id="btnSaveStockCategory">💾 حفظ القسم</button>
                        </div>
                    </div>
                </div>
                <div id="stockCategoryEditPlaceholder" class="stock-categories-placeholder">
                    <p>اختر قسماً من القائمة للتعديل، أو اضغط «قسم جديد».</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* ── Page layout ── */
    .stock-categories-intro {
        margin: 0;
        padding: 12px 24px;
        font-size: 13px;
        color: var(--text-muted);
        border-bottom: 1px solid var(--border);
        line-height: 1.7;
    }
    .stock-categories-intro a { color: var(--primary); font-weight: 700; }
    .stock-categories-layout {
        display: grid;
        grid-template-columns: minmax(280px, 320px) 1fr;
        gap: 0;
        min-height: 480px;
    }
    @media (max-width: 900px) {
        .stock-categories-layout { grid-template-columns: 1fr; }
    }
    .stock-categories-list-wrap {
        padding: 20px;
        border-left: 1px solid var(--border);
        background: #f8fafc;
    }
    .stock-categories-editor-wrap {
        padding: 20px 28px;
        background: #fff;
    }
    .stock-categories-subtitle {
        margin: 0 0 18px;
        font-size: 16px;
        font-weight: 800;
        color: var(--secondary);
    }
    .stock-categories-editor-card {
        max-width: 780px;
    }
    .stock-categories-fields-section {
        margin-top: 20px;
        padding: 16px;
        background: #f8fafc;
        border: 1px solid var(--border);
        border-radius: 14px;
    }
    .stock-categories-fields-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px;
        margin-bottom: 14px;
    }
    .stock-categories-fields-title {
        display: block;
        font-size: 14px;
        font-weight: 800;
        color: var(--secondary);
        margin-bottom: 4px;
    }
    .stock-categories-fields-hint {
        margin: 0;
        font-size: 12px;
        color: var(--text-muted);
        line-height: 1.5;
    }
    .stock-categories-fields-builder { display: flex; flex-direction: column; gap: 12px; }
    .stock-categories-editor-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 20px;
        padding-top: 16px;
        border-top: 1px solid var(--border);
    }
    .stock-categories-placeholder {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 320px;
        color: var(--text-muted);
        font-size: 14px;
        text-align: center;
        padding: 32px;
        border: 2px dashed #e2e8f0;
        border-radius: 16px;
        background: #f8fafc;
    }

    /* ── Shared form controls (scoped) ── */
    .stock-categories-page .sc-form-group {
        margin-bottom: 0;
    }
    .stock-categories-page .sc-form-label {
        display: block;
        font-size: 13px;
        font-weight: 700;
        color: var(--secondary);
        margin-bottom: 7px;
    }
    .stock-categories-page .sc-form-required {
        color: #dc2626;
    }
    .stock-categories-page .sc-form-control {
        width: 100%;
        padding: 11px 14px;
        border: 1px solid #d1d5db;
        border-radius: 10px;
        font-family: 'Tajawal', sans-serif;
        font-size: 14px;
        color: var(--secondary);
        background: #fff;
        transition: border-color 0.15s, box-shadow 0.15s;
        box-sizing: border-box;
    }
    .stock-categories-page .sc-form-control:hover {
        border-color: #9ca3af;
    }
    .stock-categories-page .sc-form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.12);
    }
    .stock-categories-page select.sc-form-control {
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: left 14px center;
        padding-left: 36px;
    }
    .stock-categories-page .sc-form-error {
        margin-top: 12px;
        padding: 10px 14px;
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 10px;
        color: #dc2626;
        font-size: 13px;
    }

    /* ── Category list ── */
    .stock-categories-page .stock-cat-row.is-active {
        border-color: var(--primary);
        background: rgba(217, 119, 6, 0.06);
        box-shadow: 0 0 0 1px rgba(217, 119, 6, 0.15);
    }
    .stock-cat-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 13px 15px;
        border: 1px solid var(--border);
        border-radius: 12px;
        margin-bottom: 8px;
        background: #fff;
        transition: border-color 0.15s, background 0.15s, box-shadow 0.15s;
        cursor: default;
    }
    .stock-cat-row:hover {
        border-color: #cbd5e1;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    .stock-cat-row__name {
        display: block;
        font-size: 14px;
        font-weight: 700;
        margin-bottom: 2px;
    }
    .stock-cat-row__meta {
        font-size: 11px;
        color: var(--text-muted);
    }
    .stock-cat-row__actions {
        display: flex;
        gap: 6px;
        flex-shrink: 0;
    }

    /* ── Field builder card ── */
    .field-builder-empty {
        margin: 0;
        padding: 28px 20px;
        font-size: 13px;
        color: var(--text-muted);
        text-align: center;
        border: 2px dashed #e2e8f0;
        border-radius: 12px;
        background: #fff;
    }
    .field-builder-row {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .field-builder-row__toolbar {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        background: linear-gradient(to bottom, #f8fafc, #f1f5f9);
        border-bottom: 1px solid #e2e8f0;
    }
    .field-builder-row__index {
        font-size: 12px;
        font-weight: 800;
        color: var(--primary);
        background: rgba(217, 119, 6, 0.1);
        padding: 4px 10px;
        border-radius: 999px;
        flex-shrink: 0;
    }
    .field-builder-row__type-badge {
        font-size: 11px;
        font-weight: 700;
        color: var(--text-muted);
        background: #fff;
        border: 1px solid #e2e8f0;
        padding: 3px 9px;
        border-radius: 999px;
    }
    .field-builder-row__toolbar-spacer { flex: 1; }
    .field-builder-row__required-toggle {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        font-weight: 600;
        color: var(--text-muted);
        cursor: pointer;
        user-select: none;
        padding: 4px 10px;
        border-radius: 8px;
        transition: background 0.15s;
    }
    .field-builder-row__required-toggle:hover { background: rgba(0,0,0,0.04); }
    .field-builder-row__required-toggle input { width: 15px; height: 15px; accent-color: var(--primary); cursor: pointer; }
    .field-builder-row__remove {
        padding: 5px 12px !important;
        font-size: 12px !important;
        flex-shrink: 0;
    }
    .field-builder-row__body {
        display: grid;
        grid-template-columns: 1fr 200px;
        gap: 14px;
        padding: 16px 14px;
    }
    @media (max-width: 560px) {
        .field-builder-row__body { grid-template-columns: 1fr; }
    }
    .field-builder-row__mini-label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        color: var(--secondary);
        margin-bottom: 6px;
    }

    /* ── Type-specific panels ── */
    .fb-panel {
        padding: 0 14px 14px;
        border-top: 1px solid #f1f5f9;
        margin-top: 0;
        padding-top: 14px;
    }
    .field-builder-row__bounds {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    .field-builder-row__color-preview {
        display: flex;
        gap: 14px;
        align-items: flex-start;
        padding: 12px;
        background: #f8fafc;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
    }
    .fb-color-swatch {
        width: 52px;
        height: 52px;
        border-radius: 12px;
        border: 2px solid #e2e8f0;
        flex-shrink: 0;
        box-shadow: inset 0 0 0 1px rgba(0,0,0,0.06);
    }
    .field-builder-row__color-copy strong {
        display: block;
        font-size: 13px;
        margin-bottom: 4px;
    }
    .field-builder-row__color-copy p {
        margin: 0 0 10px;
        font-size: 12px;
        color: var(--text-muted);
        line-height: 1.5;
    }
    .field-builder-row__color-picker label {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
    }
    .field-builder-row__color-picker input[type="color"] {
        width: 56px;
        height: 40px;
        padding: 2px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        cursor: pointer;
        background: #fff;
    }
    .fb-color-value {
        font-size: 12px;
        padding: 4px 10px;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        direction: ltr;
    }

    /* ── Options builder ── */
    .fb-options-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 10px;
    }
    .fb-options-count {
        font-size: 11px;
        font-weight: 700;
        color: var(--primary);
        padding: 3px 10px;
        background: rgba(217, 119, 6, 0.1);
        border-radius: 999px;
    }
    .fb-options-list {
        display: flex;
        flex-direction: column;
        gap: 7px;
        margin-bottom: 10px;
        max-height: 200px;
        overflow-y: auto;
    }
    .fb-options-empty {
        margin: 0;
        padding: 16px;
        font-size: 12px;
        color: var(--text-muted);
        text-align: center;
        background: #fff;
        border: 1px dashed #e2e8f0;
        border-radius: 10px;
    }
    .fb-option-row {
        display: grid;
        grid-template-columns: 32px 1fr 36px;
        gap: 8px;
        align-items: center;
    }
    .fb-option-num {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 800;
        color: var(--text-muted);
        background: #f1f5f9;
        border-radius: 8px;
    }
    .fb-option-remove {
        width: 36px !important;
        height: 36px !important;
        padding: 0 !important;
        font-size: 16px !important;
        line-height: 1 !important;
    }
    .fb-options-add {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 8px;
        align-items: center;
        padding: 10px 12px;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
    }
    .fb-options-add-btn {
        white-space: nowrap;
        padding: 9px 16px !important;
    }
</style>

<script>
window.__STOCK_CATEGORIES = @json($categories->values());
</script>
<script src="{{ asset('assets/js/pages/catalog-sections.js') }}?v={{ filemtime(public_path('assets/js/pages/catalog-sections.js')) }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.CatalogSections && window.__STOCK_CATEGORIES) {
        window.CatalogSections.init(window.__STOCK_CATEGORIES, { pageMode: true });
    }
});
</script>
