/**
 * Arabic date pickers for dashboard filter toolbars (Flatpickr).
 */
(function () {
  if (typeof flatpickr === 'undefined') {
    return;
  }

  var Arabic = flatpickr.l10ns.ar;

  var defaultOpts = {
    locale: Arabic,
    dateFormat: 'Y-m-d',
    altInput: true,
    altFormat: 'j F Y',
    allowInput: true,
    disableMobile: true,
    monthSelectorType: 'dropdown',
    altInputClass: 'dashboard-date-alt',
  };

  var selectors = [
    '.reports-date-filter input[type="date"]',
    '.data-toolbar input[type="date"]',
    '.adj-history-filter input[type="date"]',
    '.adj-history-filter__field input[type="date"]',
    'input.date-filter-input',
    '#rcvDate',
    'input[type="date"][name="from"]',
    'input[type="date"][name="to"]',
    'input[type="date"][name="date_from"]',
    'input[type="date"][name="date_to"]',
  ].join(', ');

  function initInput(el) {
    if (!el || el.dataset.arDatepicker === '1' || el._flatpickr) {
      return el && el._flatpickr ? el._flatpickr : null;
    }

    if (el.type === 'date') {
      el.type = 'text';
      el.setAttribute('inputmode', 'numeric');
      if (!el.getAttribute('placeholder')) {
        el.setAttribute('placeholder', 'YYYY-MM-DD');
      }
      el.setAttribute('dir', 'ltr');
    }

    el.dataset.arDatepicker = '1';

    return flatpickr(el, defaultOpts);
  }

  function initAll(root) {
    (root || document).querySelectorAll(selectors).forEach(initInput);
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.date-filter-picker');
    if (!btn) {
      return;
    }
    var id = btn.getAttribute('data-target');
    var input = id ? document.getElementById(id) : null;
    if (!input) {
      return;
    }
    if (!input._flatpickr) {
      initInput(input);
    }
    if (input._flatpickr) {
      input._flatpickr.open();
    }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initAll();
    });
  } else {
    initAll();
  }

  window.DashboardDateFilters = {
    init: initAll,
    initInput: initInput,
  };
})();
