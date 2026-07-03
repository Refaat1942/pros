@php
    /** قائمة مُنسّقة من StockCatalogService::formatItem (مصفوفات). */
    $items = collect($stock_items ?? []);
    $categories = collect($stock_categories ?? []);
    $catalogSuppliers = collect($suppliers ?? []);
    $dateFrom = $date_from ?? request()->query('from');
    $dateTo = $date_to ?? request()->query('to');
    $exportUrl = route('admin.catalog.export', array_filter([
        'from' => $dateFrom,
        'to'   => $dateTo,
    ]));
    // $categories = collect($stock_categories ?? []);
@endphp
<div class="section-view" id="section-catalog">
    <div class="panel">
        <div class="panel-header">
            <h3>📦 الأصناف والأسعار</h3>
            <span class="badge">{{ $items->count() }} صنف</span>
        </div>

        <div id="catalogImportStatus" style="display:none;margin:12px 16px;padding:10px 14px;border-radius:8px;font-size:13px;"></div>
        @if (session('status'))
            <div style="margin:12px 16px;padding:10px 14px;background:#dcfce7;border:1px solid #86efac;border-radius:8px;color:#166534;font-size:13px;">
                ✅ {{ session('status') }}
            </div>
        @endif
        @if (!empty(session('import_errors')))
            <div style="margin:12px 16px;padding:10px 14px;background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;color:#92400e;font-size:12px;">
                @foreach (session('import_errors') as $err)
                    <div>⚠️ {{ $err }}</div>
                @endforeach
            </div>
        @endif

        <form method="GET" action="{{ route('admin.catalog') }}" class="reports-date-filter" id="catalogDateFilter" style="margin:12px 16px 0;">
            <label>
                <span>من</span>
                <input type="date" name="from" id="catalogDateFrom" value="{{ $dateFrom }}">
            </label>
            <label>
                <span>إلى</span>
                <input type="date" name="to" id="catalogDateTo" value="{{ $dateTo }}">
            </label>
            <button type="submit" class="btn-action primary">تطبيق الفترة</button>
            @if ($dateFrom || $dateTo)
                <a href="{{ route('admin.catalog') }}" class="btn-action">مسح الفلتر</a>
            @endif
        </form>

        <div class="data-toolbar" style="flex-wrap:wrap;gap:8px;">
            <input type="text" id="catalogSlimSearch" placeholder="🔍 بحث بالصنف أو الكود..." onkeyup="applySlimCatalogFilters()">
            <select id="catalogCategoryFilter" onchange="applySlimCatalogFilters()" style="padding:8px 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;min-width:160px;">
                <option value="">🏷️ كل الأقسام</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat['id'] }}">{{ $cat['name'] }}</option>
                @endforeach
            </select>
            <button type="button" class="btn-action" style="background:var(--primary);color:#fff;border:none;" onclick="openSlimCatalogForm()">➕ إضافة صنف</button>

            <a class="btn-action" href="{{ $exportUrl }}">📊 تصدير Excel</a>

            @can('import-inventory')
                <a class="btn-action" href="{{ route('admin.catalog.template') }}">⬇️ تنزيل القالب</a>
                <form id="catalogImportForm" method="POST" action="{{ route('admin.catalog.import') }}" enctype="multipart/form-data" style="display:inline-flex;">
                    @csrf
                    <input type="file" id="catalogImportFile" name="file" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" class="catalog-file-input" required>
                    <label for="catalogImportFile" class="btn-action success catalog-file-label" id="catalogImportBtn" title="معرف المورد/القسم من تبويبات القالب — والخصائص: انسخ من «خيارات الحقول» مفصولة بـ |">📤 ارفع ملف Excel</label>
                </form>
            @endcan

            <span class="toolbar-count" id="catalogSlimCount">{{ $items->count() }} صنف</span>
        </div>

        <p class="catalog-table-hint">
            💡 لإضافة أكثر من سعر لنفس الصنف في ملف Excel/CSV، اكتب الأسعار في عمود «السعر» مفصولة بعلامة
            <strong>;</strong>
            — مثال: <code dir="ltr">1000;2000;2200</code>
            (أول سعر أساسي والباقي أسعار إضافية). عمود «أعلى سعر» يعرض أعلى قيمة مسجّلة.
        </p>

        <div class="panel-body" style="overflow-x:auto;">
            <table class="catalog-slim-table" id="catalogItemsTable" data-paginate="10" style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="background:var(--surface-2,#f8fafc);">
                        <th style="padding:10px;text-align:right;">الكود</th>
                        <th style="padding:10px;text-align:right;">الصنف</th>
                        <th style="padding:10px;text-align:right;">القسم</th>
                        <th style="padding:10px;text-align:right;">المورد</th>
                        <th style="padding:10px;text-align:center;">الكمية</th>
                        <th style="padding:10px;text-align:center;">سعر التكلفة</th>
                        <th style="padding:10px;text-align:center;">أعلى سعر</th>
                        <th style="padding:10px;text-align:center;">أسعار إضافية</th>
                        <th style="padding:10px;text-align:center;min-width:280px;">إجراء</th>
                    </tr>
                </thead>
                <tbody id="catalogSlimTable">
                    @forelse ($items as $item)
                        <tr class="catalog-slim-row"
                            data-item-id="{{ $item['id'] ?? '' }}"
                            data-search="{{ strtolower(($item['code'] ?? '') . ' ' . ($item['name'] ?? '') . ' ' . ($item['category'] ?? '')) }}"
                            data-category-id="{{ $item['category_id'] ?? '' }}"
                            data-filter-hidden="0"
                            data-item="{{ json_encode($item, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) }}"
                            style="border-top:1px solid var(--border);">
                            <td style="padding:8px;direction:ltr;text-align:right;"><strong>{{ $item['code'] ?? '' }}</strong></td>
                            <td style="padding:8px;">{{ $item['name'] ?? '' }}</td>
                            <td style="padding:8px;color:var(--text-muted);">{{ $item['category'] ?? '—' }}</td>
                            <td style="padding:8px;color:var(--text-muted);font-size:12px;">
                                @if (!empty($item['suppliers']))
                                    {{ collect($item['suppliers'])->pluck('name')->join('، ') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td style="padding:8px;text-align:center;">{{ (int) ($item['qty'] ?? 0) }}</td>
                            <td style="padding:10px;text-align:center;" class="catalog-price-cell">{{ number_format((float) ($item['price'] ?? 0), 2) }}</td>
                            <td style="padding:10px;text-align:center;" class="catalog-price-cell">{{ number_format((float) ($item['highest_price'] ?? $item['price'] ?? 0), 2) }}</td>
                            <td style="padding:8px;text-align:center;color:var(--text-muted);">
                                @if (!empty($item['prices']))
                                    {{ count($item['prices']) }} سعر
                                @else
                                    —
                                @endif
                            </td>
                            <td style="padding:10px;text-align:center;white-space:nowrap;">
                                <button type="button" class="btn-action" onclick="viewSlimCatalog(this)">👁️ عرض</button>
                                <button type="button" class="btn-action" onclick="editSlimCatalog(this)">✏️ تعديل</button>
                                @can('print-barcode')
                                    <a class="btn-action" target="_blank" href="{{ route('admin.catalog.labels', $item['id']) }}">🏷️ باركود</a>
                                @endcan
                                <button type="button" class="btn-action danger" onclick="deleteSlimCatalog({{ $item['id'] }}, {{ json_encode($item['name'] ?? '') }})">🗑️</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:24px;">لا توجد أصناف — أضف صنفاً أو ارفع ملف Excel.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="catalog-modal-overlay" id="catalogFormModal" role="dialog" aria-modal="true" hidden>
    <div class="catalog-modal catalog-form-modal" onclick="event.stopPropagation()">
        <div class="catalog-modal-header">
            <div>
                <h3 id="catalogFormModalTitle">➕ إضافة صنف</h3>
            </div>
            <button type="button" class="catalog-modal-close" id="catalogFormClose" aria-label="إغلاق">&times;</button>
        </div>
        <div class="catalog-modal-body">
            <input type="hidden" id="slimEditId" value="">
            <input type="hidden" id="slimEditCode" value="">
            <div class="catalog-form-cards">
                <section class="catalog-form-card">
                    <h4 class="catalog-form-card__title">📦 بيانات الصنf</h4>
                    <div class="catalog-form-grid catalog-form-grid--basic">
                <div>
                    <label class="catalog-form-label">كود الصنف / الباركود</label>
                    <input type="text" id="slimCode" placeholder="تلقائي إن تُرك فارغاً" class="catalog-form-input">
                </div>
                <div>
                    <label class="catalog-form-label">اسم الصنف *</label>
                    <input type="text" id="slimName" placeholder="مثال: ركبة هيدروليكية" class="catalog-form-input">
                </div>
                <div>
                    <label class="catalog-form-label">الكمية</label>
                    <input type="number" id="slimQty" min="0" value="0" class="catalog-form-input">
                </div>
                <div>
                    <label class="catalog-form-label">الحد الأدنى للصنف</label>
                    <input type="number" id="slimMinQty" min="0" value="0" class="catalog-form-input" placeholder="مثال: 10">
                </div>
                <div>
                    <label class="catalog-form-label">السعر الأساسي</label>
                    <input type="number" id="slimPrice" min="0" step="0.01" value="0" class="catalog-form-input">
                </div>
                    </div>
                </section>

                <section class="catalog-form-card">
                    <h4 class="catalog-form-card__title">🏭 المورد</h4>
                    <div class="catalog-supplier-picker">
                <label class="catalog-form-label">المورد *</label>
                <div class="catalog-combobox" id="slimSupplierCombobox">
                    <input type="hidden" id="slimSupplierId" value="">
                    <button type="button" class="catalog-combobox__toggle" id="slimSupplierToggle" aria-haspopup="listbox" aria-expanded="false">
                        <span class="catalog-combobox__value is-placeholder" id="slimSupplierLabel">— اختر المورد —</span>
                        <span class="catalog-combobox__arrow" aria-hidden="true">▾</span>
                    </button>
                    <div class="catalog-combobox__dropdown" id="slimSupplierDropdown" hidden>
                        <div class="catalog-combobox__search-wrap">
                            <input type="search" id="slimSupplierSearch" class="catalog-combobox__search" placeholder="ابحث عن المورد..." autocomplete="off">
                        </div>
                        <ul class="catalog-combobox__list" id="slimSupplierList" role="listbox"></ul>
                    </div>
                </div>
                    </div>
                </section>

                <section class="catalog-form-card">
                    <h4 class="catalog-form-card__title">📂 القسم</h4>
                <label class="catalog-form-label" for="slimCategoryId">القسم *</label>
                <select id="slimCategoryId" class="catalog-form-input">
                    <option value="">— اختر القسم —</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat['id'] }}">{{ $cat['name'] }}</option>
                    @endforeach
                </select>
                </section>

                <div id="slimCategoryFieldsWrap" class="catalog-form-card catalog-form-card--attrs" hidden>
                    <h4 class="catalog-form-card__title" id="slimCategoryFieldsHeading">📋 حقول القسم</h4>
                    <div id="slimCategoryFields" class="catalog-attr-cards"></div>
                </div>

                <section class="catalog-form-card">
                    <div class="catalog-extra-prices">
                        <div class="catalog-extra-prices__head">
                            <h4 class="catalog-form-card__title catalog-form-card__title--inline">💰 أسعار إضافية</h4>
                            <button type="button" class="btn-action" onclick="addSlimPriceRow()">+ سعر إضافي</button>
                        </div>
                        <div id="slimExtraPrices" class="catalog-extra-prices__list"></div>
                    </div>
                </section>
            </div>

            <div id="slimCatalogError" class="catalog-form-error" style="display:none;"></div>
        </div>
        <div class="catalog-modal-footer">
            <button type="button" class="btn-action" onclick="closeSlimCatalogForm()">إلغاء</button>
            <button type="button" class="btn-action success" onclick="saveSlimCatalog()">💾 حفظ الصنف</button>
        </div>
    </div>
