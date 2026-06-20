/**
 * StockCatalog — Static UI stub (no localStorage / no seed data)
 * Data will be supplied by Laravel backend later.
 */
var StockCatalog = (function () {
  var STORAGE_KEY = 'clinic_stock_catalog';
  var LOW_QTY_THRESHOLD = 3;

  function noop() {}
  function empty() { return []; }
  function fail() { return { ok: false, error: 'no_data' }; }

  return {
    getAll: empty,
    saveAll: noop,
    ensureSeeded: noop,
    resetToSeed: noop,
    nextCode: function () { return ''; },
    nextPriceId: function () { return ''; },
    addItem: fail,
    updateItem: fail,
    removeItem: fail,
    issueQty: fail,
    returnQty: fail,
    resolveBarcode: function () { return null; },
    deriveBarcode: function (code) { return code ? 'BC-' + String(code).replace(/\D/g, '') : ''; },
    receiveStock: fail,
    wac: function () { return 0; },
    highestPrice: function () { return 0; },
    inventoryValue: function () { return 0; },
    getStagnant: empty,
    normalizeItem: function (item) { return item || {}; },
    syncStatus: noop,
    formatPrice: function (n) {
      var num = Number(n) || 0;
      return num.toLocaleString('ar-EG') + ' ج.م';
    },
    getPriceSummary: function (prices) {
      var list = (prices || []).filter(function (p) { return Number(p.amount) > 0; });
      if (!list.length) return { count: 0, min: 0, max: 0 };
      var amounts = list.map(function (p) { return Number(p.amount) || 0; });
      return {
        count: list.length,
        min: Math.min.apply(null, amounts),
        max: Math.max.apply(null, amounts)
      };
    },
    DEFAULT: [],
    STORAGE_KEY: STORAGE_KEY,
    LOW_QTY_THRESHOLD: LOW_QTY_THRESHOLD
  };
})();
