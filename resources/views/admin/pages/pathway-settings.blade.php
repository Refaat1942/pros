@php
    $civilianSteps = $pathway_civilian_steps ?? [];
    $militarySteps = $pathway_military_steps ?? [];
    $stageKeyOptions = $pathway_stage_key_options ?? [];
    $civilianPolicies = $workflow_civilian_policies ?? [];
    $militaryPolicies = $workflow_military_policies ?? [];
    $skipRoleOptions = $workflow_skip_role_options ?? [];
@endphp
<div class="section-view" id="section-pathway-settings">
    <div class="pathway-settings-tabs pathway-settings-tabs--main">
        <button type="button" class="pathway-tab active" data-main-tab="display">🔢 ترقيم العرض</button>
        <button type="button" class="pathway-tab" data-main-tab="workflow">⚡ قواعد التدفق والتخطي</button>
    </div>

    <div id="pathwayMainDisplay">
    <div class="panel">
        <div class="panel-header">
            <h3>🧭 ترقيم مسار العمل — المدني والعسكري</h3>
        </div>

        <p class="pathway-settings-hint">
            تحكم في <strong>ترقيم وعناوين خطوات المسار</strong> كما تظهر في المتابعة العامة ولوحة مسار المرضى ونافذة «خط السير».
            هذا الإعداد <strong>للعرض والمتابعة فقط</strong>.
        </p>

        <div class="pathway-settings-tabs">
            <button type="button" class="pathway-tab active" data-pathway-tab="civilian">🌐 المسار المدني</button>
            <button type="button" class="pathway-tab" data-pathway-tab="military">🪖 المسار العسكري</button>
        </div>

        <div class="pathway-editor" id="pathwayEditorCivilian" data-pathway="civilian">
            <div class="pathway-editor-toolbar">
                <button type="button" class="btn-action" id="btnAddCivilianStep">➕ إضافة خطوة</button>
                <button type="button" class="btn-action" id="btnResetCivilian">↩️ استعادة الافتراضي</button>
            </div>
            <div id="civilianStepsList" class="pathway-steps-list"></div>
            <div id="pathwayCivilianError" class="pathway-settings-error" style="display:none;"></div>
            <div class="pathway-settings-actions">
                <button type="button" class="btn-action success" id="btnSaveCivilian">💾 حفظ المسار المدني</button>
            </div>
        </div>

        <div class="pathway-editor" id="pathwayEditorMilitary" data-pathway="military" hidden>
            <div class="pathway-editor-toolbar">
                <button type="button" class="btn-action" id="btnAddMilitaryStep">➕ إضافة خطوة</button>
                <button type="button" class="btn-action" id="btnResetMilitary">↩️ استعادة الافتراضي</button>
            </div>
            <div id="militaryStepsList" class="pathway-steps-list"></div>
            <div id="pathwayMilitaryError" class="pathway-settings-error" style="display:none;"></div>
            <div class="pathway-settings-actions">
                <button type="button" class="btn-action success" id="btnSaveMilitary">💾 حفظ المسار العسكري</button>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <h3>📋 مراحل النظام (stage_key)</h3>
        </div>
        <p class="pathway-settings-hint">
            اربط كل خطوة بمرحلة أو أكثر من مراحل النظام. عند وصول الحالة لأي مرحلة مرتبطة، تُحدَّث شريط التقدم على تلك الخطوة.
        </p>
        <ul class="pathway-stage-ref">
            @foreach ($stageKeyOptions as $opt)
                <li><code>{{ $opt['value'] }}</code> — {{ $opt['label'] }}</li>
            @endforeach
        </ul>
    </div>
    </div>

    <div id="pathwayMainWorkflow" hidden>
    <div class="panel">
        <div class="panel-header">
            <h3>⚡ قواعد التدفق — تخطي المراحل الاختيارية</h3>
        </div>
        <p class="pathway-settings-hint">
            حدّد المراحل التي يمكن <strong>تخطيها</strong> أو <strong>تخطيها تلقائياً</strong> — مع الحفاظ على المراحل المقفلة (عرض السعر، التشغيل، المخزن، …).
            المدير يمكنه تخطي مرحلة اختيارية من <strong>متابعة الحالات</strong> عند الحاجة.
        </p>
        <div class="pathway-settings-tabs">
            <button type="button" class="pathway-tab active" data-wf-tab="civilian">🌐 المسار المدني</button>
            <button type="button" class="pathway-tab" data-wf-tab="military">🪖 المسار العسكري</button>
        </div>
        <div class="pathway-editor" id="wfEditorCivilian">
            <div class="pathway-editor-toolbar">
                <button type="button" class="btn-action" id="btnResetWfCivilian">↩️ استعادة الافتراضي</button>
            </div>
            <div id="civilianPoliciesList" class="pathway-steps-list"></div>
            <div id="wfCivilianError" class="pathway-settings-error" style="display:none;"></div>
            <div class="pathway-settings-actions">
                <button type="button" class="btn-action success" id="btnSaveWfCivilian">💾 حفظ قواعد المدني</button>
            </div>
        </div>
        <div class="pathway-editor" id="wfEditorMilitary" hidden>
            <div class="pathway-editor-toolbar">
                <button type="button" class="btn-action" id="btnResetWfMilitary">↩️ استعادة الافتراضي</button>
            </div>
            <div id="militaryPoliciesList" class="pathway-steps-list"></div>
            <div id="wfMilitaryError" class="pathway-settings-error" style="display:none;"></div>
            <div class="pathway-settings-actions">
                <button type="button" class="btn-action success" id="btnSaveWfMilitary">💾 حفظ قواعد العسكري</button>
            </div>
        </div>
    </div>
    </div>