</div>

<div class="catalog-modal-overlay" id="catalogViewModal" role="dialog" aria-modal="true" hidden>
    <div class="catalog-modal" onclick="event.stopPropagation()">
        <div class="catalog-modal-header">
            <div>
                <h3 id="catalogViewTitle">تفاصيل الصنف</h3>
                <div class="modal-code" id="catalogViewCode"></div>
            </div>
            <button type="button" class="catalog-modal-close" id="catalogViewClose" aria-label="إغلاق">&times;</button>
        </div>
        <div class="catalog-modal-body" id="catalogViewBody"></div>
        <div class="catalog-modal-footer">
            <button type="button" class="btn-action" id="catalogViewCloseBtn">إغلاق</button>
        </div>
    </div>
</div>

<style>
    #section-catalog .catalog-table-hint {
        margin: 0 16px 12px;
        padding: 10px 14px;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 8px;
        color: #1e40af;
        font-size: 13px;
        line-height: 1.7;
    }
    #section-catalog .catalog-table-hint code {
        background: #fff;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 12px;
    }
    #section-catalog .catalog-file-input {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        border: 0;
    }
    #section-catalog .catalog-file-label {
        cursor: pointer;
        margin: 0;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    #section-catalog .catalog-slim-table {
        font-size: 14px;
    }
    #section-catalog .catalog-price-cell {
        font-size: 15px;
        font-weight: 800;
        color: var(--primary-dark, #5b21b6);
        font-variant-numeric: tabular-nums;
    }
    #catalogViewModal.catalog-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.45);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1200;
        padding: 16px;
    }
    #catalogViewModal.catalog-modal-overlay.open {
        display: flex;
    }
    #catalogFormModal.catalog-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.45);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1250;
        padding: 16px;
    }
    #catalogFormModal.catalog-modal-overlay.open {
        display: flex;
    }
    #catalogFormModal .catalog-form-modal {
        width: min(960px, 96vw);
        max-height: 92vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    #catalogFormModal .catalog-modal-body {
        overflow-y: auto;
        padding: 18px 22px;
    }
    .catalog-form-cards {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }
    .catalog-form-card {
        background: #fff;
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 12px;
        padding: 16px 18px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    }
    .catalog-form-card--attrs {
        background: #f8fafc;
    }
    .catalog-form-card__title {
        margin: 0 0 14px;
        font-size: 14px;
        font-weight: 800;
        color: var(--secondary, #334155);
    }
    .catalog-form-card__title--inline {
        margin: 0;
    }
    .catalog-form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
    }
    .catalog-form-grid--basic {
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    }
    @media (min-width: 900px) {
        .catalog-form-grid--basic {
            grid-template-columns: repeat(5, 1fr);
        }
    }
    .catalog-attr-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
    }
    .catalog-attr-card {
        background: #fff;
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 10px;
        padding: 14px;
    }
    .catalog-attr-card__label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 8px;
        color: var(--secondary, #334155);
    }
    .catalog-attr-card__req {
        color: #dc2626;
    }
    .catalog-form-label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 6px;
    }
    .catalog-form-input {
        width: 100%;
        padding: 9px;
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 8px;
        font-family: inherit;
    }
    .catalog-form-hint {
        font-size: 12px;
        color: var(--text-muted, #64748b);
        margin: 6px 0 0;
    }
    .catalog-supplier-picker {
        margin-top: 0;
    }
    .catalog-combobox {
        position: relative;
    }
    .catalog-combobox__toggle {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding: 9px 12px;
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 8px;
        background: #fff;
        font-family: inherit;
        font-size: 14px;
        text-align: right;
        cursor: pointer;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .catalog-combobox__toggle:hover {
        border-color: #cbd5e1;
    }
    .catalog-combobox.is-open .catalog-combobox__toggle,
    .catalog-combobox__toggle:focus {
        outline: none;
        border-color: var(--primary, #2563eb);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }
    .catalog-combobox__value {
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: var(--secondary, #334155);
    }
    .catalog-combobox__value.is-placeholder {
        color: var(--text-muted, #64748b);
    }
    .catalog-combobox__arrow {
        color: var(--text-muted, #64748b);
        font-size: 12px;
        flex-shrink: 0;
    }
    .catalog-combobox__dropdown {
        position: absolute;
        z-index: 50;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        background: #fff;
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
        overflow: hidden;
    }
    .catalog-combobox__search-wrap {
        padding: 8px;
        border-bottom: 1px solid var(--border, #e2e8f0);
        background: #f8fafc;
    }
    .catalog-combobox__search {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 6px;
        font-family: inherit;
        font-size: 13px;
    }
    .catalog-combobox__search:focus {
        outline: none;
        border-color: var(--primary, #2563eb);
    }
    .catalog-combobox__list {
        list-style: none;
        margin: 0;
        padding: 4px 0;
        max-height: 220px;
        overflow-y: auto;
    }
    .catalog-combobox__option {
        display: block;
        width: 100%;
        padding: 9px 12px;
        border: none;
        background: transparent;
        font-family: inherit;
        font-size: 13px;
        text-align: right;
        cursor: pointer;
        color: var(--secondary, #334155);
    }
    .catalog-combobox__option:hover,
    .catalog-combobox__option.is-selected {
        background: #eff6ff;
        color: var(--primary, #2563eb);
    }
    .catalog-combobox__empty {
        padding: 12px;
        font-size: 13px;
        color: var(--text-muted, #64748b);
        text-align: center;
    }
    .catalog-extra-prices {
        margin-top: 0;
    }
    .catalog-extra-prices__head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 10px;
    }
    .catalog-extra-prices__list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .catalog-form-error {
        margin-top: 10px;
        padding: 8px;
        background: #fee2e2;
        border-radius: 8px;
        color: #dc2626;
        font-size: 12px;
    }
    .slim-attr-color {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px 10px;
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 8px;
        background: #f8fafc;
    }
    .slim-attr-color-input {
        width: 56px !important;
        height: 40px;
        padding: 2px !important;
        border: 1px solid var(--border, #e2e8f0) !important;
        border-radius: 8px;
        cursor: pointer;
        flex-shrink: 0;
    }
    .slim-attr-color-value {
        font-size: 13px;
        font-weight: 600;
        font-family: ui-monospace, monospace;
        direction: ltr;
        color: var(--secondary, #334155);
    }
    #catalogViewModal .catalog-modal {
        background: #fff;
        border-radius: 12px;
        width: min(520px, 96vw);
        max-height: 90vh;
        overflow: hidden;
        box-shadow: 0 24px 64px rgba(0,0,0,0.2);
    }
    #catalogViewModal .catalog-modal-header,
    #catalogViewModal .catalog-modal-footer {
        padding: 14px 18px;
        border-bottom: 1px solid var(--border, #e2e8f0);
    }
    #catalogViewModal .catalog-modal-footer {
        border-bottom: none;
        border-top: 1px solid var(--border, #e2e8f0);
        display: flex;
        justify-content: flex-end;
    }
    #catalogViewModal .catalog-modal-body {
        padding: 16px 18px;
        overflow-y: auto;
        max-height: calc(90vh - 130px);
    }
    #catalogViewModal .catalog-detail-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-bottom: 16px;
    }
    #catalogViewModal .catalog-detail-box {
        background: #f8fafc;
        border-radius: 8px;
        padding: 10px 12px;
    }
    #catalogViewModal .catalog-detail-box .dl {
        font-size: 11px;
        color: var(--text-muted, #64748b);
        margin-bottom: 4px;
    }
    #catalogViewModal .catalog-detail-box .dv {
        font-size: 14px;
        font-weight: 700;
    }
    #catalogViewModal .catalog-prices-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    #catalogViewModal .catalog-prices-table th,
    #catalogViewModal .catalog-prices-table td {
        border: 1px solid var(--border, #e2e8f0);
        padding: 8px 10px;
        text-align: center;
    }
    #catalogViewModal .catalog-prices-table th {
        background: #f1f5f9;
        font-weight: 800;
    }
    #catalogViewModal .catalog-prices-table .price-val {
        font-weight: 800;
        font-size: 15px;
        color: var(--primary-dark, #5b21b6);
    }
    #catalogViewModal .catalog-sales-stats-table .stat-zero {
        color: var(--text-muted, #94a3b8);
    }
    #catalogViewModal .catalog-sales-stats-table .stat-hit {
        font-weight: 800;
        color: #15803d;
    }
    #catalogViewModal .catalog-sales-hint {
        font-size: 12px;
        color: var(--text-muted, #64748b);
        margin: 0 0 10px;
    }
</style>

<script>
(function () {
    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    window.applySlimCatalogFilters = function () {
        var term = (document.getElementById('catalogSlimSearch')?.value || '').toLowerCase().trim();
        var catId = document.getElementById('catalogCategoryFilter')?.value || '';
        var visible = 0;
        var total = 0;

        document.querySelectorAll('#catalogSlimTable .catalog-slim-row').forEach(function (row) {
            total++;
            var hay = row.getAttribute('data-search') || '';
            var rowCat = row.getAttribute('data-category-id') || '';
            var matchSearch = !term || hay.indexOf(term) !== -1;
            var matchCat = !catId || rowCat === catId;
            var show = matchSearch && matchCat;
            row.dataset.filterHidden = show ? '0' : '1';
            if (show) visible++;
        });

        var tbody = document.getElementById('catalogSlimTable');
        if (tbody && window.TablePagination && TablePagination.repaginate) {
            TablePagination.repaginate(tbody);
        }

        var countEl = document.getElementById('catalogSlimCount');
        if (countEl) {
            countEl.textContent = (catId || term)
                ? (visible + ' من ' + total + ' صنف')
                : (total + ' صنف');
        }
    };

    window.filterSlimCatalog = function () {
        window.applySlimCatalogFilters();
    };

    window.addSlimPriceRow = function (amount) {
        var box = document.getElementById('slimExtraPrices');
        var row = document.createElement('div');
        row.className = 'slim-price-row';
        row.style.cssText = 'display:flex;gap:8px;align-items:center;';
        row.innerHTML =
            '<input type="number" min="0" step="0.01" class="slim-price-amount" placeholder="السعر" style="flex:1;padding:8px;border:1px solid var(--border);border-radius:8px;">' +
            '<button type="button" class="btn-action danger" onclick="this.closest(\'.slim-price-row\').remove()">×</button>';
        box.appendChild(row);
        if (amount != null) row.querySelector('.slim-price-amount').value = amount;
    };

    var supplierOptions = @json($catalogSuppliers->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values());

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function setSupplierValue(id, name) {
        var hidden = document.getElementById('slimSupplierId');
        var label = document.getElementById('slimSupplierLabel');
        if (hidden) hidden.value = id ? String(id) : '';
        if (label) {
            label.textContent = name || '— اختر المورد —';
            label.classList.toggle('is-placeholder', !name);
        }
        closeSupplierDropdown();
    }

    function renderSupplierList(filter) {
        var list = document.getElementById('slimSupplierList');
        if (!list) return;
        var term = (filter || '').toLowerCase().trim();
        var selectedId = document.getElementById('slimSupplierId')?.value || '';
        var matches = supplierOptions.filter(function (s) {
            return !term || String(s.name).toLowerCase().indexOf(term) !== -1;
        });
        if (!matches.length) {
            list.innerHTML = '<li class="catalog-combobox__empty">لا توجد نتائج</li>';
            return;
        }
        list.innerHTML = matches.map(function (s) {
            var selected = String(s.id) === selectedId ? ' is-selected' : '';
            return '<li><button type="button" class="catalog-combobox__option' + selected + '" data-id="' + s.id + '">' + escapeHtml(s.name) + '</button></li>';
        }).join('');
    }

    function openSupplierDropdown() {
        var combobox = document.getElementById('slimSupplierCombobox');
        var dropdown = document.getElementById('slimSupplierDropdown');
        var toggle = document.getElementById('slimSupplierToggle');
        var search = document.getElementById('slimSupplierSearch');
        if (!dropdown || !toggle) return;
        dropdown.hidden = false;
        combobox?.classList.add('is-open');
        toggle.setAttribute('aria-expanded', 'true');
        if (search) {
            search.value = '';
            renderSupplierList('');
            search.focus();
        }
    }

    function closeSupplierDropdown() {
        var combobox = document.getElementById('slimSupplierCombobox');
        var dropdown = document.getElementById('slimSupplierDropdown');
        var toggle = document.getElementById('slimSupplierToggle');
        if (dropdown) dropdown.hidden = true;
        combobox?.classList.remove('is-open');
        toggle?.setAttribute('aria-expanded', 'false');
    }

    function filterSupplierOptions() {
        var search = document.getElementById('slimSupplierSearch');
        renderSupplierList(search ? search.value : '');
    }

    document.getElementById('slimSupplierToggle')?.addEventListener('click', function () {
        var dropdown = document.getElementById('slimSupplierDropdown');
        if (dropdown && !dropdown.hidden) {
            closeSupplierDropdown();
        } else {
            openSupplierDropdown();
        }
    });

    document.getElementById('slimSupplierSearch')?.addEventListener('input', filterSupplierOptions);

    document.getElementById('slimSupplierList')?.addEventListener('click', function (e) {
        var btn = e.target.closest('.catalog-combobox__option');
        if (!btn) return;
        var id = btn.getAttribute('data-id');
        var match = supplierOptions.find(function (s) { return String(s.id) === String(id); });
        setSupplierValue(id, match ? match.name : '');
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('#slimSupplierCombobox')) {
            closeSupplierDropdown();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeSupplierDropdown();
    });

    function setForm(v) {
        document.getElementById('slimCode').value = v.code || '';
        document.getElementById('slimName').value = v.name || '';
        document.getElementById('slimQty').value = v.qty != null ? v.qty : 0;
        document.getElementById('slimMinQty').value = v.min_qty != null ? v.min_qty : 0;
        document.getElementById('slimPrice').value = v.price != null ? v.price : 0;
        document.getElementById('slimEditId').value = v.id || '';
        document.getElementById('slimEditCode').value = v.code || '';
        document.getElementById('slimExtraPrices').innerHTML = '';
        (v.prices || []).forEach(function (p) { window.addSlimPriceRow(p.amount); });
        var first = (v.suppliers || [])[0];
        setSupplierValue(first ? first.id : '', first ? first.name : '');
        document.getElementById('slimCatalogError').style.display = 'none';
        if (window.CatalogSections) {
            window.CatalogSections.prepareItemForm(v);
        }
    }

    function collectSupplierIds() {
        var hidden = document.getElementById('slimSupplierId');
        if (!hidden || !hidden.value) return [];
        var id = parseInt(hidden.value, 10);
        return !isNaN(id) && id > 0 ? [id] : [];
    }

    function supplierNamesLabel(item) {
        var suppliers = item.suppliers || [];
        if (!suppliers.length) return '—';
        return suppliers.map(function (s) { return s.name; }).join('، ');
    }

    function collectExtraPrices() {
        var rows = [];
        document.querySelectorAll('#slimExtraPrices .slim-price-row').forEach(function (r) {
            var amount = parseFloat(r.querySelector('.slim-price-amount').value || '0');
            if (amount > 0) {
                rows.push({ amount: amount });
            }
        });
        return rows;
    }

    function openCatalogFormModal(title) {
        var modal = document.getElementById('catalogFormModal');
        var titleEl = document.getElementById('catalogFormModalTitle');
        if (titleEl) titleEl.textContent = title || '➕ إضافة صنف';
        if (!modal) return;
        modal.classList.add('open');
        modal.removeAttribute('hidden');
    }

    function closeCatalogFormModal() {
        closeSupplierDropdown();
        var modal = document.getElementById('catalogFormModal');
        if (!modal) return;
        modal.classList.remove('open');
        modal.setAttribute('hidden', '');
    }

    window.openSlimCatalogForm = function () {
        setForm({});
        document.getElementById('slimCode').disabled = false;
        openCatalogFormModal('➕ إضافة صنف');
    };

    window.closeSlimCatalogForm = function () {
        closeCatalogFormModal();
    };

    window.editSlimCatalog = function (btn) {
        var row = btn.closest('tr');
        var data = JSON.parse(row.getAttribute('data-item'));
        setForm(data);
        document.getElementById('slimCode').disabled = true;
        openCatalogFormModal('✏️ تعديل صنف');
    };

    function itemHighestPrice(item) {
        var amounts = [parseFloat(item.price) || 0];
        (item.prices || []).forEach(function (p) {
            amounts.push(parseFloat(p.amount) || 0);
        });
        return amounts.length ? Math.max.apply(null, amounts) : 0;
    }

    function itemAllPrices(item) {
        var rows = [{ label: 'السعر الأساسي', amount: parseFloat(item.price) || 0, primary: true }];
        (item.prices || []).forEach(function (p, idx) {
            rows.push({ label: 'سعر إضافي ' + (idx + 1), amount: parseFloat(p.amount) || 0, primary: false });
        });
        return rows.filter(function (r) { return r.amount > 0; });
    }

    function detailBox(label, value) {
        return '<div class="catalog-detail-box"><div class="dl">' + label + '</div><div class="dv">' + value + '</div></div>';
    }

    function renderCatalogSalesStats(containerId, stats) {
        var el = document.getElementById(containerId);
        if (!el) return;

        var rows = stats.rows || [];
        if (!rows.length) {
            el.innerHTML = '<p style="color:var(--text-muted);text-align:center;">لا توجد أسعار أو مبيعات مسجّلة</p>';
            return;
        }

        var html = '<p class="catalog-sales-hint">' + (stats.period_label || '') + ' — إجمالي: '
            + (stats.total_sale_times || 0) + ' حالة · ' + (stats.total_sold_qty || 0) + ' قطعة</p>'
            + '<table class="catalog-prices-table catalog-sales-stats-table"><thead><tr>'
            + '<th>السعر (ج.م)</th><th>مسجّل</th><th>مرات البيع</th><th>الكمية المباعة</th>'
            + '</tr></thead><tbody>'
            + rows.map(function (row) {
                var timesClass = row.sale_times > 0 ? 'stat-hit' : 'stat-zero';
                var qtyClass = row.sold_qty > 0 ? 'stat-hit' : 'stat-zero';
                return '<tr>'
                    + '<td class="price-val">' + formatCatalogPrice(row.unit_price) + '</td>'
                    + '<td>' + (row.registered ? '✓' : '—') + '</td>'
                    + '<td class="' + timesClass + '">' + row.sale_times + '</td>'
                    + '<td class="' + qtyClass + '">' + row.sold_qty + '</td>'
                    + '</tr>';
            }).join('')
            + '</tbody></table>';

        el.innerHTML = html;
    }

    window.viewSlimCatalog = function (btn) {
        var row = btn.closest('tr');
        var item = JSON.parse(row.getAttribute('data-item'));
        var modal = document.getElementById('catalogViewModal');
        if (!modal) return;

        document.getElementById('catalogViewTitle').textContent = item.name || '—';
        document.getElementById('catalogViewCode').textContent = (item.code || '—') + (item.barcode ? ' · ' + item.barcode : '');

        var prices = itemAllPrices(item);
        var pricesHtml = prices.length
            ? '<table class="catalog-prices-table"><thead><tr><th>#</th><th>النوع</th><th>القيمة (ج.م)</th></tr></thead><tbody>'
                + prices.map(function (p, i) {
                    return '<tr><td>' + (i + 1) + '</td><td>' + p.label + '</td><td class="price-val">' + formatCatalogPrice(p.amount) + '</td></tr>';
                }).join('')
                + '</tbody></table>'
            : '<p style="color:var(--text-muted);text-align:center;">لا توجد أسعار مسجّلة</p>';

        document.getElementById('catalogViewBody').innerHTML =
            '<div class="catalog-detail-grid">'
            + detailBox('كود الصنف', item.code || '—')
            + detailBox('الباركود', item.barcode || '—')
            + detailBox('الكمية', String(parseInt(item.qty, 10) || 0))
            + detailBox('الحد الأدنى', String(parseInt(item.min_qty, 10) || 0))
            + detailBox('القسم', item.category || '—')
            + detailBox('المورد', supplierNamesLabel(item))
            + detailBox('خصائص القسم', window.CatalogSections ? window.CatalogSections.formatAttributesSummary(item) : '—')
            + detailBox('السعر الأساسي', '<span class="catalog-price-cell">' + formatCatalogPrice(item.price) + '</span>')
            + detailBox('أعلى سعر', '<span class="catalog-price-cell">' + formatCatalogPrice(itemHighestPrice(item)) + '</span>')
            + '</div>'
            + '<h4 style="font-size:14px;font-weight:800;margin:0 0 10px;color:var(--secondary);">💰 جميع الأسعار</h4>'
            + pricesHtml
            + '<h4 style="font-size:14px;font-weight:800;margin:16px 0 10px;color:var(--secondary);">📈 البيع حسب مستوى السعر</h4>'
            + '<div id="catalogViewSalesStats"><p style="color:var(--text-muted);text-align:center;">جاري التحميل...</p></div>';

        modal.classList.add('open');
        modal.removeAttribute('hidden');

        var params = new URLSearchParams();
        var fromEl = document.getElementById('catalogDateFrom');
        var toEl = document.getElementById('catalogDateTo');
        if (fromEl && fromEl.value) params.set('from', fromEl.value);
        if (toEl && toEl.value) params.set('to', toEl.value);

        var statsUrl = @json(url('/admin/catalog')) + '/' + item.id + '/sales-stats' + (params.toString() ? ('?' + params.toString()) : '');

        fetch(statsUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (res) { return res.ok ? res.json() : Promise.reject(new Error('fetch failed')); })
            .then(function (stats) { renderCatalogSalesStats('catalogViewSalesStats', stats); })
            .catch(function () {
                var el = document.getElementById('catalogViewSalesStats');
                if (el) el.innerHTML = '<p style="color:#dc2626;text-align:center;">تعذّر تحميل إحصائيات البيع</p>';
            });
    };

    function closeCatalogViewModal() {
        var modal = document.getElementById('catalogViewModal');
        if (!modal) return;
        modal.classList.remove('open');
        modal.setAttribute('hidden', '');
    }

    document.getElementById('catalogViewClose')?.addEventListener('click', closeCatalogViewModal);
    document.getElementById('catalogViewCloseBtn')?.addEventListener('click', closeCatalogViewModal);
    document.getElementById('catalogViewModal')?.addEventListener('click', function (e) {
        if (e.target === this) closeCatalogViewModal();
    });

    document.getElementById('catalogFormClose')?.addEventListener('click', closeCatalogFormModal);
    document.getElementById('catalogFormModal')?.addEventListener('click', function (e) {
        if (e.target === this) closeCatalogFormModal();
    });

    window.saveSlimCatalog = function () {
        var id = document.getElementById('slimEditId').value;
        var err = document.getElementById('slimCatalogError');
        var name = document.getElementById('slimName').value.trim();

        if (window.CatalogSections) {
            var catErr = window.CatalogSections.validateBeforeSave();
            if (catErr) {
                err.textContent = catErr;
                err.style.display = 'block';
                return;
            }
        }

        if (!name) {
            err.textContent = 'يرجى إدخال اسم الصنف.';
            err.style.display = 'block';
            return;
        }

        var supplierIds = collectSupplierIds();
        if (!supplierIds.length) {
            err.textContent = 'يرجى اختيار مورد واحد لهذا الصنف.';
            err.style.display = 'block';
            return;
        }
        if (supplierIds.length > 1) {
            err.textContent = 'يُسمح بمورد واحد فقط لكل صنف.';
            err.style.display = 'block';
            return;
        }

        var payload = {
            name: name,
            qty: parseInt(document.getElementById('slimQty').value || '0', 10),
            min_qty: parseInt(document.getElementById('slimMinQty').value || '0', 10),
            price: parseFloat(document.getElementById('slimPrice').value || '0'),
            prices: collectExtraPrices(),
            category_id: parseInt(document.getElementById('slimCategoryId').value || '0', 10) || null,
            attributes: window.CatalogSections ? window.CatalogSections.collectAttributes() : {},
            supplier_ids: supplierIds,
        };

        if (!id) {
            var code = document.getElementById('slimCode').value.trim();
            if (code) payload.code = code;
        }

        var url = id ? ('/admin/catalog/' + id) : '/admin/catalog';
        var method = id ? 'PUT' : 'POST';

        fetch(url, {
            method: method,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        })
        .then(function (r) { return r.ok ? r.json() : r.json().then(function (j) { throw j; }); })
        .then(function () { window.location.reload(); })
        .catch(function (e) {
            var msg = (e && e.message) ? e.message : 'تعذّر الحفظ.';
            if (e && e.errors) { msg = Object.values(e.errors)[0][0]; }
            err.textContent = msg;
            err.style.display = 'block';
        });
    };

    window.deleteSlimCatalog = function (id, name) {
        if (!confirm('حذف «' + name + '»؟')) return;
        fetch('/admin/catalog/' + id, {
            method: 'DELETE',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
        .then(function (r) { return r.ok ? r.json() : r.json().then(function (j) { throw j; }); })
        .then(function () { window.location.reload(); })
        .catch(function (e) { alert((e && e.message) ? e.message : 'تعذّر الحذف.'); });
    };

    function formatCatalogPrice(n) {
        return Number(n || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function escAttr(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
    }

    function catalogRowHtml(item) {
        var search = escAttr(((item.code || '') + ' ' + (item.name || '') + ' ' + (item.category || '')).toLowerCase());
        var dataAttr = escAttr(JSON.stringify(item));
        var highest = itemHighestPrice(item);
        var extra = (item.prices && item.prices.length)
            ? (item.prices.length + ' سعر')
            : '—';
        var labelsUrl = '/admin/catalog/' + item.id + '/labels';
        return '<tr class="catalog-slim-row" data-item-id="' + (item.id || '') + '" data-search="' + search + '" data-category-id="' + (item.category_id || '') + '" data-filter-hidden="0" data-item="' + dataAttr + '" style="border-top:1px solid var(--border);">' +
            '<td style="padding:10px;direction:ltr;text-align:right;"><strong>' + (item.code || '') + '</strong></td>' +
            '<td style="padding:10px;">' + (item.name || '') + '</td>' +
            '<td style="padding:10px;color:var(--text-muted);">' + (item.category || '—') + '</td>' +
            '<td style="padding:10px;color:var(--text-muted);font-size:12px;">' + supplierNamesLabel(item) + '</td>' +
            '<td style="padding:10px;text-align:center;">' + (parseInt(item.qty, 10) || 0) + '</td>' +
            '<td style="padding:10px;text-align:center;" class="catalog-price-cell">' + formatCatalogPrice(item.price) + '</td>' +
            '<td style="padding:10px;text-align:center;" class="catalog-price-cell">' + formatCatalogPrice(highest) + '</td>' +
            '<td style="padding:10px;text-align:center;color:var(--text-muted);">' + extra + '</td>' +
            '<td style="padding:10px;text-align:center;white-space:nowrap;">' +
            '<button type="button" class="btn-action" onclick="viewSlimCatalog(this)">👁️ عرض</button> ' +
            '<button type="button" class="btn-action" onclick="editSlimCatalog(this)">✏️ تعديل</button> ' +
            '<a class="btn-action" target="_blank" href="' + labelsUrl + '">🏷️ باركود</a> ' +
            '<button type="button" class="btn-action danger" onclick="deleteSlimCatalog(' + item.id + ', ' + JSON.stringify(item.name || '') + ')">🗑️</button>' +
            '</td></tr>';
    }

    window.renderSlimCatalogTable = function (items) {
        var tbody = document.getElementById('catalogSlimTable');
        var countEl = document.getElementById('catalogSlimCount');
        var badge = document.querySelector('#section-catalog .badge');
        var list = items || [];

        if (!tbody) return;

        if (!list.length) {
            tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:24px;">لا توجد أصناف — أضف صنفاً أو ارفع ملف Excel.</td></tr>';
        } else {
            tbody.innerHTML = list.map(catalogRowHtml).join('');
        }

        var label = list.length + ' صنف';
        if (countEl) countEl.textContent = label;
        if (badge) badge.textContent = label;
        window.applySlimCatalogFilters();

        var table = document.getElementById('catalogItemsTable');
        if (table && window.TablePagination && TablePagination.refresh) {
            TablePagination.refresh(table);
        }
    };

    function showImportStatus(message, isError, errors) {
        var box = document.getElementById('catalogImportStatus');
        if (!box) return;
        box.style.display = 'block';
        box.style.background = isError ? '#fee2e2' : '#dcfce7';
        box.style.border = '1px solid ' + (isError ? '#fca5a5' : '#86efac');
        box.style.color = isError ? '#dc2626' : '#166534';
        var html = (isError ? '⚠️ ' : '✅ ') + message;
        if (errors && errors.length) {
            html += errors.map(function (e) { return '<div style="margin-top:6px;font-size:12px;">⚠️ ' + e + '</div>'; }).join('');
        }
        box.innerHTML = html;
    }

    var importForm = document.getElementById('catalogImportForm');
    var fileInput = document.getElementById('catalogImportFile');
    var importLabel = document.getElementById('catalogImportBtn');

    function uploadCatalogFile() {
        if (!importForm || !fileInput || !fileInput.files || !fileInput.files[0]) {
            showImportStatus('يرجى اختيار ملف Excel أو CSV.', true);
            return;
        }

        var formData = new FormData(importForm);
        if (importLabel) {
            importLabel.textContent = 'جاري الرفع…';
            importLabel.style.pointerEvents = 'none';
            importLabel.style.opacity = '0.7';
        }

        fetch(importForm.action, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf(),
            },
            credentials: 'same-origin',
            body: formData,
        })
        .then(function (r) {
            return r.json().then(function (j) {
                if (!r.ok) throw j;
                return j;
            });
        })
        .then(function (data) {
            showImportStatus(data.message || 'تم الاستيراد بنجاح.', false, data.summary && data.summary.errors);
            window.renderSlimCatalogTable(data.items || []);
            fileInput.value = '';
        })
        .catch(function (err) {
            var msg = (err && err.message) ? err.message : 'تعذّر رفع الملف.';
            if (err && err.errors && err.errors.file) {
                msg = err.errors.file[0];
            }
            showImportStatus(msg, true, err && err.summary ? err.summary.errors : null);
        })
        .finally(function () {
            if (importLabel) {
                importLabel.textContent = '📤 ارفع ملف اكسيل';
                importLabel.style.pointerEvents = '';
                importLabel.style.opacity = '';
            }
        });
    }

    if (fileInput) {
        fileInput.addEventListener('change', function () {
            if (fileInput.files && fileInput.files[0]) {
                uploadCatalogFile();
            }
        });
    }

    if (importForm) {
        importForm.addEventListener('submit', function (e) {
            e.preventDefault();
            uploadCatalogFile();
        });
    }
})();
</script>
<script>
window.__STOCK_CATEGORIES = @json($categories->values());
</script>
<script src="{{ asset('assets/js/pages/catalog-sections.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.CatalogSections && window.__STOCK_CATEGORIES) {
        window.CatalogSections.init(window.__STOCK_CATEGORIES);
    }

    if (typeof window.applySlimCatalogFilters === 'function') {
        window.applySlimCatalogFilters();
    }

    var itemId = new URLSearchParams(window.location.search).get('item');
    if (itemId && typeof window.viewSlimCatalog === 'function') {
        var row = document.querySelector('#catalogSlimTable tr.catalog-slim-row[data-item-id="' + itemId + '"]');
        if (row) {
            var btn = row.querySelector('button[onclick*="viewSlimCatalog"]');
            window.viewSlimCatalog(btn || row);
        }
    }
});
</script>
