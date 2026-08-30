(function () {
  'use strict';

  if (window.__CatalogItemFormBooted) return;
  window.__CatalogItemFormBooted = true;

  var supplierOptions = window.__CATALOG_SUPPLIERS || [];

  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }

  function catalogApiBase() {
    return window.__CATALOG_API_BASE || '/admin/catalog';
  }

  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
  }

  function formatTierQtyDisplay(qty) {
    var n = parseFloat(qty);
    if (!isFinite(n)) return '0';
    if (Math.abs(n - Math.round(n)) < 0.0001) return String(Math.round(n));
    return String(n).replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '');
  }

  function catalogPriceAmountKey(amount) {
    return (Math.round(parseFloat(amount) * 100) / 100).toFixed(2);
  }

  function catalogAmountsEqual(a, b) {
    return catalogPriceAmountKey(a) === catalogPriceAmountKey(b);
  }

  function tierQtyForAmount(amount, tiers) {
    if (!tiers || !tiers.length) return 0;
    var key = catalogPriceAmountKey(amount);
    for (var i = 0; i < tiers.length; i++) {
      if (catalogPriceAmountKey(tiers[i].amount) === key) {
        return parseFloat(tiers[i].qty) || 0;
      }
    }
    return 0;
  }

  function updateBasePriceQtyBadge(amount, tiers) {
    var badge = document.getElementById('slimBasePriceQty');
    if (!badge) return;
    var qty = tierQtyForAmount(amount, tiers);
    badge.textContent = 'مخزن: ' + formatTierQtyDisplay(qty);
    badge.hidden = false;
  }

  function buildPriceTiersFromItem(item) {
    if (item.price_tiers && item.price_tiers.length) {
      return item.price_tiers;
    }
    var tiers = [];
    var base = parseFloat(item.price) || 0;
    if (base > 0) {
      tiers.push({ amount: base, qty: 0, id: null });
    }
    (item.prices || []).forEach(function (p) {
      tiers.push({
        amount: parseFloat(p.amount) || 0,
        qty: parseFloat(p.qty) || 0,
        id: p.id || null,
      });
    });
    return tiers;
  }

  function setSupplierValue(id, name) {
    var hidden = document.getElementById('slimSupplierId');
    var label = document.getElementById('slimSupplierLabel');
    if (hidden) hidden.value = id ? String(id) : '';
    if (label) {
      label.textContent = name || '— اختر المورد —';
      label.classList.toggle('is-placeholder', !name);
    }
    closeSupplierDropdown();
  }

  function renderSupplierList(filter) {
    var list = document.getElementById('slimSupplierList');
    if (!list) return;
    var term = (filter || '').toLowerCase().trim();
    var selectedId = document.getElementById('slimSupplierId')?.value || '';
    var matches = supplierOptions.filter(function (s) {
      return !term || String(s.name).toLowerCase().indexOf(term) !== -1;
    });
    if (!matches.length) {
      list.innerHTML = '<li class="catalog-combobox__empty">لا توجد نتائج</li>';
      return;
    }
    list.innerHTML = matches.map(function (s) {
      var selected = String(s.id) === selectedId ? ' is-selected' : '';
      return '<li><button type="button" class="catalog-combobox__option' + selected + '" data-id="' + s.id + '">' + escapeHtml(s.name) + '</button></li>';
    }).join('');
  }

  function openSupplierDropdown() {
    var combobox = document.getElementById('slimSupplierCombobox');
    var dropdown = document.getElementById('slimSupplierDropdown');
    var toggle = document.getElementById('slimSupplierToggle');
    var search = document.getElementById('slimSupplierSearch');
    if (!dropdown || !toggle) return;
    dropdown.hidden = false;
    combobox?.classList.add('is-open');
    toggle.setAttribute('aria-expanded', 'true');
    if (search) {
      search.value = '';
      renderSupplierList('');
      search.focus();
    }
  }

  function closeSupplierDropdown() {
    var combobox = document.getElementById('slimSupplierCombobox');
    var dropdown = document.getElementById('slimSupplierDropdown');
    var toggle = document.getElementById('slimSupplierToggle');
    if (dropdown) dropdown.hidden = true;
    combobox?.classList.remove('is-open');
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
  }

  function recalcCatalogBalance() {
    var opening = parseInt(document.getElementById('slimOpeningQty').value || '0', 10);
    var addition = parseInt(document.getElementById('slimAddition').value || '0', 10);
    var discount = parseInt(document.getElementById('slimDiscount').value || '0', 10);
    var balance = opening + addition - discount;
    document.getElementById('slimBalance').value = balance < 0 ? 0 : balance;
  }

  function toggleBarcodeFields(isEdit, item) {
    var wrap = document.getElementById('slimBarcodeWrap');
    var display = document.getElementById('slimBarcodeDisplay');
    var hidden = document.getElementById('slimAltCodes');
    if (!wrap) return;
    if (isEdit && item) {
      wrap.style.display = '';
      var bc = item.barcode || (item.alt_codes ? ('BC-' + item.alt_codes) : '—');
      if (display) display.value = bc;
      if (hidden) hidden.value = item.alt_codes || '';
    } else {
      wrap.style.display = 'none';
      if (display) display.value = '';
      if (hidden) hidden.value = '';
    }
  }

  function renderWarehouseTierSummary(tiers) {
    var box = document.getElementById('slimWarehouseTiers');
    if (!box) return;
    var active = (tiers || []).filter(function (t) {
      return (parseFloat(t.qty) || 0) > 0 || t.from_supply;
    });
    if (!active.length) {
      box.innerHTML = '';
      box.hidden = true;
      return;
    }
    box.hidden = false;
    box.innerHTML = active.map(function (t) {
      var amount = parseFloat(t.amount) || 0;
      return '<span class="slim-price-qty-badge">' + amount.toFixed(2) + ' ج.م × مخزن ' + formatTierQtyDisplay(t.qty) + '</span>';
    }).join('');
  }

  function setForm(v) {
    document.getElementById('slimCode').value = v.code || '';
    document.getElementById('slimPageNumber').value = v.page_number || '';
    document.getElementById('slimName').value = v.name || '';
    document.getElementById('slimBrand').value = v.brand || '';
    document.getElementById('slimUom').value = v.uom || 'قطعة';
    document.getElementById('slimOpeningQty').value = v.opening_qty != null ? v.opening_qty : 0;
    document.getElementById('slimAddition').value = v.addition != null ? v.addition : 0;
    document.getElementById('slimDiscount').value = v.discount != null ? v.discount : 0;
    document.getElementById('slimBalance').value = v.catalog_balance != null ? v.catalog_balance : (v.balance != null ? v.balance : 0);
    document.getElementById('slimMinQty').value = v.min_qty != null ? v.min_qty : 0;
    document.getElementById('slimPrice').value = v.price != null ? v.price : 0;
    document.getElementById('slimEditId').value = v.id || '';
    document.getElementById('slimEditCode').value = v.code || '';
    document.getElementById('slimExtraPrices').innerHTML = '';
    window.__catalogPriceTiers = buildPriceTiersFromItem(v);
    updateBasePriceQtyBadge(parseFloat(v.price) || 0, window.__catalogPriceTiers);
    renderWarehouseTierSummary(window.__catalogPriceTiers);
    var extras = v.catalog_extra_prices || [];
    extras.forEach(function (p) {
      window.addSlimPriceRow(
        p.amount,
        tierQtyForAmount(p.amount, window.__catalogPriceTiers),
        p.id
      );
    });
    var quickEl = document.getElementById('slimIsQuickDispense');
    if (quickEl) quickEl.checked = !!v.is_quick_dispense;
    var first = (v.suppliers || [])[0];
    setSupplierValue(first ? first.id : '', first ? first.name : '');
    document.getElementById('slimCatalogError').style.display = 'none';
    if (window.CatalogSections) {
      window.CatalogSections.prepareItemForm(v);
    }
    toggleBarcodeFields(!!v.id, v);
  }

  function collectSupplierIds() {
    var hidden = document.getElementById('slimSupplierId');
    if (!hidden || !hidden.value) return [];
    var id = parseInt(hidden.value, 10);
    return !isNaN(id) && id > 0 ? [id] : [];
  }

  function collectExtraPrices() {
    var rows = [];
    document.querySelectorAll('#slimExtraPrices .slim-price-row').forEach(function (r) {
      var amount = parseFloat(r.querySelector('.slim-price-amount').value || '0');
      if (amount > 0) {
        var row = { amount: amount };
        var priceId = r.getAttribute('data-price-id');
        if (priceId) row.id = priceId;
        rows.push(row);
      }
    });
    return rows;
  }

  function openCatalogFormModal(title) {
    var modal = document.getElementById('catalogFormModal');
    var titleEl = document.getElementById('catalogFormModalTitle');
    if (titleEl) titleEl.textContent = title || '➕ إضافة صنف';
    if (!modal) return;
    modal.classList.add('open');
    modal.removeAttribute('hidden');
  }

  function closeCatalogFormModal() {
    closeSupplierDropdown();
    var modal = document.getElementById('catalogFormModal');
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('hidden', '');
  }

  window.addSlimPriceRow = function (amount, qty, priceId) {
    var box = document.getElementById('slimExtraPrices');
    if (!box) return;
    var row = document.createElement('div');
    row.className = 'slim-price-row';
    var qtyText = formatTierQtyDisplay(
      qty != null ? qty : tierQtyForAmount(amount, window.__catalogPriceTiers || [])
    );
    row.innerHTML =
      '<span class="slim-price-qty-badge" title="رصيد المخزن بهذا السعر (للعرض فقط)">مخزن: ' + qtyText + '</span>' +
      '<input type="number" min="0" step="0.01" class="slim-price-amount" placeholder="السعر (ج.م)">' +
      '<button type="button" class="btn-action danger" onclick="this.closest(\'.slim-price-row\').remove()">×</button>';
    box.appendChild(row);
    if (amount != null) row.querySelector('.slim-price-amount').value = amount;
    if (priceId) row.setAttribute('data-price-id', String(priceId));
  };

  window.openSlimCatalogForm = function () {
    setForm({});
    var codeEl = document.getElementById('slimCode');
    if (codeEl) codeEl.disabled = false;
    openCatalogFormModal('➕ إضافة صنف');
  };

  window.closeSlimCatalogForm = function () {
    closeCatalogFormModal();
  };

  window.saveSlimCatalog = function () {
    var id = document.getElementById('slimEditId').value;
    var err = document.getElementById('slimCatalogError');
    var name = document.getElementById('slimName').value.trim();

    if (window.CatalogSections) {
      var catErr = window.CatalogSections.validateBeforeSave();
      if (catErr) {
        err.textContent = catErr;
        err.style.display = 'block';
        return;
      }
    }

    if (!name) {
      err.textContent = 'يرجى إدخال اسم الصنف.';
      err.style.display = 'block';
      return;
    }

    var supplierIds = collectSupplierIds();
    if (!supplierIds.length) {
      err.textContent = 'يرجى اختيار مورد واحد لهذا الصنف.';
      err.style.display = 'block';
      return;
    }

    var payload = {
      name: name,
      brand: (document.getElementById('slimBrand').value || '').trim() || null,
      page_number: (document.getElementById('slimPageNumber').value || '').trim() || null,
      uom: (document.getElementById('slimUom').value || '').trim() || 'قطعة',
      opening_qty: parseInt(document.getElementById('slimOpeningQty').value || '0', 10),
      addition: parseInt(document.getElementById('slimAddition').value || '0', 10),
      discount: parseInt(document.getElementById('slimDiscount').value || '0', 10),
      balance: parseInt(document.getElementById('slimBalance').value || '0', 10),
      min_qty: parseInt(document.getElementById('slimMinQty').value || '0', 10),
      price: parseFloat(document.getElementById('slimPrice').value || '0'),
      prices: collectExtraPrices(),
      category_id: parseInt(document.getElementById('slimCategoryId').value || '0', 10) || null,
      attributes: window.CatalogSections ? window.CatalogSections.collectAttributes() : {},
      supplier_ids: supplierIds,
      is_quick_dispense: !!(document.getElementById('slimIsQuickDispense') || {}).checked,
    };

    if (!id) {
      var code = document.getElementById('slimCode').value.trim();
      if (code) payload.code = code;
    }

    var base = catalogApiBase().replace(/\/$/, '');
    var url = id ? (base + '/' + id) : base;
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
      .then(function () {
        if (window.__CATALOG_SUCCESS_REDIRECT) {
          window.location.href = window.__CATALOG_SUCCESS_REDIRECT;
        } else {
          window.location.reload();
        }
      })
      .catch(function (e) {
        var msg = (e && e.message) ? e.message : 'تعذّر الحفظ.';
        if (e && e.errors) { msg = Object.values(e.errors)[0][0]; }
        err.textContent = msg;
        err.style.display = 'block';
      });
  };

  document.getElementById('slimSupplierToggle')?.addEventListener('click', function () {
    var dropdown = document.getElementById('slimSupplierDropdown');
    if (dropdown && !dropdown.hidden) closeSupplierDropdown();
    else openSupplierDropdown();
  });

  document.getElementById('slimSupplierSearch')?.addEventListener('input', function () {
    var search = document.getElementById('slimSupplierSearch');
    renderSupplierList(search ? search.value : '');
  });

  document.getElementById('slimSupplierList')?.addEventListener('click', function (e) {
    var btn = e.target.closest('.catalog-combobox__option');
    if (!btn) return;
    var id = btn.getAttribute('data-id');
    var match = supplierOptions.find(function (s) { return String(s.id) === String(id); });
    setSupplierValue(id, match ? match.name : '');
  });

  document.addEventListener('click', function (e) {
    if (!e.target.closest('#slimSupplierCombobox')) closeSupplierDropdown();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeSupplierDropdown();
  });

  ['slimOpeningQty', 'slimAddition', 'slimDiscount'].forEach(function (fieldId) {
    document.getElementById(fieldId)?.addEventListener('input', recalcCatalogBalance);
  });

  document.getElementById('slimPrice')?.addEventListener('input', function () {
    updateBasePriceQtyBadge(parseFloat(document.getElementById('slimPrice').value || '0'), window.__catalogPriceTiers || []);
  });

  document.getElementById('catalogFormClose')?.addEventListener('click', closeCatalogFormModal);
  document.getElementById('catalogFormModal')?.addEventListener('click', function (e) {
    if (e.target === this) closeCatalogFormModal();
  });

  function bootEntryPage() {
    if (window.CatalogSections && window.__STOCK_CATEGORIES) {
      window.CatalogSections.init(window.__STOCK_CATEGORIES);
    }
    if (window.__CATALOG_ENTRY_AUTO_OPEN && typeof window.openSlimCatalogForm === 'function') {
      window.openSlimCatalogForm();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootEntryPage);
  } else {
    bootEntryPage();
  }
})();
