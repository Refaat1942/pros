@php
    $catalogListRoles = $catalog_list_roles ?? app(\App\Services\CatalogListVisibilityService::class)->catalogForAdmin();
@endphp

<div class="section-view" id="section-catalog-list-settings">
    <div class="panel">
        <div class="panel-header">
            <h3>📋 عرض قوائم الأصناف</h3>
        </div>

        <p class="catalog-list-settings-hint">
            حدّد لكل <strong>دور</strong> ما إذا كان يرى قوائم <strong>المخزون والتوريد</strong> (مفتاح رئيسي)،
            ثم فعّل كل قائمة على حدة وأعمدة كل قائمة.
            الأعمدة المرتبطة بصلاحية (مثل السعر أو WAC) تُخفى تلقائياً لو المستخدم لا يملك الصلاحية —
            حتى لو مفعّلة هنا.
        </p>

        <div id="catalogListSettingsWrap" class="catalog-list-settings-wrap"></div>
        <div id="catalogListSettingsError" class="catalog-list-settings-error" style="display:none;"></div>

        <div class="catalog-list-settings-actions">
            <button type="button" class="btn-action success" id="btnSaveCatalogListSettings">💾 حفظ الإعدادات</button>
        </div>
    </div>
</div>

<style>
    #section-catalog-list-settings .catalog-list-settings-hint {
        margin: 12px 16px 0;
        padding: 10px 14px;
        background: #f8fafc;
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 8px;
        font-size: 13px;
        line-height: 1.6;
        color: var(--text-muted, #64748b);
    }
    .catalog-list-settings-wrap { padding: 16px; display: flex; flex-direction: column; gap: 20px; }
    .catalog-list-settings-role {
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 12px;
        overflow: hidden;
        background: #fff;
    }
    .catalog-list-settings-role__head {
        padding: 12px 16px;
        background: var(--surface-2, #f8fafc);
        border-bottom: 1px solid var(--border, #e2e8f0);
        font-weight: 800;
        font-size: 15px;
    }
    .catalog-list-settings-section {
        border-top: 1px solid var(--border, #e2e8f0);
        background: #fafbff;
    }
    .catalog-list-settings-section__head {
        padding: 12px 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        border-bottom: 1px dashed var(--border, #e2e8f0);
    }
    .catalog-list-settings-section__title {
        font-weight: 800;
        font-size: 14px;
        flex: 1;
    }
    .catalog-list-settings-section__hint {
        font-size: 11px;
        color: var(--text-muted, #94a3b8);
    }
    .catalog-list-settings-profile.is-section-off {
        opacity: 0.55;
    }
    .catalog-list-settings-profile {
        padding: 14px 16px;
        border-top: 1px solid var(--border, #e2e8f0);
    }
    .catalog-list-settings-profile__head {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
        flex-wrap: wrap;
    }
    .catalog-list-settings-profile__title { font-weight: 700; font-size: 14px; flex: 1; }
    .catalog-list-settings-profile__meta {
        font-size: 11px;
        color: var(--text-muted, #94a3b8);
    }
    .catalog-list-settings-columns {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 8px;
        margin-top: 8px;
    }
    .catalog-list-settings-col {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 10px;
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 8px;
        font-size: 13px;
        background: #fafafa;
    }
    .catalog-list-settings-col.is-gated { border-style: dashed; }
    .catalog-list-settings-col.is-required { opacity: 0.85; }
    .catalog-list-settings-col.is-required input { pointer-events: none; }
    .catalog-list-settings-error {
        margin: 0 16px;
        padding: 10px 14px;
        background: #fee2e2;
        border: 1px solid #fca5a5;
        border-radius: 8px;
        color: #dc2626;
        font-size: 13px;
    }
    .catalog-list-settings-actions { padding: 0 16px 16px; }
</style>

<script type="application/json" id="catalogListSettingsBootstrap">
{!! json_encode(['roles' => $catalogListRoles, 'csrf' => csrf_token()], JSON_UNESCAPED_UNICODE) !!}
</script>

@push('scripts')
    <script src="{{ asset('assets/js/pages/catalog-list-settings.js') }}?v={{ filemtime(public_path('assets/js/pages/catalog-list-settings.js')) }}"></script>
@endpush
