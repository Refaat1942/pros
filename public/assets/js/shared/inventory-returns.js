/**
 * InventoryReturns — Static UI stub (no localStorage / no seed data)
 */
var InventoryReturns = (function () {
  var STORAGE_KEY = 'clinic_inventory_returns';

  function noop() {}
  function empty() { return []; }
  function fail() { return { ok: false, error: 'no_data' }; }
  function nullFn() { return null; }

  function statusLabel(status) {
    if (status === 'completed') return 'مكتمل';
    if (status === 'partial') return 'جزئي';
    if (status === 'authorized') return 'مصرّح';
    return status || '—';
  }

  return {
    STORAGE_KEY: STORAGE_KEY,
    SEED_VERSION: 0,
    ensureSeeded: noop,
    getAll: empty,
    getById: nullFn,
    getEligibleBoms: empty,
    createReturnNote: fail,
    verifyReturnBarcode: fail,
    processReturnScan: fail,
    getSummary: function () { return { total: 0, authorized: 0, partial: 0, completed: 0 }; },
    statusLabel: statusLabel
  };
})();
