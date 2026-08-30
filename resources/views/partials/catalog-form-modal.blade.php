@php
    $categoryOptions = collect($categories ?? []);
@endphp
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
                    <h4 class="catalog-form-card__title">📦 بيانات الصنف</h4>
                    <div class="catalog-form-grid catalog-form-grid--basic">
                        <div>
                            <label class="catalog-form-label">رقم الصنف</label>
                            <input type="text" id="slimCode" placeholder="تلقائي (ITM-xxx) إن تُرك فارغاً" class="catalog-form-input">
                        </div>
                        <div>
                            <label class="catalog-form-label">رقم الصفحة</label>
                            <input type="text" id="slimPageNumber" placeholder="مثال: 12" class="catalog-form-input">
                        </div>
                        <div>
                            <label class="catalog-form-label">اسم الصنف *</label>
                            <input type="text" id="slimName" placeholder="مثال: ركبة هيدروليكية" class="catalog-form-input">
                        </div>
                        <div>
                            <label class="catalog-form-label">الماركة</label>
                            <input type="text" id="slimBrand" placeholder="مثال: Ottobock" class="catalog-form-input">
                        </div>
                        <div id="slimBarcodeWrap" style="display:none;">
                            <label class="catalog-form-label">الباركود (تلقائي)</label>
                            <input type="text" id="slimBarcodeDisplay" class="catalog-form-input" readonly dir="ltr">
                            <input type="hidden" id="slimAltCodes">
                        </div>
                        <div>
                            <label class="catalog-form-label">الوحدة</label>
                            <input type="text" id="slimUom" list="slimUomOptions" value="قطعة" class="catalog-form-input" placeholder="قطعة / متر / طقم">
                            <datalist id="slimUomOptions">
                                <option value="قطعة"></option>
                                <option value="متر"></option>
                                <option value="طقم"></option>
                                <option value="لفة"></option>
                                <option value="كيلو"></option>
                                <option value="جرام"></option>
                                <option value="لتر"></option>
                            </datalist>
                        </div>
                        <div>
                            <label class="catalog-form-label">رصيد أول المده</label>
                            <input type="number" id="slimOpeningQty" min="0" value="0" class="catalog-form-input">
                        </div>
                        <div>
                            <label class="catalog-form-label">الاضافة</label>
                            <input type="number" id="slimAddition" min="0" value="0" class="catalog-form-input">
                        </div>
                        <div>
                            <label class="catalog-form-label">الخصم</label>
                            <input type="number" id="slimDiscount" min="0" value="0" class="catalog-form-input">
                        </div>
                        <div>
                            <label class="catalog-form-label">الرصيد</label>
                            <input type="number" id="slimBalance" min="0" value="0" class="catalog-form-input" readonly>
                        </div>
                        <div>
                            <label class="catalog-form-label">الحد الأدنى للطلب</label>
                            <input type="number" id="slimMinQty" min="0" value="0" class="catalog-form-input" placeholder="مثال: 10">
                        </div>
                        <div>
                            <label class="catalog-form-label">السعر الأساسي</label>
                            <div class="catalog-price-with-qty">
                                <input type="number" id="slimPrice" min="0" step="0.01" value="0" class="catalog-form-input">
                                <span id="slimBasePriceQty" class="slim-price-qty-badge" hidden></span>
                            </div>
                        </div>
                        <div class="catalog-form-grid__full">
                            <label class="catalog-quick-dispense-label">
                                <input type="checkbox" id="slimIsQuickDispense">
                                <span>⚡ صنف صرف سريع (ربح مباشر 40% — يُباع كما هو بدون تصنيع)</span>
                            </label>
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
                        @foreach ($categoryOptions as $cat)
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
                        <div class="catalog-tier-hint catalog-tier-hint--steps">
                            <p><strong>كيف أضيف أسعار بكمياتها؟</strong></p>
                            <ol style="margin:6px 0 0;padding-right:18px;line-height:1.55;">
                                <li>أضف <strong>سعر إضافي</strong> هنا (تعريف السعر).</li>
                                <li>في <strong>المخزن → استلام وارد</strong>: الكمية + سعر الوحدة لكل فاتورة.</li>
                                <li>«مخزن: X» = قراءة فقط من الاستلام.</li>
                            </ol>
                        </div>
                        <div id="slimWarehouseTiers" class="catalog-warehouse-tiers" hidden></div>
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
