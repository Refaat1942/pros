/**
 * تحقق مبلغ التحصيل — مديونيات مدنية وعسكرية.
 */
(function (global) {
  'use strict';

  var INVALID_CLASS = 'debt-collect-input--invalid';
  var INPUT_SELECTOR = '.debt-collect-amount-input';
  var alerting = false;

  function fmtMoney(n) {
    return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function getRemaining(row) {
    return parseFloat(row && row.dataset.remaining) || 0;
  }

  function markInvalid(input, invalid) {
    if (!input) return;
    input.classList.toggle(INVALID_CLASS, !!invalid);
    input.setAttribute('aria-invalid', invalid ? 'true' : 'false');
  }

  function showError(msg) {
    if (alerting) return;
    alerting = true;
    try {
      window.alert(msg);
    } finally {
      alerting = false;
    }
  }

  function exceedsRemaining(amount, remaining) {
    return amount > remaining + 0.009;
  }

  function validateAmount(input, remaining, options) {
    options = options || {};
    if (!input) return false;

    var amount = parseFloat(input.value);
    if (!amount || amount <= 0) {
      markInvalid(input, true);
      if (options.alert) {
        showError('أدخل المبلغ الذي حوّلته لحساب الإدارة.');
      }
      return false;
    }

    if (exceedsRemaining(amount, remaining)) {
      markInvalid(input, true);
      if (options.alert) {
        showError('لا يمكن أن يكون المبلغ المحصّل أكبر من المتبقي (' + fmtMoney(remaining) + ' ج.م).');
      }
      return false;
    }

    markInvalid(input, false);
    return true;
  }

  function syncInvalidState(input, row) {
    var amount = parseFloat(input.value);
    if (!amount || amount <= 0) {
      markInvalid(input, false);
      return;
    }
    markInvalid(input, exceedsRemaining(amount, getRemaining(row)));
  }

  function bind(root) {
    root = root || document;

    root.addEventListener('input', function (e) {
      var input = e.target.closest(INPUT_SELECTOR);
      if (!input) return;
      var row = input.closest('[data-remaining]');
      if (!row) return;
      syncInvalidState(input, row);
    }, true);

    // تمييز بصري + تنبيه عند تجاوز المتبقي (blur)
    root.addEventListener('blur', function (e) {
      var input = e.target.closest(INPUT_SELECTOR);
      if (!input) return;
      var row = input.closest('[data-remaining]');
      if (!row) return;
      var amount = parseFloat(input.value);
      if (!amount || amount <= 0) {
        markInvalid(input, false);
        return;
      }
      var remaining = getRemaining(row);
      if (exceedsRemaining(amount, remaining)) {
        markInvalid(input, true);
        showError('لا يمكن أن يكون المبلغ المحصّل أكبر من المتبقي (' + fmtMoney(remaining) + ' ج.م).');
        return;
      }
      markInvalid(input, false);
    }, true);
  }

  global.DebtCollectValidation = {
    fmtMoney: fmtMoney,
    validateAmount: validateAmount,
    showError: showError,
    markInvalid: markInvalid,
    bind: bind,
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { bind(document); });
  } else {
    bind(document);
  }
})(window);
