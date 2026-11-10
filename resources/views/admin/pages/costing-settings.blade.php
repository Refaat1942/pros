@php
    $rateRows = $overhead_rate_definitions ?? [];
    $ratesSum = $overhead_rates_sum ?? 0;
    $costingModes = $costing_modes ?? [];
    $discountPolicy = $discount_policy ?? ['pathways' => [], 'types' => []];
    $discountPathways = $discount_pathways ?? [];
@endphp
<div class="section-view" id="section-costing-settings">
    <div class="panel">
        <div class="panel-header">
            <h3>⚙️ إعدادات التكاليف الإضافية</h3>
            <span class="badge" id="costingSettingsSumBadge" title="كل بند نسبة مستقلة من تكلفة المواد — مش لازم المجموع 100%">مجموع النسب {{ rtrim(rtrim(number_format((float) $ratesSum, 2, '.', ''), '0'), '.') }}%</span>
        </div>


        <form id="costingSettingsForm" class="costing-settings-form">
            @foreach ($rateRows as $row)
                <label class="costing-settings-field">
                    <span>{{ $row['label'] }}</span>
                    <div class="costing-settings-input-wrap">
                        <input type="number"
                               name="{{ $row['key'] }}"
                               min="0"
                               max="100"
                               step="0.01"
                               value="{{ $row['rate'] }}"
                               required>
                        <span>%</span>
                    </div>
                </label>
            @endforeach

            <div id="costingSettingsError" class="costing-settings-error" style="display:none;"></div>

            <div class="costing-settings-actions">
                <button type="submit" class="btn-action success">💾 حفظ الإعدادات</button>
            </div>
        </form>
    </div>

    <div class="panel">
        <div class="panel-header">
            <h3>🧮 أنماط التكاليف (طرف صناعي / صرف سريع)</h3>
            <button type="button" class="btn-action" id="btnAddCostingMode">➕ إضافة نمط</button>
        </div>


        <div id="costingModesEditor" class="costing-modes-editor"></div>

        <div id="costingModesError" class="costing-settings-error" style="display:none;"></div>

        <div class="costing-settings-actions" style="padding:0 16px 16px;">
            <button type="button" class="btn-action success" id="btnSaveCostingModes">💾 حفظ الأنماط</button>
        </div>
    </div>

    {{-- سياسة الخصومات — السوبر أدمن يحدد مين يستفيد من الخصم وأنواع الخصومات المعتمدة وحدودها. --}}
    <div class="panel" id="discountPolicyPanel">
        <div class="panel-header">
            <h3>🏷️ سياسة الخصومات</h3>
            <button type="button" class="btn-action" id="btnAddDiscountType">➕ إضافة نوع خصم</button>
        </div>
        <div style="padding:16px;">
            <p style="margin:0 0 10px;font-weight:700;">الفئات المسموح بمنحها خصماً عند الاعتماد:</p>
            <div style="display:flex;flex-wrap:wrap;gap:18px;margin-bottom:16px;">
                @foreach ($discountPathways as $key => $label)
                    <label style="display:flex;align-items:center;gap:6px;">
                        <input type="checkbox" class="discount-pathway" value="{{ $key }}" @checked($discountPolicy['pathways'][$key] ?? false)>
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            <table data-no-paginate class="stock-table" style="width:100%;">
                <thead>
                    <tr><th>نوع الخصم</th><th style="width:130px;">نسبة ثابتة %</th><th style="width:110px;">نسبة مئوية</th><th style="width:110px;">مبلغ ثابت</th><th style="width:150px;">الحد الأقصى (% من السعر)</th><th style="width:60px;"></th></tr>
                </thead>
                <tbody id="discountTypesBody"></tbody>
            </table>
            <p style="margin:10px 0 0;font-size:12px;color:var(--text-muted);">«نسبة ثابتة»: الخصم يُطبَّق بهذه النسبة تحديداً (مثل خصم 15% وخصم 25% في حسبة اللائحة). اترك «الحد الأقصى» فارغاً لعدم تقييد النسبة. يُطبَّق الخصم على سعر البيع ويُسجَّل في عرض السعر والفاتورة ومديونية الجهة.</p>
            <div id="discountPolicyError" class="costing-settings-error" style="display:none;"></div>
            <div class="costing-settings-actions">
                <button type="button" class="btn-action success" id="btnSaveDiscountPolicy">💾 حفظ سياسة الخصومات</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var types = @json($discountPolicy['types']);
    var body = document.getElementById('discountTypesBody');
    if (!body) return;
    function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
    function readRows() {
        types = Array.prototype.map.call(body.querySelectorAll('tr'), function (tr) {
            return {
                key: tr.dataset.key || '',
                label: tr.querySelector('.dt-label').value.trim(),
                allow_percent: tr.querySelector('.dt-percent').checked,
                allow_amount: tr.querySelector('.dt-amount').checked,
                max_percent: tr.querySelector('.dt-max').value === '' ? null : parseFloat(tr.querySelector('.dt-max').value),
                fixed_percent: tr.querySelector('.dt-fixed').value === '' ? null : parseFloat(tr.querySelector('.dt-fixed').value),
            };
        });
        return types;
    }
    function render() {
        body.innerHTML = types.map(function (t, i) {
            return '<tr data-key="' + esc(t.key) + '">' +
                '<td><input type="text" class="form-control dt-label" maxlength="100" value="' + esc(t.label) + '" placeholder="مثال: حالة إنسانية"></td>' +
                '<td><input type="number" class="form-control dt-fixed" min="0.01" max="99.99" step="0.01" value="' + (t.fixed_percent == null ? '' : esc(t.fixed_percent)) + '" placeholder="حرة"></td>' +
                '<td style="text-align:center;"><input type="checkbox" class="dt-percent"' + (t.allow_percent ? ' checked' : '') + '></td>' +
                '<td style="text-align:center;"><input type="checkbox" class="dt-amount"' + (t.allow_amount ? ' checked' : '') + '></td>' +
                '<td><input type="number" class="form-control dt-max" min="0.01" max="99.99" step="0.01" value="' + (t.max_percent == null ? '' : esc(t.max_percent)) + '" placeholder="بدون حد"></td>' +
                '<td><button type="button" class="btn-action danger dt-remove" data-i="' + i + '">حذف</button></td></tr>';
        }).join('');
        body.querySelectorAll('.dt-remove').forEach(function (b) {
            b.addEventListener('click', function () { readRows(); types.splice(+b.dataset.i, 1); render(); });
        });
    }
    render();
    document.getElementById('btnAddDiscountType').addEventListener('click', function () {
        readRows(); types.push({ key: '', label: '', allow_percent: true, allow_amount: true, max_percent: null, fixed_percent: null }); render();
    });
    document.getElementById('btnSaveDiscountPolicy').addEventListener('click', function () {
        var err = document.getElementById('discountPolicyError');
        var pathways = {};
        document.querySelectorAll('.discount-pathway').forEach(function (c) { pathways[c.value] = c.checked; });
        var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        fetch('/admin/discount-policy', {
            method: 'PUT', credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ pathways: pathways, types: readRows() }),
        }).then(function (r) { return r.json().then(function (j) { if (!r.ok) throw j; return j; }); })
          .then(function (res) {
              types = res.discount_policy.types; render();
              err.style.display = 'block'; err.style.background = '#dcfce7'; err.style.color = '#166534';
              err.textContent = res.message || 'تم الحفظ.';
          })
          .catch(function (e) {
              err.style.display = 'block'; err.style.background = ''; err.style.color = '';
              err.textContent = (e && e.errors) ? Object.values(e.errors)[0][0] : ((e && e.message) || 'تعذّر الحفظ.');
          });
    });
})();
</script>

