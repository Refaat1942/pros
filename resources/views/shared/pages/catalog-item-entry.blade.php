@php
    $categories = collect($stock_categories ?? []);
    $catalogSuppliers = collect($suppliers ?? []);
    $catalogApiBase = $catalog_api_base ?? url('/admin/catalog');
    $successRedirect = $catalog_success_redirect ?? null;
@endphp
<div class="section-view" id="section-add-catalog-item">
    <div class="panel">
        <div class="panel-header">
            <h3>➕ إضافة صنف جديد</h3>
        </div>
        <div class="panel-body" style="padding:16px 20px;">
            <p style="margin:0 0 14px;color:var(--text-muted);font-size:13px;line-height:1.6;">
                استخدم النموذج لإضافة صنف جديد إلى كتالوج الأصناف والمخزون. التحقق والصلاحيات نفس شاشة «الأصناف والأسعار».
            </p>
            <button type="button" class="btn-action primary" style="background:var(--primary);color:#fff;border:none;" onclick="openSlimCatalogForm()">
                ➕ إضافة صنف
            </button>
        </div>
    </div>
</div>

@include('partials.catalog-form-modal-styles')
@include('partials.catalog-form-modal', ['categories' => $categories])

<script>
window.__STOCK_CATEGORIES = @json($categories->values());
window.__CATALOG_SUPPLIERS = @json($catalogSuppliers->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values());
window.__CATALOG_API_BASE = @json($catalogApiBase);
window.__CATALOG_SUCCESS_REDIRECT = @json($successRedirect);
window.__CATALOG_ENTRY_AUTO_OPEN = true;
</script>
<script src="{{ asset('assets/js/pages/catalog-sections.js') }}"></script>
<script src="{{ asset('assets/js/pages/catalog-item-form.js') }}"></script>
