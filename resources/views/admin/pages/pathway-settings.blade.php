@php
    $designer = [
        'civilian' => $pathway_civilian_steps ?? ($civilian ?? []),
        'military' => $pathway_military_steps ?? ($military ?? []),
        'entity' => $pathway_entity_steps ?? ($entity ?? []),
        'departments' => $departments ?? [],
        'skip_roles' => $skip_roles ?? ($workflow_skip_role_options ?? []),
        'handlers' => $handlers ?? [],
    ];
@endphp

@push('styles-late')
    <link rel="stylesheet" href="{{ asset('assets/css/pathway-designer.css') }}?v={{ filemtime(public_path('assets/css/pathway-designer.css')) }}">
@endpush

<div class="section-view pathway-designer-page" id="section-pathway-settings">
    <div class="panel">
        <div class="panel-header">
            <h3>🧭 مصمم مسار العمل</h3>
        </div>

        <p class="pathway-designer-intro">
            <strong>ثلاثة مسارات — نفس ترتيب جدول العميل:</strong><br>
            🌐 <strong>مدني (نقدي)</strong> — 11 خطوة · 🪖 <strong>عسكري</strong> — 11 خطوة (الخزنة تُتخطى تلقائياً) · 🏢 <strong>جهات</strong> — 13 خطوة.<br>
            اضغط على أي خلية في الجدول لتعديل: القسم المسؤول · الإجراء · الانتقال التالي · التخطي.
        </p>

        <div class="pathway-designer-toolbar">
            <span class="pathway-designer-toolbar__hint" id="pathwayEditHint">اختر خلية من الجدول للتعديل</span>
            <button type="button" class="btn-action" id="btnResetPathway">↩️ استعادة المسار الحالي</button>
            <button type="button" class="btn-action" id="btnResetAllPathways">↩️ استعادة الثلاثة</button>
        </div>

        <div id="pathwayMatrixWrap" class="pathway-matrix-wrap" aria-label="جدول المسارات الثلاثة"></div>

        <div id="pathwayFlowMap" class="pathway-flow-map" aria-live="polite"></div>

        <div id="pathwayStepEditor" class="pathway-step-editor" hidden></div>

        <div id="pathwayDesignerError" class="pathway-designer-error" style="display:none;"></div>

        <div class="pathway-designer-actions">
            <button type="button" class="btn-action success" id="btnSavePathway">💾 حفظ المسار الحالي</button>
            <button type="button" class="btn-action success" id="btnSaveAllPathways">💾 حفظ الثلاثة</button>
        </div>
    </div>
</div>

<script type="application/json" id="pathwayDesignerBootstrap">
{!! json_encode(array_merge($designer, ['csrf' => csrf_token()]), JSON_UNESCAPED_UNICODE) !!}
</script>

@push('scripts')
    <script src="{{ asset('assets/js/pages/pathway-designer.js') }}?v={{ filemtime(public_path('assets/js/pages/pathway-designer.js')) }}"></script>
@endpush