<style>
    #section-costing-settings .costing-settings-hint {
        margin: 0 16px 16px;
        padding: 12px 14px;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 8px;
        color: #1e40af;
        font-size: 13px;
        line-height: 1.7;
    }
    #section-costing-settings .costing-settings-form {
        display: grid;
        gap: 14px;
        padding: 0 16px 16px;
        max-width: 720px;
    }
    #section-costing-settings .costing-settings-field {
        display: grid;
        gap: 6px;
        font-size: 13px;
        font-weight: 700;
    }
    #section-costing-settings .costing-settings-input-wrap {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    #section-costing-settings .costing-settings-input-wrap input {
        width: 120px;
        padding: 10px;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-family: inherit;
    }
    #section-costing-settings .costing-settings-error {
        padding: 10px 12px;
        background: #fee2e2;
        border-radius: 8px;
        color: #dc2626;
        font-size: 13px;
    }
    #section-costing-settings .costing-settings-actions {
        display: flex;
        justify-content: flex-end;
    }
    #section-costing-settings .costing-modes-editor {
        display: grid;
        gap: 14px;
        padding: 0 16px 8px;
    }
    #section-costing-settings .costing-mode-card {
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 10px;
        padding: 14px;
        background: var(--surface-2, #f8fafc);
    }
    #section-costing-settings .costing-mode-card__head {
        display: grid;
        grid-template-columns: 1fr 1fr 160px auto;
        gap: 10px;
        align-items: end;
    }
    @media (max-width: 720px) {
        #section-costing-settings .costing-mode-card__head { grid-template-columns: 1fr 1fr; }
    }
    #section-costing-settings .cm-field { display: grid; gap: 4px; font-size: 12px; font-weight: 700; }
    #section-costing-settings .cm-field input,
    #section-costing-settings .cm-field select {
        padding: 8px; border: 1px solid var(--border); border-radius: 8px; font-family: inherit;
    }
    #section-costing-settings .costing-mode-components { margin-top: 12px; display: grid; gap: 8px; }
    #section-costing-settings .cm-comp-row { display: grid; grid-template-columns: 1fr 120px auto; gap: 8px; align-items: center; }
    #section-costing-settings .cm-comp-row input { padding: 7px; border: 1px solid var(--border); border-radius: 8px; font-family: inherit; }
    #section-costing-settings .cm-btn-sm { padding: 6px 10px; border: 1px solid var(--border); border-radius: 8px; background: #fff; cursor: pointer; font-family: inherit; font-size: 12px; }
    #section-costing-settings .cm-btn-danger { color: #dc2626; border-color: #fecaca; }
