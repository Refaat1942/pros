<div class="section-view" id="section-stock-uom-settings">
    <div class="panel">
        <div class="panel-header">
            <h3>📐 وحدات التوريد والمخزن — قوالب التحويل</h3>
        </div>
        <p class="stock-uom-settings-hint">
            القوالب الافتراضية (ورقة → سم²، علبة → قطع، …) مدمجة في النظام. يمكنك إضافة قوالب مخصصة بمفتاح لاتيني (مثل <code>sheet_custom</code>) وتُستخدم في بطاقة الصنف.
            <strong>وحدة المخزن</strong> في الكتالوج = وحدة الصرف والارتجاع وWAC؛ <strong>وحدة التوريد</strong> = ما يُسجَّل في فاتورة الاستلام.
        </p>
        <div id="stockUomSettingsWrap" class="stock-uom-settings-wrap"></div>
        <div id="stockUomSettingsError" class="stock-uom-settings-error" style="display:none;"></div>
        <div class="stock-uom-settings-actions">
            <button type="button" class="btn-action" id="btnAddStockUomProfile">➕ قالب مخصص</button>
            <button type="button" class="btn-action success" id="btnSaveStockUomSettings">💾 حفظ الإعدادات</button>
        </div>
    </div>
</div>

<style>
    #section-stock-uom-settings .stock-uom-settings-hint {
        padding: 0 16px 12px;
        margin: 0;
        color: var(--text-muted);
        font-size: 13px;
        line-height: 1.65;
    }
    .stock-uom-settings-wrap { padding: 16px; display: flex; flex-direction: column; gap: 16px; }
    .stock-uom-profile-card {
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 12px;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 10px;
        background: var(--surface);
    }
    .stock-uom-profile-card.is-builtin { opacity: 0.85; background: rgba(0,0,0,0.02); }
    .stock-uom-settings-error { color: #dc2626; padding: 8px 16px; }
    .stock-uom-settings-actions { padding: 0 16px 16px; display: flex; gap: 10px; flex-wrap: wrap; }
</style>

<script>
window.__STOCK_UOM_BUILTIN = @json($stock_uom_builtin_profiles ?? []);
window.__STOCK_UOM_CUSTOM = @json($stock_uom_custom_profiles ?? []);
window.__STOCK_UOM_SUGGESTIONS = @json($supply_unit_suggestions ?? []);
</script>
<script src="{{ asset('assets/js/pages/stock-uom-settings.js') }}?v={{ @filemtime(public_path('assets/js/pages/stock-uom-settings.js')) ?: 1 }}"></script>
