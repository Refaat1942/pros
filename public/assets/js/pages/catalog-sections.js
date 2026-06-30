/**
 * أقسام الأصناف + حقول ديناميكية — صفحة admin/catalog
 */
window.CatalogSections = (function () {
  var categories = [];
  var pageMode = false;
  var activeCategoryId = null;
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

  function init(list, options) {
    options = options || {};
    pageMode = !!options.pageMode || !!document.getElementById('section-stock-categories');
    categories = Array.isArray(list) ? list : [];
    if (pageMode) {
      bindPageEditor();
    } else {
      bindSectionsModal();
    }
    renderCategoryFilter();
    var sel = document.getElementById('slimCategoryId');
    if (sel) {
      sel.addEventListener('change', function () {
        renderDynamicFields(sel.value, {});
      });
    }
    document.addEventListener('input', function (e) {
      if (e.target && e.target.classList && e.target.classList.contains('slim-attr-color-input')) {
        var wrap = e.target.closest('.slim-attr-color');
        var val = wrap && wrap.querySelector('.slim-attr-color-value');
        if (val) val.textContent = e.target.value;
      }
    });
    if (pageMode) {
      renderCategoriesList();
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
    var heading = document.getElementById('slimCategoryFieldsHeading');
    if (!box) return;
    values = values || {};
    var cat = getCategory(categoryId);
    if (heading) {
      heading.style.display = cat && cat.fields && cat.fields.length ? '' : 'none';
      if (cat && cat.fields && cat.fields.length) {
        heading.textContent = '📋 حقول القسم — ' + cat.name;
      }
    }
    if (!cat || !cat.fields || !cat.fields.length) {
      box.innerHTML = '';
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
        return '<div class="slim-attr-color">' +
          '<input type="color" class="slim-attr-input slim-attr-color-input" data-key="' + field.field_key + '" value="' + (value || cfg.default || '#2563eb') + '">' +
          '<span class="slim-attr-color-value">' + (value || cfg.default || '#2563eb') + '</span>' +
          '</div>';
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

  function bindPageEditor() {
    document.getElementById('btnAddStockCategory')?.addEventListener('click', function () { editCategory(null); });
    document.getElementById('btnSaveStockCategory')?.addEventListener('click', saveCategory);
    document.getElementById('btnCancelStockCategory')?.addEventListener('click', cancelCategoryEdit);
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
      var active = pageMode && String(c.id) === String(activeCategoryId) ? ' is-active' : '';
      return '<div class="stock-cat-row' + active + '">' +
        '<div class="stock-cat-row__info">' +
        '<strong class="stock-cat-row__name">' + escapeHtml(c.name) + '</strong>' +
        '<span class="stock-cat-row__meta">' + fieldsCount + ' حقل</span>' +
        '</div>' +
        '<div class="stock-cat-row__actions">' +
        '<button type="button" class="btn-action stock-cat-row__edit" data-edit-cat="' + c.id + '" title="تعديل">✏️</button>' +
        '<button type="button" class="btn-action danger stock-cat-row__del" data-del-cat="' + c.id + '" data-cat-name="' + escapeHtml(c.name) + '" title="حذف">🗑️</button>' +
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
    activeCategoryId = cat ? cat.id : null;
    var panel = document.getElementById('stockCategoryEditPanel');
    var placeholder = document.getElementById('stockCategoryEditPlaceholder');
    var title = document.getElementById('stockCategoryEditTitle');
    if (panel) panel.style.display = 'block';
    if (placeholder) placeholder.style.display = 'none';
    if (title) title.textContent = cat ? ('تعديل — ' + cat.name) : 'قسم جديد';
    document.getElementById('editStockCategoryId').value = cat ? cat.id : '';
    document.getElementById('editStockCategoryName').value = cat ? cat.name : '';
    renderFieldBuilder(cat ? cat.fields : []);
    if (pageMode) renderCategoriesList();
  }

  function cancelCategoryEdit() {
    activeCategoryId = null;
    var panel = document.getElementById('stockCategoryEditPanel');
    var placeholder = document.getElementById('stockCategoryEditPlaceholder');
    if (pageMode) {
      if (panel) panel.style.display = 'none';
      if (placeholder) placeholder.style.display = '';
      renderCategoriesList();
    } else {
      if (panel) panel.style.display = 'none';
    }
    var err = document.getElementById('stockCategoryEditError');
    if (err) err.style.display = 'none';
  }

  function renderOptionsListHtml(options) {
    var items = (options || []).map(function (o) { return o.label || o.value; }).filter(Boolean);
    if (!items.length) {
      return '<p class="fb-options-empty">لا توجد خيارات بعد — أضف خياراً أدناه.</p>';
    }
    return items.map(function (label, idx) {
      return optionRowHtml(label, idx + 1);
    }).join('');
  }

  function optionRowHtml(label, num) {
    num = num || 1;
    return '<div class="fb-option-row">' +
      '<span class="fb-option-num">' + num + '</span>' +
      '<input type="text" class="fb-option-input sc-form-control" value="' + escapeHtml(label || '') + '" placeholder="مثال: قطعة">' +
      '<button type="button" class="fb-option-remove btn-action danger" data-remove-option title="حذف الخيار">×</button>' +
      '</div>';
  }

  function reindexOptionRows(listEl) {
    if (!listEl) return;
    var rows = listEl.querySelectorAll('.fb-option-row');
    rows.forEach(function (row, idx) {
      var num = row.querySelector('.fb-option-num');
      if (num) num.textContent = String(idx + 1);
    });
    var empty = listEl.querySelector('.fb-options-empty');
    if (!rows.length && !empty) {
      listEl.innerHTML = '<p class="fb-options-empty">لا توجد خيارات بعد — أضف خياراً أدناه.</p>';
    } else if (rows.length && empty) {
      empty.remove();
    }
    updateOptionsCount(listEl.closest('.fb-panel--options'));
  }

  function updateOptionsCount(panel) {
    if (!panel) return;
    var countEl = panel.querySelector('.fb-options-count');
    if (!countEl) return;
    var n = panel.querySelectorAll('.fb-option-row').length;
    countEl.textContent = n ? (n + ' ' + (n === 1 ? 'خيار' : 'خيارات')) : 'بدون خيارات';
  }

  function addOptionToRow(fieldRow, value) {
    value = (value || '').trim();
    if (!value) return false;
    var listEl = fieldRow.querySelector('.fb-options-list');
    if (!listEl) return false;
    var empty = listEl.querySelector('.fb-options-empty');
    if (empty) empty.remove();
    var count = listEl.querySelectorAll('.fb-option-row').length;
    listEl.insertAdjacentHTML('beforeend', optionRowHtml(value, count + 1));
    updateOptionsCount(fieldRow.querySelector('.fb-panel--options'));
    return true;
  }

  function bindOptionsPanel(fieldRow) {
    var panel = fieldRow.querySelector('.fb-panel--options');
    if (!panel || panel.dataset.bound) return;
    panel.dataset.bound = '1';

    panel.querySelector('[data-add-option]')?.addEventListener('click', function () {
      var input = panel.querySelector('.fb-option-new');
      if (!input) return;
      if (addOptionToRow(fieldRow, input.value)) {
        input.value = '';
        input.focus();
      }
    });

    panel.querySelector('.fb-option-new')?.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      if (addOptionToRow(fieldRow, e.target.value)) {
        e.target.value = '';
      }
    });

    panel.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-remove-option]');
      if (!btn) return;
      var listEl = panel.querySelector('.fb-options-list');
      btn.closest('.fb-option-row')?.remove();
      reindexOptionRows(listEl);
      updateOptionsCount(panel);
    });
  }

  function collectOptionsFromFieldRow(row) {
    var listEl = row.querySelector('.fb-options-list');
    if (!listEl) return [];
    return Array.from(listEl.querySelectorAll('.fb-option-input'))
      .map(function (input) { return input.value.trim(); })
      .filter(Boolean)
      .map(function (line) { return { value: line, label: line }; });
  }

  function renderFieldBuilder(fields) {
    var box = document.getElementById('stockCategoryFieldsBuilder');
    if (!box) return;
    box.innerHTML = (fields || []).map(function (f, idx) { return fieldBuilderRow(f, idx); }).join('') ||
      '<p class="field-builder-empty">لا توجد حقول — اضغط «+ حقل جديد» لإضافة أول حقل.</p>';
    box.querySelectorAll('.field-builder-row').forEach(function (row, idx) {
      var indexEl = row.querySelector('.field-builder-row__index');
      if (indexEl) indexEl.textContent = 'حقل ' + (idx + 1);
      bindFieldBuilderRow(row);
    });
  }

  function bindFieldBuilderRow(row) {
    if (!row) return;
    row.querySelector('[data-remove-field]')?.addEventListener('click', function () {
      row.remove();
      var box = document.getElementById('stockCategoryFieldsBuilder');
      if (box && !box.querySelector('.field-builder-row')) {
        box.innerHTML = '<p class="field-builder-empty">لا توجد حقول — اضغط «+ حقل جديد» لإضافة أول حقل.</p>';
      } else {
        box.querySelectorAll('.field-builder-row').forEach(function (r, i) {
          var indexEl = r.querySelector('.field-builder-row__index');
          if (indexEl) indexEl.textContent = 'حقل ' + (i + 1);
        });
      }
    });
    row.querySelector('.fb-type')?.addEventListener('change', function () {
      updateFieldBuilderRow(row);
    });
    row.querySelector('.fb-default-color')?.addEventListener('input', function (e) {
      var swatch = row.querySelector('.fb-color-swatch');
      var val = row.querySelector('.fb-color-value');
      if (swatch) swatch.style.background = e.target.value;
      if (val) val.textContent = e.target.value;
    });
    bindOptionsPanel(row);
    updateFieldBuilderRow(row);
  }

  function fieldTypeLabel(type) {
    for (var i = 0; i < fieldTypes.length; i++) {
      if (fieldTypes[i].value === type) return fieldTypes[i].label;
    }
    return type;
  }

  function updateFieldBuilderRow(row) {
    var type = row.querySelector('.fb-type')?.value || 'text';
    var needsOptions = ['list', 'radio', 'checkbox'].indexOf(type) !== -1;
    var needsBounds = ['number', 'range'].indexOf(type) !== -1;
    var isColor = type === 'color';

    var badge = row.querySelector('.field-builder-row__type-badge');
    if (badge) badge.textContent = fieldTypeLabel(type);

    row.querySelectorAll('.fb-panel').forEach(function (panel) {
      panel.hidden = true;
    });

    if (needsOptions) {
      var optPanel = row.querySelector('.fb-panel--options');
      if (optPanel) {
        optPanel.hidden = false;
        updateOptionsCount(optPanel);
      }
    }
    if (needsBounds) {
      var boundsPanel = row.querySelector('.fb-panel--bounds');
      if (boundsPanel) boundsPanel.hidden = false;
    }
    if (isColor) {
      var colorPanel = row.querySelector('.fb-panel--color');
      if (colorPanel) colorPanel.hidden = false;
    }
  }

  function fieldBuilderRow(field, idx) {
    field = field || { type: 'text', label: '', options: [], config: {}, required: false };
    var cfg = field.config || {};
    var defaultColor = cfg.default || '#2563eb';
    var fieldNum = (idx || 0) + 1;
    var typeOptions = fieldTypes.map(function (t) {
      return '<option value="' + t.value + '"' + (field.type === t.value ? ' selected' : '') + '>' + t.label + '</option>';
    }).join('');
    var optionsCount = (field.options || []).length;

    return '<div class="field-builder-row">' +
      '<input type="hidden" class="fb-id" value="' + (field.id || '') + '">' +
      '<div class="field-builder-row__toolbar">' +
        '<span class="field-builder-row__index">حقل ' + fieldNum + '</span>' +
        '<span class="field-builder-row__type-badge">' + escapeHtml(fieldTypeLabel(field.type || 'text')) + '</span>' +
        '<span class="field-builder-row__toolbar-spacer"></span>' +
        '<label class="field-builder-row__required-toggle">' +
          '<input type="checkbox" class="fb-required"' + (field.required ? ' checked' : '') + '> مطلوب' +
        '</label>' +
        '<button type="button" class="field-builder-row__remove btn-action danger" data-remove-field title="حذف الحقل">🗑 حذف</button>' +
      '</div>' +
      '<div class="field-builder-row__body">' +
        '<div class="field-builder-row__label-wrap">' +
          '<label class="field-builder-row__mini-label">اسم الحقل</label>' +
          '<input type="text" class="fb-label sc-form-control" placeholder="مثال: عدد المسامير" value="' + escapeHtml(field.label || '') + '">' +
        '</div>' +
        '<div class="field-builder-row__type-wrap">' +
          '<label class="field-builder-row__mini-label">نوع الحقل</label>' +
          '<select class="fb-type sc-form-control">' + typeOptions + '</select>' +
        '</div>' +
      '</div>' +
      '<div class="fb-panel fb-panel--options" hidden>' +
        '<div class="fb-options-head">' +
          '<span class="field-builder-row__mini-label">الخيارات</span>' +
          '<span class="fb-options-count">' + (optionsCount ? optionsCount + ' ' + (optionsCount === 1 ? 'خيار' : 'خيارات') : 'بدون خيارات') + '</span>' +
        '</div>' +
        '<div class="fb-options-list">' + renderOptionsListHtml(field.options) + '</div>' +
        '<div class="fb-options-add">' +
          '<input type="text" class="fb-option-new sc-form-control" placeholder="اكتب خياراً جديداً…">' +
          '<button type="button" class="btn-action primary fb-options-add-btn" data-add-option>+ إضافة</button>' +
        '</div>' +
      '</div>' +
      '<div class="fb-panel fb-panel--bounds" hidden>' +
        '<div class="field-builder-row__bounds">' +
          '<div><label class="field-builder-row__mini-label">الحد الأدنى</label><input type="number" class="fb-min sc-form-control" placeholder="مثال: 0" value="' + (cfg.min != null ? cfg.min : '') + '"></div>' +
          '<div><label class="field-builder-row__mini-label">الحد الأقصى</label><input type="number" class="fb-max sc-form-control" placeholder="مثال: 100" value="' + (cfg.max != null ? cfg.max : '') + '"></div>' +
        '</div>' +
      '</div>' +
      '<div class="fb-panel fb-panel--color" hidden>' +
        '<div class="field-builder-row__color-preview">' +
          '<span class="fb-color-swatch" style="background:' + escapeHtml(defaultColor) + ';"></span>' +
          '<div class="field-builder-row__color-copy">' +
            '<strong>منتقي ألوان</strong>' +
            '<p>سيظهر للمستخدم منتقي لون عند إضافة صنف بهذا الحقل.</p>' +
            '<div class="field-builder-row__color-picker">' +
              '<label><span class="field-builder-row__mini-label">لون افتراضي (اختياري)</span>' +
              '<input type="color" class="fb-default-color" value="' + escapeHtml(defaultColor) + '">' +
              '<code class="fb-color-value">' + escapeHtml(defaultColor) + '</code></label>' +
            '</div>' +
          '</div>' +
        '</div>' +
      '</div>' +
    '</div>';
  }

  document.addEventListener('click', function (e) {
    if (e.target && e.target.id === 'btnAddCategoryField') {
      var box = document.getElementById('stockCategoryFieldsBuilder');
      if (!box) return;
      var empty = box.querySelector('.field-builder-empty');
      if (empty) empty.remove();
      box.insertAdjacentHTML('beforeend', fieldBuilderRow({}, 0));
      var rows = box.querySelectorAll('.field-builder-row');
      bindFieldBuilderRow(rows[rows.length - 1]);
    }
  });

  function collectFieldBuilderRows() {
    return Array.from(document.querySelectorAll('#stockCategoryFieldsBuilder .field-builder-row')).map(function (row, idx) {
      var type = row.querySelector('.fb-type').value;
      var options = [];
      if (['list', 'radio', 'checkbox'].indexOf(type) !== -1) {
        options = collectOptionsFromFieldRow(row);
      }
      var config = {};
      if (['number', 'range'].indexOf(type) !== -1) {
        var min = row.querySelector('.fb-min')?.value;
        var max = row.querySelector('.fb-max')?.value;
        if (min !== '') config.min = parseFloat(min);
        if (max !== '') config.max = parseFloat(max);
      }
      if (type === 'color') {
        var defaultColor = row.querySelector('.fb-default-color')?.value;
        if (defaultColor) config.default = defaultColor;
      }
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
      if (pageMode) {
        editCategory(saved);
      } else {
        cancelCategoryEdit();
      }
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
