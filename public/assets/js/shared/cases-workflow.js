/**
 * CasesWorkflow — Static UI stub (no localStorage / no seed data)
 */
var CasesWorkflow = (function () {
  var STORAGE_KEY = 'clinic_cases_workflow';

  var STAGES = [
    { key: 'reception', label: 'استقبال' },
    { key: 'exam', label: 'كشف' },
    { key: 'technical', label: 'توصيف فني' },
    { key: 'cost_calc', label: 'حساب تكلفة' },
    { key: 'admin_approval', label: 'انتظار موافقة الأدمن' },
    { key: 'quote', label: 'عرض سعر' },
    { key: 'waiting_return', label: 'بانتظار موافقة الجهة' },
    { key: 'manufacturing', label: 'جاري التصنيع' },
    { key: 'delivered', label: 'تم التسليم' }
  ];

  var MANUFACTURING_STAGES = [
    { key: 'warehouse', label: 'تحضير مخزن' },
    { key: 'workshop', label: 'الورشة' },
    { key: 'fitting', label: 'تركيب وتجربة' },
    { key: 'quality', label: 'فحص جودة' },
    { key: 'issue', label: 'صرف بالباركود' },
    { key: 'generation', label: 'توليد' },
    { key: 'assembly', label: 'تجميع' },
    { key: 'casting', label: 'صب' },
    { key: 'finishing', label: 'تشطيب وفحص' },
    { key: 'closed', label: 'حالة مغلقة' }
  ];

  var PATIENT_TYPES = {
    civilian: { label: 'مدني', icon: '', badge: 'civilian' },
    military: { label: 'عسكري', icon: '', badge: 'military' }
  };

  function noop() {}
  function empty() { return []; }
  function nullFn() { return null; }

  function getStageLabel(key) {
    var s = STAGES.find(function (x) { return x.key === key; });
    return s ? s.label : (key || '—');
  }

  function getManufacturingLabel(key) {
    var s = MANUFACTURING_STAGES.find(function (x) { return x.key === key; });
    return s ? s.label : (key || '—');
  }

  function formatMoney(n) {
    return Number(n || 0).toLocaleString('ar-EG') + ' ج.م';
  }

  function renderPipeline() { return '—'; }
  function renderQuoteRefCell() { return '—'; }

  return {
    STORAGE_KEY: STORAGE_KEY,
    SEED_VERSION: 0,
    STAGES: STAGES,
    REF_DATE: null,
    ensureSeeded: noop,
    getAll: empty,
    saveAll: noop,
    getById: nullFn,
    getByOrderRef: nullFn,
    getByPricingQueueId: nullFn,
    getByQuoteId: nullFn,
    upsert: nullFn,
    setStage: nullFn,
    setManufacturingStage: nullFn,
    onCostCalculated: nullFn,
    onAdminApproved: nullFn,
    onQuoteIssued: nullFn,
    onApprovalConfirmed: nullFn,
    onDelivered: nullFn,
    getBucket: empty,
    getStageLabel: getStageLabel,
    getManufacturingLabel: getManufacturingLabel,
    MANUFACTURING_STAGES: MANUFACTURING_STAGES,
    PATIENT_TYPES: PATIENT_TYPES,
    getPatientTypeMeta: function (t) { return PATIENT_TYPES[t] || PATIENT_TYPES.civilian; },
    getPatientTypeLabel: function (t) { return (PATIENT_TYPES[t] || PATIENT_TYPES.civilian).label; },
    getByPatientId: nullFn,
    getByPatientQr: nullFn,
    isMilitary: function () { return false; },
    generateWorkOrderNo: function () { return ''; },
    turnaroundDays: function () { return 0; },
    getSlaSummary: function () { return { avgTat: 0, breached: [], slaLimit: 21, openCount: 0 }; },
    getTypeDistribution: function () { return { total: 0, military: 0, civilian: 0 }; },
    renderPipeline: renderPipeline,
    daysBetween: function () { return 0; },
    renderQuoteRefCell: renderQuoteRefCell,
    getPricingRef: function () { return '—'; },
    formatMoney: formatMoney,
    parseDate: function () { return null; },
    formatDate: function () { return '—'; }
  };
})();