</style>

<script>
(function () {
    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function updateSumBadge() {
        var form = document.getElementById('costingSettingsForm');
        var badge = document.getElementById('costingSettingsSumBadge');
        if (!form || !badge) return;

        var sum = 0;
        form.querySelectorAll('input[type="number"]').forEach(function (input) {
            sum += parseFloat(input.value || '0') || 0;
        });

        // للعلم بس — كل بند نسبة مستقلة من المواد، فمفيش لون أحمر لو المجموع مش 100%.
        badge.textContent = 'مجموع النسب ' + sum.toFixed(2).replace(/\.?0+$/, '') + '%';
    }

    document.getElementById('costingSettingsForm')?.addEventListener('input', updateSumBadge);

    document.getElementById('costingSettingsForm')?.addEventListener('submit', function (e) {
        e.preventDefault();

        var form = e.target;
        var err = document.getElementById('costingSettingsError');
        var payload = {};

        form.querySelectorAll('input[type="number"]').forEach(function (input) {
            payload[input.name] = parseFloat(input.value || '0');
        });

        fetch('/admin/costing-settings', {
            method: 'PUT',
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
        .then(function (data) {
            if (err) err.style.display = 'none';
            updateSumBadge();
            if (window.DashboardToast) {
                window.DashboardToast.show(data.message || 'تم الحفظ', { id: 'toast' });
            }
        })
        .catch(function (e) {
            var msg = (e && e.message) ? e.message : 'تعذّر الحفظ.';
            if (e && e.errors) {
                msg = Object.values(e.errors)[0][0] || msg;
            }
            if (err) {
                err.textContent = msg;
                err.style.display = 'block';
            }
        });
    });

    updateSumBadge();

    // ===== أنماط التكاليف (modes/components) =====
    var MODES = @json($costingModes);
    var editor = document.getElementById('costingModesEditor');

    function h(tag, attrs, children) {
        var el = document.createElement(tag);
        attrs = attrs || {};
        Object.keys(attrs).forEach(function (k) {
            if (k === 'class') el.className = attrs[k];
            else if (k === 'value') el.value = attrs[k];
            else el.setAttribute(k, attrs[k]);
        });
        (children || []).forEach(function (c) {
            el.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
        });
        return el;
    }

    function field(label, input) {
        return h('label', { class: 'cm-field' }, [label, input]);
    }

    function compRow(comp) {
        comp = comp || { label: '', rate: 0 };
        var lbl = h('input', { type: 'text', class: 'cm-comp-label', value: comp.label || '', placeholder: 'اسم المكوّن' });
        var rate = h('input', { type: 'number', step: '0.01', min: '0', class: 'cm-comp-rate', value: comp.rate != null ? comp.rate : 0, placeholder: '%' });
        var del = h('button', { type: 'button', class: 'cm-btn-sm cm-btn-danger' }, ['حذف']);
        var row = h('div', { class: 'cm-comp-row' }, [lbl, rate, del]);
        del.addEventListener('click', function () { row.remove(); });
        return row;
    }

    function modeCard(mode) {
        mode = mode || {};
        var keyInput = h('input', { type: 'text', class: 'cm-key', value: mode.key || '', placeholder: 'مفتاح فريد (limb)' });
        var labelInput = h('input', { type: 'text', class: 'cm-label', value: mode.label || '', placeholder: 'اسم النمط' });
        var profitInput = h('input', { type: 'number', step: '0.01', min: '0', class: 'cm-profit', value: mode.profit_rate != null ? mode.profit_rate : 0 });

        var hasComp = h('input', { type: 'checkbox', class: 'cm-has-components' });
        if (mode.has_components) hasComp.checked = true;
        var hasCompWrap = h('label', { class: 'cm-field' }, ['له مكوّنات', hasComp]);

        var del = h('button', { type: 'button', class: 'cm-btn-sm cm-btn-danger' }, ['🗑 حذف النمط']);

        var head = h('div', { class: 'costing-mode-card__head' }, [
            field('اسم النمط', labelInput),
            field('المفتاح', keyInput),
            field('نسبة الربح %', profitInput),
            hasCompWrap,
        ]);

        var compsWrap = h('div', { class: 'costing-mode-components' }, []);
        (mode.components || []).forEach(function (c) { compsWrap.appendChild(compRow(c)); });
        var addComp = h('button', { type: 'button', class: 'cm-btn-sm' }, ['➕ مكوّن']);
        addComp.addEventListener('click', function () { compsWrap.appendChild(compRow()); });

        var compsSection = h('div', {}, [compsWrap, addComp]);

        function syncCompVisibility() {
            compsSection.style.display = hasComp.checked ? 'block' : 'none';
        }
        hasComp.addEventListener('change', syncCompVisibility);
        syncCompVisibility();

        var card = h('div', { class: 'costing-mode-card' }, [head, compsSection, h('div', { style: 'margin-top:10px;' }, [del])]);
        del.addEventListener('click', function () { card.remove(); });
        return card;
    }

    function renderModes() {
        if (!editor) return;
        editor.innerHTML = '';
        (MODES || []).forEach(function (m) { editor.appendChild(modeCard(m)); });
        if (!MODES || !MODES.length) editor.appendChild(modeCard());
    }

    function collectModes() {
        var out = [];
        editor.querySelectorAll('.costing-mode-card').forEach(function (card, i) {
            var comps = [];
            card.querySelectorAll('.cm-comp-row').forEach(function (row, ci) {
                comps.push({
                    label: row.querySelector('.cm-comp-label').value.trim(),
                    rate: parseFloat(row.querySelector('.cm-comp-rate').value || '0') || 0,
                    sort: ci,
                });
            });
            var hasComp = card.querySelector('.cm-has-components').checked;
            out.push({
                key: card.querySelector('.cm-key').value.trim(),
                label: card.querySelector('.cm-label').value.trim(),
                profit_rate: parseFloat(card.querySelector('.cm-profit').value || '0') || 0,
                has_components: hasComp,
                sort: i,
                components: hasComp ? comps : [],
            });
        });
        return out;
    }

    document.getElementById('btnAddCostingMode')?.addEventListener('click', function () {
        editor.appendChild(modeCard());
    });

    document.getElementById('btnSaveCostingModes')?.addEventListener('click', function () {
        var err = document.getElementById('costingModesError');
        fetch('/admin/costing-modes', {
            method: 'PUT',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ modes: collectModes() }),
        })
        .then(function (r) { return r.ok ? r.json() : r.json().then(function (j) { throw j; }); })
        .then(function (data) {
            if (err) err.style.display = 'none';
            if (data.costing_modes) { MODES = data.costing_modes; renderModes(); }
            if (window.DashboardToast) {
                window.DashboardToast.show(data.message || 'تم حفظ الأنماط', { id: 'toast' });
            }
        })
        .catch(function (e) {
            var msg = (e && e.message) ? e.message : 'تعذّر حفظ الأنماط.';
            if (e && e.errors) { msg = Object.values(e.errors)[0][0] || msg; }
            if (err) { err.textContent = msg; err.style.display = 'block'; }
        });
    });

    renderModes();
})();
</script>
