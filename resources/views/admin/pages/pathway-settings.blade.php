@php
    $designer = [
        'civilian' => $pathway_civilian_steps ?? ($civilian ?? []),
        'military' => $pathway_military_steps ?? ($military ?? []),
        'departments' => $departments ?? [],
        'skip_roles' => $skip_roles ?? ($workflow_skip_role_options ?? []),
        'handlers' => $handlers ?? [],
    ];
@endphp
<div class="section-view pathway-designer-page" id="section-pathway-settings">
    <div class="panel">
        <div class="panel-header">
            <h3>🧭 مصمم مسار العمل</h3>
        </div>

        <p class="pathway-designer-intro">
            <strong>شاشة واحدة — من يعمل ماذا وإيه اللي بعدها.</strong><br>
            ✅ <strong>تقدر تعدّل:</strong> القسم المسؤول — ماذا يفعل — ماذا يحدث بعد الإكمال — من يصدر أمر الشغل.<br>
            🔒 <strong>مقفول = لا يُتخطى فقط</strong> (مثلاً: ماينفعش نتخطى عرض السعر أو التصنيع) — لكن تقدر تغيّر الوصف والقسم المسؤول.<br>
            ⏭️ <strong>الخطوات اللي تقدر تخطيها:</strong> الكشف الطبي — المعدلات (اختياري).
        </p>

        <div class="pathway-designer-tabs">
            <button type="button" class="pathway-tab active" data-pathway-select="civilian">🌐 المسار المدني</button>
            <button type="button" class="pathway-tab" data-pathway-select="military">🪖 المسار العسكري</button>
            <button type="button" class="btn-action" id="btnResetPathway">↩️ استعادة الافتراضي</button>
        </div>

        <div id="pathwayTimeline" class="pathway-timeline"></div>
        <div id="pathwayDesignerError" class="pathway-designer-error" style="display:none;"></div>

        <div class="pathway-designer-actions">
            <button type="button" class="btn-action success" id="btnSavePathway">💾 حفظ المسار</button>
        </div>
    </div>
</div>

<script type="application/json" id="pathwayDesignerBootstrap">
{!! json_encode(array_merge($designer, ['csrf' => csrf_token()]), JSON_UNESCAPED_UNICODE) !!}
</script>