</div>

<script type="application/json" id="pathwaySettingsBootstrap">
{!! json_encode([
    'civilian' => $civilianSteps,
    'military' => $militarySteps,
    'stageKeyOptions' => $stageKeyOptions,
    'workflowCivilian' => $civilianPolicies,
    'workflowMilitary' => $militaryPolicies,
    'skipRoleOptions' => $skipRoleOptions,
    'csrf' => csrf_token(),
], JSON_UNESCAPED_UNICODE) !!}
</script>

<style>
    #section-pathway-settings .pathway-settings-hint {
        margin: 0 16px 16px;
        padding: 12px 14px;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 8px;
        color: #1e40af;
        font-size: 13px;
        line-height: 1.7;
    }
    #section-pathway-settings .pathway-settings-tabs--main {
        padding: 16px 16px 0;
    }
    #section-pathway-settings .pathway-settings-tabs {
        display: flex;
        gap: 8px;
        padding: 0 16px 12px;
        flex-wrap: wrap;
    }
    #section-pathway-settings .pathway-tab {
        padding: 8px 14px;
        border: 1px solid var(--border);
        border-radius: 999px;
        background: #fff;
        cursor: pointer;
        font-family: inherit;
        font-size: 13px;
        font-weight: 700;
    }
    #section-pathway-settings .pathway-tab.active {
        background: #1e40af;
        color: #fff;
        border-color: #1e40af;
    }
    #section-pathway-settings .pathway-editor { padding: 0 16px 16px; }
    #section-pathway-settings .pathway-editor-toolbar {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 12px;
    }
    #section-pathway-settings .pathway-steps-list { display: grid; gap: 10px; }
    #section-pathway-settings .pathway-step-card {
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 12px;
        background: #fff;
        display: grid;
        gap: 10px;
    }
    #section-pathway-settings .pathway-step-head {
        display: grid;
        grid-template-columns: 56px 1fr auto;
        gap: 10px;
        align-items: center;
    }
    #section-pathway-settings .pathway-step-num {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: #1e40af;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 18px;
    }
    #section-pathway-settings .pathway-step-fields {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 8px;
    }
    #section-pathway-settings .pw-field {
        display: grid;
        gap: 4px;
        font-size: 12px;
        font-weight: 700;
    }
    #section-pathway-settings .pw-field input,
    #section-pathway-settings .pw-field select {
        padding: 7px;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-family: inherit;
        font-weight: 400;
    }
    #section-pathway-settings .pw-stage-keys {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 14px;
        font-size: 12px;
    }
    #section-pathway-settings .pw-stage-keys label {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-weight: 600;
    }
    #section-pathway-settings .pathway-settings-error {
        margin-top: 10px;
        padding: 10px 12px;
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 8px;
        color: #b91c1c;
        font-size: 13px;
    }
    #section-pathway-settings .pathway-settings-actions { margin-top: 14px; }
    #section-pathway-settings .pathway-stage-ref {
        margin: 0 16px 16px;
        padding: 0 16px;
        columns: 2;
        font-size: 13px;
        line-height: 1.8;
    }
    #section-pathway-settings .pathway-step-card--locked {
        background: #fffbeb;
        border-color: #fcd34d;
    }
    @media (max-width: 640px) {
        #section-pathway-settings .pathway-stage-ref { columns: 1; }
    }
