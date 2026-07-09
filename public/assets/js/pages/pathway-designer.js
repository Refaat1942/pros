/**
 * مصمم مسار العمل — جدول ثلاثي (مدني / عسكري / جهات) + محرر خلية.
 */
(function () {
    var bootEl = document.getElementById('pathwayDesignerBootstrap');
    if (!bootEl) return;

    var boot = JSON.parse(bootEl.textContent || '{}');
    var state = {
        civilian: (boot.civilian || []).slice(),
        military: (boot.military || []).slice(),
        entity: (boot.entity || []).slice(),
    };
    var pathwayMeta = {
        civilian: { label: 'مدني (نقدي)', icon: '🌐' },
        military: { label: 'عسكري', icon: '🪖' },
        entity: { label: 'جهات', icon: '🏢' },
    };
    var pathwayOrder = ['civilian', 'military', 'entity'];
    var activePathway = 'civilian';
    var activeStepIdx = null;
    var depts = boot.departments || [];
    var skipRoles = boot.skip_roles || [];
    var handlerDefs = boot.handlers || [];
    var csrf = boot.csrf || '';
    var maxRows = 13;

    var handlerKeysByStep = {
        operations_wo: ['work_order'],
        operations_release: [],
        entity_return: ['entity_approval'],
        quote: [],
        cashier: ['collect_payment'],
        warehouse: ['barcode_dispense'],
        workshop: ['production'],
        operations: ['work_order', 'entity_approval'],
        manufacturing: ['barcode_dispense', 'production'],
    };

    function esc(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    function deptOptions(selected) {
        return depts.map(function (d) {
            return '<option value="' + esc(d.value) + '"' + (selected === d.value ? ' selected' : '') + '>'
                + esc(d.icon + ' ' + d.label) + '</option>';
        }).join('');
    }

    function deptLabel(value) {
        for (var i = 0; i < depts.length; i++) {
            if (depts[i].value === value) return depts[i].label;
        }
        return value || '—';
    }

    function syncNextStepMeta(pathwayKey) {
        var list = state[pathwayKey];
        list.forEach(function (step, idx) {
            var nextKey = step.next_step_key;
            if (!nextKey) {
                nextKey = (list[idx + 1] && list[idx + 1].key) || '_completed';
            }
            step.next_step_key = nextKey;
            if (nextKey === '_completed') {
                step.next_step_label = 'إغلاق المسار';
                step.on_complete = 'الحالة مكتملة';
                return;
            }
            var next = null;
            for (var i = 0; i < list.length; i++) {
                if (list[i].key === nextKey) { next = list[i]; break; }
            }
            step.next_step_label = next ? next.label : nextKey;
            step.on_complete = 'ينتقل إلى ' + step.next_step_label;
        });
    }

    function syncAllPathways() {
        pathwayOrder.forEach(syncNextStepMeta);
    }

    function nextStepOptions(list, currentIdx, selectedKey) {
        var html = '';
        list.forEach(function (s, i) {
            if (i === currentIdx) return;
            var sel = selectedKey === s.key ? ' selected' : '';
            html += '<option value="' + esc(s.key) + '"' + sel + '>' + esc(s.sort + '. ' + s.label) + '</option>';
        });
        var doneSel = selectedKey === '_completed' ? ' selected' : '';
        html += '<option value="_completed"' + doneSel + '>✅ إغلاق المسار (مكتمل)</option>';
        return html;
    }

    function labelsMatchAcrossRow(rowIdx) {
        var labels = [];
        pathwayOrder.forEach(function (pk) {
            var step = state[pk][rowIdx];
            if (step) labels.push(String(step.label || '').trim());
        });
        if (labels.length < 2) return false;
        var first = labels[0];
        return labels.every(function (l) { return l === first; });
    }

    function renderMatrix() {
        var wrap = document.getElementById('pathwayMatrixWrap');
        if (!wrap) return;

        syncAllPathways();

        var body = '';
        for (var row = 0; row < maxRows; row++) {
            var shared = row < 5 && labelsMatchAcrossRow(row);
            body += '<tr><td class="pathway-matrix__num">' + (row + 1) + '</td>';

            if (shared) {
                var step = state.civilian[row] || state.military[row] || state.entity[row];
                var label = step ? step.label : '—';
                body += '<td class="pathway-matrix__cell pathway-matrix__cell--shared" colspan="3">'
                    + '<button type="button" class="pathway-matrix__btn pathway-matrix__btn--shared" data-shared-row="' + row + '">'
                    + esc(label)
                    + '<span class="pathway-matrix__btn-sub">مشترك — اضغط للتعديل (مدني)</span>'
                    + '</button></td>';
            } else {
                pathwayOrder.forEach(function (pk) {
                    var st = state[pk][row];
                    var selected = pk === activePathway && activeStepIdx === row;
                    if (!st) {
                        body += '<td class="pathway-matrix__cell"><span class="pathway-matrix__btn is-empty">—</span></td>';
                        return;
                    }
                    var cls = 'pathway-matrix__btn' + (selected ? ' is-selected' : '') + (st.locked ? ' is-locked' : '');
                    var badge = st.auto_skip ? '<span class="pathway-matrix__badge">تخطي تلقائي</span>' : '';
                    body += '<td class="pathway-matrix__cell">'
                        + '<button type="button" class="' + cls + '" data-pathway="' + pk + '" data-idx="' + row + '">'
                        + esc(st.label)
                        + '<span class="pathway-matrix__btn-sub">' + esc(deptLabel(st.owner_department)) + '</span>'
                        + badge
                        + '</button></td>';
                });
            }
            body += '</tr>';
        }

        wrap.innerHTML = ''
            + '<table class="pathway-matrix" dir="rtl">'
            + '<thead><tr>'
            + '<th>#</th>'
            + '<th>🌐 مدني</th>'
            + '<th>🪖 عسكري</th>'
            + '<th>🏢 جهات</th>'
            + '</tr></thead>'
            + '<tbody>' + body + '</tbody>'
            + '</table>';

        wrap.querySelectorAll('[data-pathway]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                selectCell(btn.getAttribute('data-pathway'), parseInt(btn.getAttribute('data-idx'), 10));
            });
        });
        wrap.querySelectorAll('[data-shared-row]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                selectCell('civilian', parseInt(btn.getAttribute('data-shared-row'), 10));
            });
        });
    }

    function selectCell(pathway, idx) {
        if (!state[pathway] || !state[pathway][idx]) return;
        activePathway = pathway;
        activeStepIdx = idx;
        updateHint();
        render();
    }

    function updateHint() {
        var hint = document.getElementById('pathwayEditHint');
        if (!hint) return;
        if (activeStepIdx === null) {
            hint.textContent = 'اختر خلية من الجدول للتعديل';
            return;
        }
        var meta = pathwayMeta[activePathway] || { label: activePathway, icon: '' };
        var step = state[activePathway][activeStepIdx];
        hint.textContent = 'تعديل: ' + meta.icon + ' ' + meta.label + ' — خطوة ' + (activeStepIdx + 1) + ': ' + (step ? step.label : '');
    }

    function renderFlowMap(list) {
        var el = document.getElementById('pathwayFlowMap');
        if (!el || !list.length) {
            if (el) el.innerHTML = '';
            return;
        }

        var byKey = {};
        list.forEach(function (s) { byKey[s.key] = s; });

        var chain = [];
        var current = list[0];
        var seen = {};
        while (current && !seen[current.key] && chain.length <= list.length) {
            seen[current.key] = true;
            chain.push(current);
            if (!current.next_step_key || current.next_step_key === '_completed') break;
            current = byKey[current.next_step_key];
            if (!current) break;
        }

        var meta = pathwayMeta[activePathway] || { label: activePathway };
        var chips = chain.map(function (s, i) {
            var arrow = (i < chain.length - 1 && s.next_step_key !== '_completed')
                ? '<span class="pf-flow-arrow">←</span>' : '';
            return '<span class="pf-flow-chip"><b>' + s.sort + '</b> ' + esc(s.label) + '</span>' + arrow;
        }).join('');

        el.innerHTML = '<p class="pf-flow-title">📍 معاينة مسار ' + esc(meta.label) + '</p><div class="pf-flow-strip">' + chips + '</div>';
    }

    function relevantHandlers(step) {
        var keys = handlerKeysByStep[step.key] || [];
        var handlers = step.handlers || {};
        return handlerDefs.filter(function (h) {
            return keys.indexOf(h.key) >= 0 || handlers[h.key];
        });
    }

    function renderEditor() {
        var editor = document.getElementById('pathwayStepEditor');
        if (!editor) return;

        if (activeStepIdx === null || !state[activePathway][activeStepIdx]) {
            editor.hidden = true;
            editor.innerHTML = '';
            return;
        }

        editor.hidden = false;
        var list = state[activePathway];
        var step = list[activeStepIdx];
        var idx = activeStepIdx;
        step.sort = idx + 1;
        var locked = !!step.locked;
        var handlers = step.handlers || {};
        var handlerHtml = '';
        var relHandlers = relevantHandlers(step);

        if (relHandlers.length) {
            handlerHtml = '<div class="pf-handlers"><p class="pf-handlers-title">⚙️ من ينفّذ الإجراء؟</p>';
            relHandlers.forEach(function (h) {
                handlerHtml += '<label class="pf-field"><span>' + esc(h.label) + '</span><select data-h="' + esc(h.key) + '">'
                    + deptOptions(handlers[h.key] || step.owner_department || 'operations')
                    + '</select></label>';
            });
            handlerHtml += '</div>';
        }

        var roleChecks = skipRoles.map(function (r) {
            var checked = (step.skip_roles || []).indexOf(r.value) >= 0 ? ' checked' : '';
            return '<label><input type="checkbox" data-role="' + esc(r.value) + '"' + checked + '> ' + esc(r.label) + '</label>';
        }).join('');

        var skipBlock = '';
        if (step.can_skip !== false && !locked) {
            skipBlock = '<div class="pf-skip-row">'
                + '<p class="pf-skip-title">⏭️ تخطي هذه الخطوة (اختياري)</p>'
                + '<label class="pf-field"><span>هل يمكن تخطيها؟</span><select data-f="required">'
                + '<option value="1"' + (step.required !== false ? ' selected' : '') + '>لا — إلزامية</option>'
                + '<option value="0"' + (step.required === false ? ' selected' : '') + '>نعم — يمكن تخطيها</option>'
                + '</select></label>'
                + '<label class="pf-field"><span>تخطي تلقائي؟</span><select data-f="auto_skip"' + (step.required !== false ? ' disabled' : '') + '>'
                + '<option value="0"' + (!step.auto_skip ? ' selected' : '') + '>لا</option>'
                + '<option value="1"' + (step.auto_skip ? ' selected' : '') + '>نعم — تخطي فوري</option>'
                + '</select></label>'
                + '<div class="pf-skip-roles">' + roleChecks + '</div>'
                + '</div>';
        } else if (locked && step.lock_reason) {
            skipBlock = '<p class="pf-lock-note">🔒 ' + esc(step.lock_reason) + '</p>';
        }

        var meta = pathwayMeta[activePathway] || { label: activePathway, icon: '' };

        editor.innerHTML = ''
            + '<article class="pf-card' + (locked ? ' pf-card--locked' : '') + '">'
            + '<div class="pf-card-head">'
            + '  <div class="pf-num">' + step.sort + '</div>'
            + '  <div class="pf-head-text">'
            + '    <h4 class="pf-title">' + esc(meta.icon + ' ' + meta.label + ' — ' + step.label) + '</h4>'
            + '    <p class="pf-step-hint">👤 ' + esc(deptLabel(step.owner_department)) + '</p>'
            + '  </div>'
            + '</div>'
            + '<div class="pf-grid">'
            + '<label class="pf-field pf-field--wide"><span>🏷️ اسم الخطوة (يظهر في الجدول)</span><input type="text" data-f="label" value="' + esc(step.label) + '"></label>'
            + '<label class="pf-field"><span>👤 القسم المسؤول</span><select data-f="owner_department">' + deptOptions(step.owner_department) + '</select></label>'
            + '<label class="pf-field pf-field--wide"><span>➡️ بعد الإكمال — ينتقل إلى</span><select data-f="next_step_key">' + nextStepOptions(list, idx, step.next_step_key) + '</select></label>'
            + '<label class="pf-field pf-field--wide"><span>📋 ماذا يفعل هنا؟</span><textarea data-f="action_summary" placeholder="اكتب وظيفة هذا القسم في هذه المرحلة…">' + esc(step.action_summary || '') + '</textarea></label>'
            + '</div>'
            + handlerHtml
            + skipBlock
            + '</article>';

        bindEditorEvents();
    }

    function bindEditorEvents() {
        var editor = document.getElementById('pathwayStepEditor');
        if (!editor || editor.dataset.bound) return;
        editor.dataset.bound = '1';

        function applyField(field, value) {
            if (activeStepIdx === null) return;
            var step = state[activePathway][activeStepIdx];
            if (!step) return;
            if (field === 'required' || field === 'auto_skip') {
                step[field] = value === '1' || value === true;
            } else {
                step[field] = value;
            }
            if (field === 'required' && step.required) {
                step.auto_skip = false;
            }
            render();
        }

        editor.addEventListener('input', function (e) {
            var t = e.target;
            var field = t.getAttribute('data-f');
            if (field) applyField(field, t.value);
        });

        editor.addEventListener('change', function (e) {
            var t = e.target;
            var field = t.getAttribute('data-f');
            if (field) {
                applyField(field, t.value);
                return;
            }
            if (t.hasAttribute('data-h') && activeStepIdx !== null) {
                state[activePathway][activeStepIdx].handlers = state[activePathway][activeStepIdx].handlers || {};
                state[activePathway][activeStepIdx].handlers[t.getAttribute('data-h')] = t.value;
            }
            if (t.hasAttribute('data-role') && activeStepIdx !== null) {
                var roles = state[activePathway][activeStepIdx].skip_roles || [];
                if (t.checked && roles.indexOf(t.getAttribute('data-role')) < 0) roles.push(t.getAttribute('data-role'));
                if (!t.checked) roles = roles.filter(function (r) { return r !== t.getAttribute('data-role'); });
                state[activePathway][activeStepIdx].skip_roles = roles;
            }
        });
    }

    function render() {
        renderMatrix();
        renderFlowMap(state[activePathway]);
        renderEditor();
    }

    function savePathway(pathway) {
        return fetch('/admin/pathway-settings', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ pathway: pathway, steps: state[pathway] }),
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); });
    }

    function resetPathway(pathway) {
        return fetch('/admin/pathway-settings/reset', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ pathway: pathway }),
        }).then(function (r) { return r.json(); });
    }

    document.getElementById('btnSavePathway')?.addEventListener('click', function () {
        var err = document.getElementById('pathwayDesignerError');
        if (err) err.style.display = 'none';
        savePathway(activePathway).then(function (res) {
            if (!res.ok) {
                if (err) { err.style.display = 'block'; err.textContent = res.data.message || 'تعذّر الحفظ'; }
                return;
            }
            state[activePathway] = res.data.steps || state[activePathway];
            document.getElementById('pathwayStepEditor').dataset.bound = '';
            render();
            alert(res.data.message || 'تم الحفظ');
        });
    });

    document.getElementById('btnSaveAllPathways')?.addEventListener('click', function () {
        var err = document.getElementById('pathwayDesignerError');
        if (err) err.style.display = 'none';
        var chain = Promise.resolve();
        pathwayOrder.forEach(function (pk) {
            chain = chain.then(function () {
                return savePathway(pk).then(function (res) {
                    if (!res.ok) throw new Error(res.data.message || 'تعذّر حفظ ' + pk);
                    state[pk] = res.data.steps || state[pk];
                });
            });
        });
        chain.then(function () {
            document.getElementById('pathwayStepEditor').dataset.bound = '';
            render();
            alert('تم حفظ المسارات الثلاثة.');
        }).catch(function (e) {
            if (err) { err.style.display = 'block'; err.textContent = e.message || 'تعذّر الحفظ'; }
        });
    });

    document.getElementById('btnResetPathway')?.addEventListener('click', function () {
        if (!confirm('استعادة المسار الحالي للافتراضي؟')) return;
        resetPathway(activePathway).then(function (data) {
            state[activePathway] = data.steps || [];
            document.getElementById('pathwayStepEditor').dataset.bound = '';
            render();
            alert(data.message || 'تمت الاستعادة');
        });
    });

    document.getElementById('btnResetAllPathways')?.addEventListener('click', function () {
        if (!confirm('استعادة المسارات الثلاثة للافتراضي؟')) return;
        var chain = Promise.resolve();
        pathwayOrder.forEach(function (pk) {
            chain = chain.then(function () {
                return resetPathway(pk).then(function (data) {
                    state[pk] = data.steps || [];
                });
            });
        });
        chain.then(function () {
            document.getElementById('pathwayStepEditor').dataset.bound = '';
            render();
            alert('تمت استعادة المسارات الثلاثة.');
        });
    });

    if (state.civilian.length) {
        selectCell('civilian', 0);
    } else {
        render();
    }
})();