<style>
    .pathway-designer-page .pathway-designer-intro {
        margin: 0 16px 16px;
        padding: 14px 16px;
        background: linear-gradient(135deg, #eff6ff, #f0fdf4);
        border: 1px solid #bfdbfe;
        border-radius: 10px;
        color: #1e3a5f;
        font-size: 14px;
        line-height: 1.8;
    }
    .pathway-designer-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
        padding: 0 16px 16px;
    }
    .pathway-designer-page .pathway-tab {
        padding: 8px 16px;
        border: 1px solid var(--border);
        border-radius: 999px;
        background: #fff;
        cursor: pointer;
        font-family: inherit;
        font-size: 13px;
        font-weight: 700;
    }
    .pathway-designer-page .pathway-tab.active {
        background: #1e40af;
        color: #fff;
        border-color: #1e40af;
    }
    .pathway-timeline {
        padding: 0 16px 16px;
        display: grid;
        gap: 12px;
        direction: rtl;
    }
    .pathway-timeline::before { display: none; }
    .pf-card {
        position: relative;
        padding: 16px;
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,.04);
        direction: rtl;
    }
    .pf-card--locked {
        background: #fffbeb;
        border-color: #fcd34d;
    }
    .pf-card-head {
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 14px;
        margin-bottom: 14px;
        padding-bottom: 12px;
        border-bottom: 1px solid #e2e8f0;
    }
    .pf-num {
        flex-shrink: 0;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #1e40af;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 16px;
    }
    .pf-card--locked .pf-num { background: #b45309; }
    .pf-head-text { flex: 1; min-width: 0; }
    .pf-title { margin: 0 0 4px; font-size: 17px; font-weight: 800; line-height: 1.4; }
    .pf-step-hint { margin: 0; font-size: 12px; color: #64748b; }
    .pf-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 10px;
    }
    .pf-field {
        display: grid;
        gap: 4px;
        font-size: 12px;
        font-weight: 700;
        color: #475569;
    }
    .pf-field input,
    .pf-field select,
    .pf-field textarea {
        padding: 8px;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-family: inherit;
        font-weight: 400;
        font-size: 13px;
    }
    .pf-field textarea { min-height: 56px; resize: vertical; }
    .pf-field--wide { grid-column: 1 / -1; }
    .pf-handlers {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px dashed #e2e8f0;
        display: grid;
        gap: 8px;
    }
    .pf-handlers-title { font-size: 12px; font-weight: 800; color: #1e40af; margin: 0; }
    .pf-skip-row {
        margin-top: 10px;
        padding: 10px;
        background: #f8fafc;
        border-radius: 8px;
        display: grid;
        gap: 8px;
    }
    .pf-skip-roles {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 12px;
        font-size: 12px;
    }
    .pf-lock-note {
        margin: 0;
        padding: 10px 12px;
        font-size: 13px;
        color: #92400e;
        font-weight: 600;
        background: #fef3c7;
        border-radius: 8px;
        line-height: 1.6;
    }
    .pf-skip-title { margin: 0 0 6px; font-size: 13px; font-weight: 800; color: #334155; }
    .pathway-designer-error {
        margin: 0 16px;
        padding: 10px 12px;
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 8px;
        color: #b91c1c;
    }
    .pathway-designer-actions { padding: 0 16px 16px; }
</style>

<script>
(function () {
    var boot = JSON.parse(document.getElementById('pathwayDesignerBootstrap').textContent || '{}');
    var state = {
        civilian: (boot.civilian || []).slice(),
        military: (boot.military || []).slice(),
    };
    var activePathway = 'civilian';
    var depts = boot.departments || [];
    var skipRoles = boot.skip_roles || [];
    var handlerDefs = boot.handlers || [];
    var csrf = boot.csrf || '';

    function esc(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    function deptOptions(selected) {
        return depts.map(function (d) {
            return '<option value="' + esc(d.value) + '"' + (selected === d.value ? ' selected' : '') + '>' + esc(d.icon + ' ' + d.label) + '</option>';
        }).join('');
    }

    function render() {
        var list = state[activePathway];
        var el = document.getElementById('pathwayTimeline');
        if (!el) return;

        el.innerHTML = list.map(function (step, idx) {
            step.sort = idx + 1;
            var locked = !!step.locked;
            var handlers = step.handlers || {};
            var handlerHtml = '';
            var relevantHandlers = handlerDefs.filter(function (h) {
                return step.key === 'operations' || step.key === 'cashier' || step.key === 'manufacturing' || handlers[h.key];
            });

            if (relevantHandlers.length) {
                handlerHtml = '<div class="pf-handlers"><p class="pf-handlers-title">⚙️ من ينفّذ الإجراء؟</p>';
                relevantHandlers.forEach(function (h) {
                    handlerHtml += '<label class="pf-field"><span>' + esc(h.label) + '</span><select data-h="' + esc(h.key) + '" data-idx="' + idx + '">'
                        + deptOptions(handlers[h.key] || step.owner_department || 'operations')
                        + '</select></label>';
                });
                handlerHtml += '</div>';
            }

            var roleChecks = skipRoles.map(function (r) {
                var checked = (step.skip_roles || []).indexOf(r.value) >= 0 ? ' checked' : '';
                return '<label><input type="checkbox" data-role="' + esc(r.value) + '" data-idx="' + idx + '"' + checked + '> ' + esc(r.label) + '</label>';
            }).join('');

            var skipBlock = '';
            if (step.can_skip !== false && !locked) {
                skipBlock = '<div class="pf-skip-row">'
                    + '<p class="pf-skip-title">⏭️ تخطي هذه الخطوة (اختياري)</p>'
                    + '<label class="pf-field"><span>هل يمكن تخطيها؟</span><select data-f="required" data-idx="' + idx + '">'
                    + '<option value="1"' + (step.required !== false ? ' selected' : '') + '>لا — إلزامية</option>'
                    + '<option value="0"' + (step.required === false ? ' selected' : '') + '>نعم — يمكن تخطيها</option>'
                    + '</select></label>'
                    + '<label class="pf-field"><span>تخطي تلقائي؟</span><select data-f="auto_skip" data-idx="' + idx + '"' + (step.required !== false ? ' disabled' : '') + '>'
                    + '<option value="0"' + (!step.auto_skip ? ' selected' : '') + '>لا</option>'
                    + '<option value="1"' + (step.auto_skip ? ' selected' : '') + '>نعم — تخطي فوري</option>'
                    + '</select></label>'
                    + '<div class="pf-skip-roles">' + roleChecks + '</div>'
                    + '</div>';
            } else if (locked && step.lock_reason) {
                skipBlock = '<p class="pf-lock-note">🔒 ' + esc(step.lock_reason) + '</p>';
            }

            return ''
                + '<article class="pf-card' + (locked ? ' pf-card--locked' : '') + '" data-idx="' + idx + '">'
                + '<div class="pf-card-head">'
                + '  <div class="pf-num">' + step.sort + '</div>'
                + '  <div class="pf-head-text">'
                + '    <h4 class="pf-title">' + esc(step.label) + '</h4>'
                + '    <p class="pf-step-hint">الخطوة ' + step.sort + ' في المسار</p>'
                + '  </div>'
                + '</div>'
                + '<div class="pf-grid">'
                + '<label class="pf-field"><span>👤 القسم المسؤول</span><select data-f="owner_department" data-idx="' + idx + '">' + deptOptions(step.owner_department) + '</select></label>'
                + '<label class="pf-field pf-field--wide"><span>📋 ماذا يفعل هنا؟</span><textarea data-f="action_summary" data-idx="' + idx + '" placeholder="اكتب وظيفة هذا القسم في هذه المرحلة…">' + esc(step.action_summary || '') + '</textarea></label>'
                + '<label class="pf-field pf-field--wide"><span>➡️ بعد الإكمال — ماذا يحدث؟</span><input type="text" data-f="on_complete" data-idx="' + idx + '" value="' + esc(step.on_complete || '') + '" placeholder="مثال: ينتقل لمكتب التشغيل"></label>'
                + '</div>'
                + handlerHtml
                + skipBlock
                + '</article>';
        }).join('');

        bindEvents();
    }

    function bindEvents() {
        var el = document.getElementById('pathwayTimeline');
        if (!el || el.dataset.bound) return;
        el.dataset.bound = '1';

        el.addEventListener('input', function (e) {
            var t = e.target;
            var idx = parseInt(t.getAttribute('data-idx'), 10);
            var field = t.getAttribute('data-f');
            if (field) {
                if (field === 'required' || field === 'auto_skip') {
                    state[activePathway][idx][field] = t.value === '1';
                } else {
                    state[activePathway][idx][field] = t.value;
                }
                if (field === 'required' && t.value === '1') {
                    state[activePathway][idx].auto_skip = false;
                }
                render();
            }
        });

        el.addEventListener('change', function (e) {
            var t = e.target;
            var idx = parseInt(t.getAttribute('data-idx'), 10);
            if (t.hasAttribute('data-h')) {
                state[activePathway][idx].handlers = state[activePathway][idx].handlers || {};
                state[activePathway][idx].handlers[t.getAttribute('data-h')] = t.value;
            }
            if (t.hasAttribute('data-role')) {
                var roles = state[activePathway][idx].skip_roles || [];
                if (t.checked && roles.indexOf(t.getAttribute('data-role')) < 0) roles.push(t.getAttribute('data-role'));
                if (!t.checked) roles = roles.filter(function (r) { return r !== t.getAttribute('data-role'); });
                state[activePathway][idx].skip_roles = roles;
            }
        });
    }

    document.querySelectorAll('[data-pathway-select]').forEach(function (tab) {
        tab.addEventListener('click', function () {
            activePathway = tab.getAttribute('data-pathway-select');
            document.querySelectorAll('[data-pathway-select]').forEach(function (t) {
                t.classList.toggle('active', t === tab);
            });
            document.getElementById('pathwayTimeline').dataset.bound = '';
            render();
        });
    });

    document.getElementById('btnSavePathway')?.addEventListener('click', function () {
        var err = document.getElementById('pathwayDesignerError');
        if (err) { err.style.display = 'none'; }
        fetch('/admin/pathway-settings', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ pathway: activePathway, steps: state[activePathway] }),
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
          .then(function (res) {
              if (!res.ok) {
                  if (err) { err.style.display = 'block'; err.textContent = res.data.message || 'تعذّر الحفظ'; }
                  return;
              }
              state[activePathway] = res.data.steps || state[activePathway];
              document.getElementById('pathwayTimeline').dataset.bound = '';
              render();
              alert(res.data.message || 'تم الحفظ');
          });
    });

    document.getElementById('btnResetPathway')?.addEventListener('click', function () {
        if (!confirm('استعادة المسار الافتراضي؟')) return;
        fetch('/admin/pathway-settings/reset', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ pathway: activePathway }),
        }).then(function (r) { return r.json(); })
          .then(function (data) {
              state[activePathway] = data.steps || [];
              document.getElementById('pathwayTimeline').dataset.bound = '';
              render();
              alert(data.message || 'تمت الاستعادة');
          });
    });

    render();
})();
</script>
