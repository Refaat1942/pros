/**

 * StockMultiSelect — اختيار متعدد مع بحث وكمية لأصناف المخزون

 */

var StockMultiSelect = (function () {

  function esc(s) {

    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');

  }



  // حجب الكميات المتاحة عن الفني: لا يُقيَّد بالرصيد ولا يراه (يطلب ما يحتاجه فقط).
  function clampQty(item, qty) {

    var n = parseInt(qty, 10);

    if (isNaN(n) || n < 1) return 1;

    return Math.min(n, 99);

  }



  function copyItem(item, qty) {

    var copy = Object.assign({}, item);

    copy.selectedQty = clampQty(copy, qty != null ? qty : 1);

    return copy;

  }



  function create(rootId) {

    var root = document.getElementById(rootId);

    if (!root) return null;



    var selectedWrap = root.querySelector('.sms-selected');

    var searchInput = root.querySelector('.sms-search');

    var dropdown = root.querySelector('.sms-dropdown');

    var toggleBtn = root.querySelector('.sms-toggle');

    var selected = [];

    var open = false;



    function getItems() {

      return StockCatalog.getAll();

    }



    function isSelected(code) {

      return selected.some(function (s) { return s.code === code; });

    }



    function renderSelected() {

      if (!selected.length) {

        selectedWrap.innerHTML = '';

        return;

      }

      selectedWrap.innerHTML = selected.map(function (item) {

        var max = Math.max(1, item.qty || 1);

        return '<div class="sms-chip" data-code="' + esc(item.code) + '">' +

          '<span class="sms-chip-label">' + esc(item.name) + '</span>' +

          '<div class="sms-chip-qty">' +

          '<span class="sms-qty-label">الكمية</span>' +

          '<input type="number" class="sms-qty-input" min="1" max="' + max + '" value="' + item.selectedQty + '" inputmode="numeric" aria-label="كمية ' + esc(item.name) + '">' +

          '<span class="sms-qty-max">/ ' + max + '</span>' +

          '</div>' +

          '<button type="button" class="sms-chip-remove" data-code="' + esc(item.code) + '" aria-label="إزالة">&times;</button>' +

          '</div>';

      }).join('');

    }



    function renderDropdown() {

      var q = (searchInput.value || '').trim().toLowerCase();

      var items = getItems().filter(function (item) {

        if (!q) return true;

        var hay = [item.name, item.code, item.category, item.spec].join(' ').toLowerCase();

        return hay.indexOf(q) !== -1;

      });



      if (!items.length) {

        dropdown.innerHTML = '<div class="sms-empty">لا توجد نتائج — تأكد من إضافة الأصناف في لوحة المخزون</div>';

        return;

      }



      dropdown.innerHTML = items.map(function (item) {

        var checked = isSelected(item.code);

        return '<label class="sms-option">' +

          '<input type="checkbox" value="' + esc(item.code) + '"' + (checked ? ' checked' : '') + '>' +

          '<span class="sms-option-body">' +

          '<span class="sms-option-title">' + esc(item.name) + '</span>' +

          '<span class="sms-option-meta">' + esc(item.code) + ' · ' + esc(item.category) + ' · ' + esc(item.spec) + '</span>' +

          '</span></label>';

      }).join('');

    }



    function setOpen(next) {

      open = next;

      root.classList.toggle('open', open);

      if (toggleBtn) toggleBtn.textContent = open ? '▲' : '▼';

      if (open) renderDropdown();

    }



    function toggleItem(code, add) {

      var items = getItems();

      var item = items.find(function (i) { return i.code === code; });

      if (!item) return;

      if (add) {

        if (!isSelected(code)) selected.push(copyItem(item, 1));

      } else {

        selected = selected.filter(function (s) { return s.code !== code; });

      }

      renderSelected();

      renderDropdown();

    }



    searchInput.addEventListener('focus', function () { setOpen(true); });

    searchInput.addEventListener('input', function () {

      setOpen(true);

      renderDropdown();

    });



    if (toggleBtn) {

      toggleBtn.addEventListener('click', function (e) {

        e.preventDefault();

        setOpen(!open);

        if (open) searchInput.focus();

      });

    }



    dropdown.addEventListener('change', function (e) {

      if (e.target && e.target.type === 'checkbox') {

        toggleItem(e.target.value, e.target.checked);

      }

    });



    selectedWrap.addEventListener('click', function (e) {

      var btn = e.target.closest('.sms-chip-remove');

      if (!btn) return;

      e.preventDefault();

      toggleItem(btn.getAttribute('data-code'), false);

    });



    selectedWrap.addEventListener('input', function (e) {

      if (!e.target.classList.contains('sms-qty-input')) return;

      var chip = e.target.closest('.sms-chip');

      if (!chip) return;

      var code = chip.getAttribute('data-code');

      var entry = selected.find(function (s) { return s.code === code; });

      if (!entry) return;

      entry.selectedQty = clampQty(entry, e.target.value);

      e.target.value = entry.selectedQty;

    });



    document.addEventListener('click', function (e) {

      if (!root.contains(e.target)) setOpen(false);

    });



    renderSelected();

    renderDropdown();



    return {

      getSelected: function () { return selected.slice(); },

      reset: function () {

        selected = [];

        searchInput.value = '';

        renderSelected();

        renderDropdown();

        setOpen(false);

      },

      refresh: function () {

        var prev = selected.slice();

        selected = prev.map(function (p) {

          var fresh = getItems().find(function (i) { return i.code === p.code; });

          return fresh ? copyItem(fresh, p.selectedQty) : null;

        }).filter(Boolean);

        renderSelected();

        renderDropdown();

      }

    };

  }



  return { create: create };

})();


