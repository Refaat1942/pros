/**
 * BomInventory — Static UI stub (no localStorage / no seed data)
 */
var BomInventory = (function () {
  var STORAGE_KEY = 'clinic_bom_inventory';

  var STAGES = [
    { key: 'raw', label: 'خام' },
    { key: 'wip', label: 'تحت التشغيل' },
    { key: 'finished', label: 'تام' }
  ];

  function noop() {}
  function empty() { return []; }
  function fail() { return { ok: false, error: 'no_data' }; }
  function nullFn() { return null; }

  function formatMoney(n) {
    return Number(n || 0).toLocaleString('ar-EG') + ' ج.م';
  }

  function getStageLabel(key) {
    var s = STAGES.find(function (x) { return x.key === key; });
    return s ? s.label : (key || '—');
  }

  function getStageBadgeClass(key) {
    if (key === 'raw') return 'badge-raw';
    if (key === 'wip') return 'badge-wip';
    if (key === 'finished') return 'badge-finished';
    return '';
  }

  function renderItemsList() {
    return '<span class="text-muted">—</span>';
  }

  return {
    STORAGE_KEY: STORAGE_KEY,
    SEED_VERSION: 0,
    STAGES: STAGES,
    ensureSeeded: noop,
    resetToSeed: noop,
    getAll: empty,
    saveAll: noop,
    getById: nullFn,
    getByCaseId: nullFn,
    getByOrderRef: nullFn,
    getByStage: empty,
    getSummary: function () { return { raw: 0, wip: 0, finished: 0, total: 0 }; },
    bomTotalValue: function () { return 0; },
    createFromApproval: fail,
    canReleaseToWip: function () { return { ok: false, reason: 'no_data' }; },
    releaseToWip: fail,
    releaseToWipByBarcode: fail,
    verifyBarcodes: function () { return { ok: false, reason: 'no_data' }; },
    resolveScanCode: nullFn,
    getReturnableQty: function () { return 0; },
    recordItemReturn: fail,
    completeToFinished: fail,
    completeByCaseId: fail,
    canDeliver: function () { return { ok: false, reason: 'no_data' }; },
    getReadyForDelivery: empty,
    parseQuoteItems: empty,
    getStageLabel: getStageLabel,
    getStageBadgeClass: getStageBadgeClass,
    renderItemsList: renderItemsList,
    formatMoney: formatMoney
  };
})();
