<div class="panel" id="stockKitsPanel">
    <div class="panel-header">
        <h3>🧩 أطقم جاهزة ومخصصات</h3>
        <button type="button" class="btn-add-rank" id="btnAddStockKit">➕ إضافة طقم</button>
    </div>
    <p class="catalog-table-hint" style="margin:12px 16px 0;">
        الطقم الجاهز يُختار في التوصيف أو المعدلات فيُفكّك تلقائياً إلى مكوّناته. «مخصصات» = مجموعة إكسسوارات جاهزة بكمياتها.
    </p>
    <div class="data-toolbar">
        <input type="text" id="stockKitSearch" placeholder="🔍 بحث...">
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
    <div class="catalog-modal" style="max-width:720px;" onclick="event.stopPropagation()">
        <div class="catalog-modal-header">
            <h3 id="stockKitModalTitle">➕ طقم جديد</h3>
            <button type="button" class="catalog-modal-close" id="closeStockKitModal" aria-label="إغلاق">&times;</button>
        </div>
        <div class="catalog-modal-body">
            <input type="hidden" id="stockKitId">
            <div class="form-group" style="margin-bottom:12px;">
                <label for="stockKitName">اسم الطقم *</label>
                <input type="text" id="stockKitName" class="form-control" maxlength="255" placeholder="مثال: طقم ركبة كاملة">
            </div>
            <div class="form-group" style="margin-bottom:12px;">
                <label for="stockKitType">النوع</label>
                <select id="stockKitType" class="form-control">
                    <option value="assembly">طقم جاهز (تجميع)</option>
                    <option value="accessory">مخصصات</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:12px;">
                <label for="stockKitDescription">وصف</label>
                <textarea id="stockKitDescription" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-group" style="margin-bottom:12px;">
                <label><input type="checkbox" id="stockKitActive" checked> نشط</label>
            </div>
            <div class="form-group">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <label style="margin:0;">المكوّنات *</label>
                    <button type="button" class="btn-action" id="btnAddKitComponent">➕ مكوّن</button>
                </div>
                <div id="stockKitComponents"></div>
            </div>
            <div id="stockKitError" class="catalog-form-error" style="display:none;"></div>
        </div>
        <div class="catalog-modal-footer">
            <button type="button" class="btn-action" id="cancelStockKitModal">إلغاء</button>
            <button type="button" class="btn-action success" id="saveStockKitBtn">💾 حفظ</button>
        </div>
    </div>
</div>