</style>

<script>
(function () {
    var boot = JSON.parse(document.getElementById('pathwaySettingsBootstrap').textContent || '{}');
    var state = {
        civilian: (boot.civilian || []).slice(),
        military: (boot.military || []).slice(),
    };
    var stageOpts = boot.stageKeyOptions || [];
    var csrf = boot.csrf || '';

    function esc(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    function renumber(steps) {
        steps.forEach(function (s, i) { s.sort = i + 1; });
        return steps;
    }

    function renderList(pathway) {
        var list = document.getElementById(pathway === 'civilian' ? 'civilianStepsList' : 'militaryStepsList');
        if (!list) return;
        var steps = renumber(state[pathway].slice());
        state[pathway] = steps;

        list.innerHTML = steps.map(function (step, idx) {
            var keys = step.stage_keys || [];
            var checks = stageOpts.map(function (opt) {
                var checked = keys.indexOf(opt.value) >= 0 ? ' checked' : '';
                return '<label><input type="checkbox" data-idx="' + idx + '" data-pathway="' + pathway + '" data-stage="' + esc(opt.value) + '"' + checked + '> ' + esc(opt.label) + '</label>';
            }).join('');

            return ''
                + '<div class="pathway-step-card" data-idx="' + idx + '">'
                + '  <div class="pathway-step-head">'
                + '    <div class="pathway-step-num">' + step.sort + '</div>'
                + '    <div class="pathway-step-fields">'
                + '      <label class="pw-field"><span>عنوان الخطوة</span><input type="text" data-field="label" data-idx="' + idx + '" data-pathway="' + pathway + '" value="' + esc(step.label) + '"></label>'
                + '      <label class="pw-field"><span>مفتاح (key)</span><input type="text" data-field="key" data-idx="' + idx + '" data-pathway="' + pathway + '" value="' + esc(step.key) + '" pattern="[a-z0-9_]+"></label>'
                + '      <label class="pw-field"><span>وصف مختصر</span><input type="text" data-field="description" data-idx="' + idx + '" data-pathway="' + pathway + '" value="' + esc(step.description || '') + '"></label>'
                + '      <label class="pw-field"><span>نشط</span><select data-field="active" data-idx="' + idx + '" data-pathway="' + pathway + '">'
                + '        <option value="1"' + (step.active !== false ? ' selected' : '') + '>نعم</option>'
                + '        <option value="0"' + (step.active === false ? ' selected' : '') + '>لا</option>'
                + '      </select></label>'
                + '    </div>'
                + '    <div style="display:flex;flex-direction:column;gap:4px;">'
                + '      <button type="button" class="cm-btn-sm" data-move="up" data-idx="' + idx + '" data-pathway="' + pathway + '">▲</button>'
                + '      <button type="button" class="cm-btn-sm" data-move="down" data-idx="' + idx + '" data-pathway="' + pathway + '">▼</button>'
                + '      <button type="button" class="cm-btn-sm cm-btn-danger" data-remove data-idx="' + idx + '" data-pathway="' + pathway + '">✕</button>'
                + '    </div>'
                + '  </div>'
                + '  <div class="pw-stage-keys">' + checks + '</div>'
                + '</div>';
        }).join('');
    }

    function bindEditor(pathway) {
        var list = document.getElementById(pathway === 'civilian' ? 'civilianStepsList' : 'militaryStepsList');
        if (!list || list.dataset.bound) return;
        list.dataset.bound = '1';

        list.addEventListener('input', function (e) {
            var t = e.target;
            var field = t.getAttribute('data-field');
            if (!field) return;
            var idx = parseInt(t.getAttribute('data-idx'), 10);
            var p = t.getAttribute('data-pathway');
            if (field === 'active') {
                state[p][idx].active = t.value === '1';
            } else {
                state[p][idx][field] = t.value;
            }
        });

        list.addEventListener('change', function (e) {
            var t = e.target;
            if (!t.getAttribute('data-stage')) return;
            var idx = parseInt(t.getAttribute('data-idx'), 10);
            var p = t.getAttribute('data-pathway');
            var stage = t.getAttribute('data-stage');
            var keys = state[p][idx].stage_keys || [];
            if (t.checked && keys.indexOf(stage) < 0) keys.push(stage);
            if (!t.checked) keys = keys.filter(function (k) { return k !== stage; });
            state[p][idx].stage_keys = keys;
        });

        list.addEventListener('click', function (e) {
            var btn = e.target.closest('button');
            if (!btn) return;
            var p = btn.getAttribute('data-pathway');
            var idx = parseInt(btn.getAttribute('data-idx'), 10);
            if (btn.hasAttribute('data-remove')) {
                state[p].splice(idx, 1);
                renderList(p);
                return;
            }
            var move = btn.getAttribute('data-move');
            if (move === 'up' && idx > 0) {
                var tmp = state[p][idx - 1];
                state[p][idx - 1] = state[p][idx];
                state[p][idx] = tmp;
                renderList(p);
            }
            if (move === 'down' && idx < state[p].length - 1) {
                var tmp2 = state[p][idx + 1];
                state[p][idx + 1] = state[p][idx];
                state[p][idx] = tmp2;
                renderList(p);
            }
        });
    }

    function showError(pathway, msg) {
        var el = document.getElementById(pathway === 'civilian' ? 'pathwayCivilianError' : 'pathwayMilitaryError');
        if (!el) return;
        el.style.display = msg ? 'block' : 'none';
        el.textContent = msg || '';
    }

    function save(pathway) {
        showError(pathway, '');
        var steps = renumber(state[pathway].slice());
        fetch('/admin/pathway-settings', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ pathway: pathway, steps: steps }),
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
          .then(function (res) {
              if (!res.ok) {
                  var msg = res.data.message || (res.data.errors && Object.values(res.data.errors).flat().join(' ')) || 'تعذّر الحفظ';
                  showError(pathway, msg);
                  return;
              }
              state[pathway] = res.data.steps || steps;
              renderList(pathway);
              alert(res.data.message || 'تم الحفظ');
          })
          .catch(function () { showError(pathway, 'خطأ في الاتصال'); });
    }

    function reset(pathway) {
        if (!confirm('استعادة الإعدادات الافتراضية لهذا المسار؟')) return;
        fetch('/admin/pathway-settings/reset', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ pathway: pathway }),
        }).then(function (r) { return r.json(); })
          .then(function (data) {
              state[pathway] = data.steps || [];
              renderList(pathway);
              alert(data.message || 'تمت الاستعادة');
          });
    }

    document.querySelectorAll('[data-pathway-tab]').forEach(function (tab) {
        tab.addEventListener('click', function () {
            var p = tab.getAttribute('data-pathway-tab');
            document.querySelectorAll('[data-pathway-tab]').forEach(function (t) { t.classList.toggle('active', t === tab); });
            document.getElementById('pathwayEditorCivilian').hidden = p !== 'civilian';
            document.getElementById('pathwayEditorMilitary').hidden = p !== 'military';
        });
    });

    document.getElementById('btnSaveCivilian')?.addEventListener('click', function () { save('civilian'); });
    document.getElementById('btnSaveMilitary')?.addEventListener('click', function () { save('military'); });
    document.getElementById('btnResetCivilian')?.addEventListener('click', function () { reset('civilian'); });
    document.getElementById('btnResetMilitary')?.addEventListener('click', function () { reset('military'); });
    document.getElementById('btnAddCivilianStep')?.addEventListener('click', function () {
        state.civilian.push({ key: 'step_' + (state.civilian.length + 1), label: 'خطوة جديدة', sort: state.civilian.length + 1, stage_keys: ['reception'], active: true, description: '' });
        renderList('civilian');
    });
    document.getElementById('btnAddMilitaryStep')?.addEventListener('click', function () {
        state.military.push({ key: 'step_' + (state.military.length + 1), label: 'خطوة جديدة', sort: state.military.length + 1, stage_keys: ['reception'], active: true, description: '' });
        renderList('military');
    });

    renderList('civilian');
    renderList('military');
    bindEditor('civilian');
    bindEditor('military');

    // ── Main tabs: display vs workflow ──
    document.querySelectorAll('[data-main-tab]').forEach(function (tab) {
        tab.addEventListener('click', function () {
            var m = tab.getAttribute('data-main-tab');
            document.querySelectorAll('[data-main-tab]').forEach(function (t) { t.classList.toggle('active', t === tab); });
            document.getElementById('pathwayMainDisplay').hidden = m !== 'display';
            document.getElementById('pathwayMainWorkflow').hidden = m !== 'workflow';
        });
    });

    // ── Workflow policies ──
    var wfState = {
        civilian: (boot.workflowCivilian || []).slice(),
        military: (boot.workflowMilitary || []).slice(),
    };
    var roleOpts = boot.skipRoleOptions || [];

    function renderPolicies(pathway) {
        var list = document.getElementById(pathway === 'civilian' ? 'civilianPoliciesList' : 'militaryPoliciesList');
        if (!list) return;
        list.innerHTML = wfState[pathway].map(function (p, idx) {
            var locked = !!p.locked;
            var roles = p.skip_roles || [];
            var roleChecks = roleOpts.map(function (opt) {
                var checked = roles.indexOf(opt.value) >= 0 ? ' checked' : '';
                var dis = locked ? ' disabled' : '';
                return '<label><input type="checkbox" data-wf-role data-idx="' + idx + '" data-pathway="' + pathway + '" value="' + esc(opt.value) + '"' + checked + dis + '> ' + esc(opt.label) + '</label>';
            }).join('');

            return ''
                + '<div class="pathway-step-card' + (locked ? ' pathway-step-card--locked' : '') + '">'
                + '  <div class="pathway-step-head" style="grid-template-columns:1fr;">'
                + '    <div class="pathway-step-fields">'
                + '      <strong>' + esc(p.label) + '</strong> <code style="font-size:11px;">' + esc(p.stage_key) + '</code>'
                + '      <span style="font-size:12px;color:#64748b;">' + esc(p.description || '') + '</span>'
                + '      <label class="pw-field"><span>إلزامية</span><select data-wf-field="required" data-idx="' + idx + '" data-pathway="' + pathway + '"' + (locked ? ' disabled' : '') + '>'
                + '        <option value="1"' + (p.required ? ' selected' : '') + '>إلزامية</option>'
                + '        <option value="0"' + (!p.required ? ' selected' : '') + '>اختيارية (قابلة للتخطي)</option>'
                + '      </select></label>'
                + '      <label class="pw-field"><span>تخطي تلقائي</span><select data-wf-field="auto_skip" data-idx="' + idx + '" data-pathway="' + pathway + '"' + (locked || p.required ? ' disabled' : '') + '>'
                + '        <option value="0"' + (!p.auto_skip ? ' selected' : '') + '>لا</option>'
                + '        <option value="1"' + (p.auto_skip ? ' selected' : '') + '>نعم — تخطي فوري</option>'
                + '      </select></label>'
                + (locked ? '<p style="margin:0;font-size:12px;color:#b45309;">🔒 مقفلة — حماية المنطق التجاري</p>' : '')
                + '      <div class="pw-stage-keys">' + roleChecks + '</div>'
                + '    </div>'
                + '  </div>'
                + '</div>';
        }).join('');
    }

    function bindWfList(pathway) {
        var list = document.getElementById(pathway === 'civilian' ? 'civilianPoliciesList' : 'militaryPoliciesList');
        if (!list || list.dataset.bound) return;
        list.dataset.bound = '1';
        list.addEventListener('change', function (e) {
            var t = e.target;
            var idx = parseInt(t.getAttribute('data-idx'), 10);
            var p = t.getAttribute('data-pathway');
            if (t.hasAttribute('data-wf-field')) {
                wfState[p][idx][t.getAttribute('data-wf-field')] = t.value === '1';
                if (wfState[p][idx].required) wfState[p][idx].auto_skip = false;
                renderPolicies(p);
            }
            if (t.hasAttribute('data-wf-role')) {
                var roles = wfState[p][idx].skip_roles || [];
                if (t.checked && roles.indexOf(t.value) < 0) roles.push(t.value);
                if (!t.checked) roles = roles.filter(function (r) { return r !== t.value; });
                wfState[p][idx].skip_roles = roles;
            }
        });
    }

    function saveWf(pathway) {
        var errEl = document.getElementById(pathway === 'civilian' ? 'wfCivilianError' : 'wfMilitaryError');
        if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }
        fetch('/admin/workflow-policies', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ pathway: pathway, policies: wfState[pathway] }),
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
          .then(function (res) {
              if (!res.ok) {
                  if (errEl) { errEl.style.display = 'block'; errEl.textContent = res.data.message || 'تعذّر الحفظ'; }
                  return;
              }
              wfState[pathway] = res.data.policies || wfState[pathway];
              renderPolicies(pathway);
              alert(res.data.message || 'تم الحفظ');
          });
    }

    function resetWf(pathway) {
        if (!confirm('استعادة قواعد التدفق الافتراضية؟')) return;
        fetch('/admin/workflow-policies/reset', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ pathway: pathway }),
        }).then(function (r) { return r.json(); })
          .then(function (data) {
              wfState[pathway] = data.policies || [];
              renderPolicies(pathway);
              alert(data.message || 'تمت الاستعادة');
          });
    }

    document.querySelectorAll('[data-wf-tab]').forEach(function (tab) {
        tab.addEventListener('click', function () {
            var p = tab.getAttribute('data-wf-tab');
            document.querySelectorAll('[data-wf-tab]').forEach(function (t) { t.classList.toggle('active', t === tab); });
            document.getElementById('wfEditorCivilian').hidden = p !== 'civilian';
            document.getElementById('wfEditorMilitary').hidden = p !== 'military';
        });
    });

    document.getElementById('btnSaveWfCivilian')?.addEventListener('click', function () { saveWf('civilian'); });
    document.getElementById('btnSaveWfMilitary')?.addEventListener('click', function () { saveWf('military'); });
    document.getElementById('btnResetWfCivilian')?.addEventListener('click', function () { resetWf('civilian'); });
    document.getElementById('btnResetWfMilitary')?.addEventListener('click', function () { resetWf('military'); });

    renderPolicies('civilian');
    renderPolicies('military');
    bindWfList('civilian');
    bindWfList('military');
})();
</script>
