/**
 * CreditNotes — Static UI stub (no localStorage / no seed data)
 */
var CreditNotes = (function () {
  var STORAGE_KEY = 'clinic_credit_notes';
  var DEBTS_KEY = 'clinic_contract_debts';

  function noop() {}
  function empty() { return []; }
  function fail() { return { ok: false, error: 'no_data' }; }
  function nullFn() { return null; }

  function formatMoney(n) {
    return Number(n || 0).toLocaleString('ar-EG') + ' ج.م';
  }

  function statusLabel(status) {
    if (status === 'approved') return 'معتمد';
    if (status === 'rejected') return 'مرفوض';
    if (status === 'pending') return 'قيد المراجعة';
    return status || '—';
  }

  function typeLabel(type) {
    return type === 'full' ? 'كامل' : 'جزئي';
  }

  return {
    STORAGE_KEY: STORAGE_KEY,
    DEBTS_KEY: DEBTS_KEY,
    SEED_VERSION: 0,
    ensureSeeded: noop,
    getAll: empty,
    getById: nullFn,
    getDebts: empty,
    getEligibleCases: empty,
    createNote: fail,
    approveNote: fail,
    rejectNote: fail,
    statusLabel: statusLabel,
    typeLabel: typeLabel,
    formatMoney: formatMoney,
    findDebtCompany: nullFn
  };
})();
