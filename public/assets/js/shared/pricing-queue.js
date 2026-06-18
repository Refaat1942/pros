/**
 * PricingQueue — Static UI stub (no localStorage / no seed data)
 */
var PricingQueue = (function () {
  var STORAGE_KEY = 'clinic_pricing_queue';
  var STEP_LABELS = ['موافقة الأدمن', 'إرسال للاستقبال — عرض سعر'];

  function noop() {}
  function empty() { return []; }
  function nullFn() { return null; }

  function formatMoney(n) {
    return Number(n || 0).toLocaleString('ar-EG') + ' ج.م';
  }

  return {
    STORAGE_KEY: STORAGE_KEY,
    SEED_VERSION: 0,
    STEP_LABELS: STEP_LABELS,
    ensureSeeded: noop,
    getAll: empty,
    saveAll: noop,
    getById: nullFn,
    add: nullFn,
    approve: nullFn,
    estimateTotal: function () { return 0; },
    formatMoney: formatMoney,
    highestUnitPrice: function () { return 0; },
    findStockItem: nullFn
  };
})();
