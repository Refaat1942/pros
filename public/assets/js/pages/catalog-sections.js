/**
 * أقسام الأصناف + حقول ديناميكية — صفحة admin/catalog
 */
window.CatalogSections = (function () {
  var categories = [];
  var fieldTypes = [
    { value: 'text', label: 'نص' },
    { value: 'number', label: 'رقم' },
    { value: 'list', label: 'قائمة' },
    { value: 'radio', label: 'اختيار واحد' },
    { value: 'checkbox', label: 'خانات اختيار' },
    { value: 'color', label: 'لون' },
    { value: 'range', label: 'شريط' },
  ];

  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }

  function init(list) {
    categories = Array.isArray(list) ? list : [];
    bindSectionsModal();
    renderCategoryFilter();
    var sel = document.getElementById('slimCategoryId');
    if (sel) {
      sel.addEventListener('change', function () {
        renderDynamicFields(sel.value, {});
      });
    }
  }

  function getCategory(id) {
    return categories.find(function (c) { return String(c.id) === String(id); });
  }

  function renderCategoryFilter() {
    var sel = document.getElementById('catalogCategoryFilter');
    if (!sel) return;
    sel.innerHTML = '<option value="">🏷️ كل الأقسام</option>' +
      categories.map(function (c) {
        return '<option value="' + c.id + '">' + escapeHtml(c.name) + '</option>';
      }).join('');
  }

  function populateCategorySelect(selectedId) {
    var sel = document.getElementById('slimCategoryId');
    if (!sel) return;
    sel.innerHTML = '<option value="">— اختر القسم —</option>' +
      categories.map(function (c) {
        var selected = String(c.id) === String(selectedId || '') ? ' selected' : '';
        return '<option value="' + c.id + '"' + selected + '>' + escapeHtml(c.name) + '</option>';
      }).join('');
  }

  function renderDynamicFields(categoryId, values) {
    var box = document.getElementById('slimCategoryFields');
    if (!box) return;
    values = values || {};
    var cat = getCategory(categoryId);
    if (!cat || !cat.fields || !cat.fields.length) {
      box.innerHTML = cat
        ? '<p style="font-size:12px;color:var(--text-muted);margin:0;">لا توجد حقول مخصّصة لهذا القسم.</p>'
        : '<p style="font-size:12px;color:var(--text-muted);margin:0;">اختر قسماً لعرض الحقول الخاصة به.</p>';
      return;
    }

    box.innerHTML = cat.fields.map(function (field) {
      var req = field.required ? ' <span style="color:#dc2626">*</span>' : '';
      var val = values[field.field_key];
      return '<div class="slim-cat-field" data-key="' + escapeHtml(field.field_key) + '" data-type="' + field.type + '">' +
        '<label style="display:block;font-size:12px;font-weight:700;margin-bottom:6px;">' + escapeHtml(field.label) + req + '</label>' +
        renderFieldInput(field, val) +
        '</div>';
    }).join('');
  }

  function renderFieldInput(field, value) {
    var cfg = field.config || {};
    var opts = field.options || [];
    var style = 'width:100%;padding:9px;border:1px solid var(--border);border-radius:8px;';

    switch (field.type) {
      case 'number':
        return '<input type="number" class="slim-attr-input" data-key="' + field.field_key + '" value="' + (value != null ? value : '') + '" min="' + (cfg.min != null ? cfg.min : '') + '" max="' + (cfg.max != null ? cfg.max : '') + '" step="' + (cfg.step || 'any') + '" style="' + style + '">';
      case 'list':
        return '<select class="slim-attr-input" data-key="' + field.field_key + '" style="' + style + '">' +
          '<option value="">—</option>' +
          opts.map(function (o) {
            var sel = String(value) === String(o.value) ? ' selected' : '';
            return '<option value="' + escapeHtml(o.value) + '"' + sel + '>' + escapeHtml(o.label) + '</option>';
          }).join('') +
          '</select>';
      case 'radio':
        return '<div class="slim-attr-radio">' + opts.map(function (o) {
          var checked = String(value) === String(o.value) ? ' checked' : '';
          return '<label style="display:inline-flex;align-items:center;gap:6px;margin-left:12px;font-size:13px;">' +
            '<input type="radio" class="slim-attr-input" data-key="' + field.field_key + '" name="attr_' + field.field_key + '" value="' + escapeHtml(o.value) + '"' + checked + '> ' +
            escapeHtml(o.label) + '</label>';
        }).join('') + '</div>';
      case 'checkbox':
        var picked = Array.isArray(value) ? value.map(String) : [];
        return '<div class="slim-attr-checkbox">' + opts.map(function (o) {
          var checked = picked.indexOf(String(o.value)) !== -1 ? ' checked' : '';
          return '<label style="display:flex;align-items:center;gap:8px;margin-bottom:6px;font-size:13px;">' +
            '<input type="checkbox" class="slim-attr-input" data-key="' + field.field_key + '" value="' + escapeHtml(o.value) + '"' + checked + '> ' +
            escapeHtml(o.label) + '</label>';
        }).join('') + '</div>';
      case 'color':
        return '<input type="color" class="slim-attr-input" data-key="' + field.field_key + '" value="' + (value || '#000000') + '" style="width:64px;height:40px;padding:2px;border:1px solid var(--border);border-radius:8px;">';
      case 'range':
        return '<div><input type="range" class="slim-attr-input" data-key="' + field.field_key + '" value="' + (value != null ? value : (cfg.min || 0)) + '" min="' + (cfg.min != null ? cfg.min : 0) + '" max="' + (cfg.max != null ? cfg.max : 100) + '" step="' + (cfg.step || 1) + '" style="width:100%;"><span class="slim-range-val" style="font-size:12px;color:var(--text-muted);">' + (value != null ? value : (cfg.min || 0)) + '</span></div>';
      default:
        return '<input type="text" class="slim-attr-input" data-key="' + field.field_key + '" value="' + escapeHtml(value != null ? value : '') + '" maxlength="' + (cfg.max_length || 255) + '" placeholder="' + escapeHtml(cfg.placeholder || '') + '" style="' + style + '">';
    }
  }

  function collectAttributes() {
    var out = {};
    document.querySelectorAll('#slimCategoryFields .slim-cat-field').forEach(function (wrap) {
      var key = wrap.getAttribute('data-key');
      var type = wrap.getAttribute('data-type');
      if (!key) return;

      if (type === 'checkbox') {
        out[key] = Array.from(wrap.querySelectorAll('input[type=checkbox]:checked')).map(function (el) { return el.value; });
        return;
      }
      if (type === 'radio') {
        var checked = wrap.querySelector('input[type=radio]:checked');
        out[key] = checked ? checked.value : '';
        return;
      }
      var input = wrap.querySelector('.slim-attr-input');
      if (input) out[key] = input.value;
    });
    return out;
  }

  function validateBeforeSave() {
    var catId = document.getElementById('slimCategoryId')?.value;
    if (!catId) return 'يرجى اختيار القسم.';
    var cat = getCategory(catId);
    if (!cat) return 'القسم غير صالح.';
    var attrs = collectAttributes();
    for (var i = 0; i < (cat.fields || []).length; i++) {
      var f = cat.fields[i];
      if (!f.required) continue;
      var v = attrs[f.field_key];
      if (f.type === 'checkbox') {
        if (!Array.isArray(v) || !v.length) return '«' + f.label + '» مطلوب.';
      } else if (v === '' || v == null) {
        return '«' + f.label + '» مطلوب.';
      }
    }
    return null;
  }

  function prepareItemForm(item) {
    item = item || {};
    populateCategorySelect(item.category_id || '');
    var map = item.attributes_map || {};
    if (!Object.keys(map).length && item.attributes) {
      item.attributes.forEach(function (a) { map[a.field_key] = a.value; });
    }
    renderDynamicFields(item.category_id || '', map);
  }

  function bindSectionsModal() {
    var openBtn = document.getElementById('btnManageStockCategories');
    var modal = document.getElementById('stockCategoriesModal');
    if (openBtn) openBtn.addEventListener('click', openSectionsModal);
    if (modal) modal.addEventListener('click', function (e) {
      if (e.target === modal) closeSectionsModal();
    });
    document.getElementById('closeStockCategoriesModal')?.addEventListener('click', closeSectionsModal);
    document.getElementById('btnAddStockCategory')?.addEventListener('click', function () { editCategory(null); });
    document.getElementById('btnSaveStockCategory')?.addEventListener('click', saveCategory);
    document.getElementById('btnCancelStockCategory')?.addEventListener('click', cancelCategoryEdit);
  }

  function openSectionsModal() {
    renderCategoriesList();
    cancelCategoryEdit();
    document.getElementById('stockCategoriesModal')?.classList.add('open');
  }

  function closeSectionsModal() {
    document.getElementById('stockCategoriesModal')?.classList.remove('open');
  }

  function renderCategoriesList() {
    var box = document.getElementById('stockCategoriesList');
    if (!box) return;
    if (!categories.length) {
      box.innerHTML = '<p style="color:var(--text-muted);font-size:13px;">لا توجد أقسام — أضف قسماً جديداً.</p>';
      return;
    }
    box.innerHTML = categories.map(function (c) {
      var fieldsCount = (c.fields || []).length;
      return '<div class="stock-cat-row" style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px;border:1px solid var(--border);border-radius:8px;margin-bottom:8px;">' +
        '<div><strong>' + escapeHtml(c.name) + '</strong><div style="font-size:11px;color:var(--text-muted);">' + fieldsCount + ' حقل</div></div>' +
        '<div style="display:flex;gap:6px;">' +
        '<button type="button" class="btn-action" data-edit-cat="' + c.id + '">✏️</button>' +
        '<button type="button" class="btn-action danger" data-del-cat="' + c.id + '" data-cat-name="' + escapeHtml(c.name) + '">🗑️</button>' +
        '</div></div>';
    }).join('');

    box.querySelectorAll('[data-edit-cat]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        editCategory(getCategory(btn.getAttribute('data-edit-cat')));
      });
    });
    box.querySelectorAll('[data-del-cat]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        deleteCategory(btn.getAttribute('data-del-cat'), btn.getAttribute('data-cat-name'));
      });
    });
  }

  function editCategory(cat) {
    document.getElementById('stockCategoryEditPanel').style.display = 'block';
    document.getElementById('editStockCategoryId').value = cat ? cat.id : '';
    document.getElementById('editStockCategoryName').value = cat ? cat.name : '';
    renderFieldBuilder(cat ? cat.fields : []);
  }

  function cancelCategoryEdit() {
    document.getElementById('stockCategoryEditPanel').style.display = 'none';
    document.getElementById('stockCategoryEditError').style.display = 'none';
  }

  function renderFieldBuilder(fields) {
    var box = document.getElementById('stockCategoryFieldsBuilder');
    if (!box) return;
    box.innerHTML = (fields || []).map(function (f, idx) { return fieldBuilderRow(f, idx); }).join('') ||
      '<p style="font-size:12px;color:var(--text-muted);">أضف حقولاً لهذا القسم.</p>';
    box.querySelectorAll('[data-remove-field]').forEach(function (btn) {
      btn.addEventListener('click', function () { btn.closest('.field-builder-row').remove(); });
    });
  }

  function fieldBuilderRow(field, idx) {
    field = field || { type: 'text', label: '', options: [], config: {}, required: false };
    var optNeeds = ['list', 'radio', 'checkbox'].indexOf(field.type) !== -1;
    var cfg = field.config || {};
    var optionsText = (field.options || []).map(function (o) { return o.label || o.value; }).join('\n');
    return '<div class="field-builder-row" style="border:1px dashed var(--border);border-radius:8px;padding:10px;margin-bottom:8px;">' +
      '<input type="hidden" class="fb-id" value="' + (field.id || '') + '">' +
      '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">' +
      '<input type="text" class="fb-label" placeholder="اسم الحقل" value="' + escapeHtml(field.label || '') + '" style="padding:8px;border:1px solid var(--border);border-radius:8px;">' +
      '<select class="fb-type" style="padding:8px;border:1px solid var(--border);border-radius:8px;">' +
      fieldTypes.map(function (t) {
        return '<option value="' + t.value + '"' + (field.type === t.value ? ' selected' : '') + '>' + t.label + '</option>';
      }).join('') +
      '</select></div>' +
      (optNeeds ? '<textarea class="fb-options" rows="2" placeholder="خيارات (سطر لكل خيار)" style="width:100%;margin-top:8px;padding:8px;border:1px solid var(--border);border-radius:8px;">' + escapeHtml(optionsText) + '</textarea>' : '') +
      '<div style="display:flex;gap:8px;margin-top:8px;align-items:center;">' +
      '<label style="font-size:12px;"><input type="checkbox" class="fb-required"' + (field.required ? ' checked' : '') + '> مطلوب</label>' +
      '<input type="number" class="fb-min" placeholder="min" value="' + (cfg.min != null ? cfg.min : '') + '" style="width:70px;padding:6px;border:1px solid var(--border);border-radius:6px;">' +
      '<input type="number" class="fb-max" placeholder="max" value="' + (cfg.max != null ? cfg.max : '') + '" style="width:70px;padding:6px;border:1px solid var(--border);border-radius:6px;">' +
      '<button type="button" class="btn-action danger" data-remove-field>×</button>' +
      '</div></div>';
  }

  document.addEventListener('click', function (e) {
    if (e.target && e.target.id === 'btnAddCategoryField') {
      var box = document.getElementById('stockCategoryFieldsBuilder');
      if (!box) return;
      if (box.querySelector('p')) box.innerHTML = '';
      box.insertAdjacentHTML('beforeend', fieldBuilderRow({}, 0));
      box.querySelectorAll('[data-remove-field]').forEach(function (btn) {
        btn.addEventListener('click', function () { btn.closest('.field-builder-row').remove(); });
      });
    }
  });

  function collectFieldBuilderRows() {
    return Array.from(document.querySelectorAll('#stockCategoryFieldsBuilder .field-builder-row')).map(function (row, idx) {
      var type = row.querySelector('.fb-type').value;
      var options = [];
      if (['list', 'radio', 'checkbox'].indexOf(type) !== -1) {
        (row.querySelector('.fb-options')?.value || '').split(/\n/).forEach(function (line) {
          line = line.trim();
          if (line) options.push({ value: line, label: line });
        });
      }
      var config = {};
      var min = row.querySelector('.fb-min')?.value;
      var max = row.querySelector('.fb-max')?.value;
      if (min !== '') config.min = parseFloat(min);
      if (max !== '') config.max = parseFloat(max);
      return {
        id: row.querySelector('.fb-id')?.value || null,
        label: row.querySelector('.fb-label')?.value.trim(),
        type: type,
        required: !!row.querySelector('.fb-required')?.checked,
        sort_order: (idx + 1) * 10,
        options: options,
        config: config,
      };
    }).filter(function (f) { return f.label; });
  }

  function saveCategory() {
    var err = document.getElementById('stockCategoryEditError');
    var id = document.getElementById('editStockCategoryId').value;
    var name = document.getElementById('editStockCategoryName').value.trim();
    if (!name) {
      err.textContent = 'اسم القسم مطلوب.';
      err.style.display = 'block';
      return;
    }
    var payload = { name: name, fields: collectFieldBuilderRows() };
    var url = id ? ('/admin/stock-categories/' + id) : '/admin/stock-categories';
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
    .then(function (data) {
      var saved = data.stock_category || data;
      if (id) {
        categories = categories.map(function (c) { return String(c.id) === String(id) ? saved : c; });
      } else {
        categories.push(saved);
      }
      window.__STOCK_CATEGORIES = categories;
      renderCategoriesList();
      renderCategoryFilter();
      populateCategorySelect(document.getElementById('slimCategoryId')?.value);
      cancelCategoryEdit();
    })
    .catch(function (e) {
      err.textContent = (e && e.message) ? e.message : 'تعذّر الحفظ.';
      if (e && e.errors) {
        var first = Object.values(e.errors)[0];
        if (first && first[0]) err.textContent = first[0];
      }
      err.style.display = 'block';
    });
  }

  function deleteCategory(id, name) {
    if (!confirm('حذف القسم «' + name + '»؟')) return;
    fetch('/admin/stock-categories/' + id, {
      method: 'DELETE',
      headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    .then(function (r) { return r.ok ? r.json() : r.json().then(function (j) { throw j; }); })
    .then(function () {
      categories = categories.filter(function (c) { return String(c.id) !== String(id); });
      window.__STOCK_CATEGORIES = categories;
      renderCategoriesList();
      renderCategoryFilter();
    })
    .catch(function (e) { alert((e && e.message) ? e.message : 'تعذّر الحذف.'); });
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/"/g, '&quot;');
  }

  function formatAttributesSummary(item) {
    if (!item.attributes || !item.attributes.length) return '—';
    return item.attributes.map(function (a) {
      return a.label + ': ' + (a.display_value || a.value || '—');
    }).join(' · ');
  }

  return {
    init: init,
    prepareItemForm: prepareItemForm,
    collectAttributes: collectAttributes,
    validateBeforeSave: validateBeforeSave,
    formatAttributesSummary: formatAttributesSummary,
  };
})();
