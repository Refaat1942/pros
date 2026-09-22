(function () {
  if (document.body.dataset.activePage !== 'stock-uom-settings') return;

  var wrap = document.getElementById('stockUomSettingsWrap');
  var errEl = document.getElementById('stockUomSettingsError');
  var builtin = window.__STOCK_UOM_BUILTIN || {};
  var customRows = Array.isArray(window.__STOCK_UOM_CUSTOM) ? window.__STOCK_UOM_CUSTOM.slice() : [];

  function esc(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }

  function renderBuiltin() {
    var html = '<h4 style="margin:0 0 8px;font-size:14px;">قوالب افتراضية (للقراءة)</h4>';
    Object.keys(builtin).forEach(function (key) {
      var p = builtin[key];
      html += '<div class="stock-uom-profile-card is-builtin">' +
        '<div><strong>' + esc(p.label || key) + '</strong><div style="font-size:12px;color:var(--text-muted);">' + esc(key) + '</div></div>' +
        '<div>توريد: ' + esc(p.supply_uom || '—') + '</div>' +
        '<div>معامل: ' + esc(p.units_per_supply_unit) + '</div>' +
        '<div>مخزن: ' + esc(p.base_uom_hint || '—') + '</div>' +
        '</div>';
    });
    return html;
  }

  function renderCustom() {
    var html = '<h4 style="margin:16px 0 8px;font-size:14px;">قوالب مخصصة (قابلة للتعديل)</h4>';
    if (!customRows.length) {
      html += '<p style="color:var(--text-muted);font-size:13px;">لا توجد قوالب مخصصة — اضغط «قالب مخصص».</p>';
    }
    customRows.forEach(function (row, idx) {
      html += '<div class="stock-uom-profile-card" data-idx="' + idx + '">' +
        '<label>المفتاح<input class="form-control" data-field="key" value="' + esc(row.key) + '" dir="ltr"></label>' +
        '<label>الوصف<input class="form-control" data-field="label" value="' + esc(row.label) + '"></label>' +
        '<label>وحدة التوريد<input class="form-control" data-field="supply_uom" value="' + esc(row.supply_uom || '') + '"></label>' +
        '<label>وحدات المخزن / توريد<input type="number" step="any" min="0.000001" class="form-control" data-field="units_per_supply_unit" value="' + esc(row.units_per_supply_unit) + '"></label>' +
        '<label>تلميح وحدة المخزن<input class="form-control" data-field="base_uom_hint" value="' + esc(row.base_uom_hint || '') + '"></label>' +
        '<div style="align-self:end;"><button type="button" class="btn-action danger" data-remove="' + idx + '">حذف</button></div>' +
        '</div>';
    });
    return html;
  }

  function paint() {
    wrap.innerHTML = renderBuiltin() + renderCustom();
    wrap.querySelectorAll('[data-remove]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var i = parseInt(btn.getAttribute('data-remove'), 10);
        customRows.splice(i, 1);
        paint();
      });
    });
  }

  document.getElementById('btnAddStockUomProfile').addEventListener('click', function () {
    customRows.push({
      key: 'custom_' + (customRows.length + 1),
      label: 'قالب مخصص',
      supply_uom: '',
      units_per_supply_unit: 1,
      base_uom_hint: '',
    });
    paint();
  });

  document.getElementById('btnSaveStockUomSettings').addEventListener('click', function () {
    errEl.style.display = 'none';
    var payload = { custom_profiles: [], supply_unit_suggestions: window.__STOCK_UOM_SUGGESTIONS || [] };
    wrap.querySelectorAll('.stock-uom-profile-card:not(.is-builtin)').forEach(function (card) {
      var row = {};
      card.querySelectorAll('[data-field]').forEach(function (input) {
        row[input.getAttribute('data-field')] = input.value;
      });
      payload.custom_profiles.push(row);
    });
    var csrf = document.querySelector('meta[name="csrf-token"]');
    fetch('/admin/stock-uom-settings', {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : '',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    }).then(function (r) { return r.ok ? r.json() : r.json().then(function (j) { throw j; }); })
      .then(function (res) {
        alert(res.message || 'تم الحفظ');
        window.location.reload();
      })
      .catch(function (e) {
        errEl.style.display = 'block';
        errEl.textContent = (e && e.message) ? e.message : 'فشل الحفظ';
      });
  });

  paint();
})();
