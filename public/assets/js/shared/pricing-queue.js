/**
 * PricingQueue — طابور اعتماد التسعير (مشترك بين المخزون والإدارة)
 */
var PricingQueue = (function () {
  var STORAGE_KEY = 'clinic_pricing_queue';
  var SEED_VERSION = 4;

  var STEP_LABELS = ['موافقة الأدمن', 'إرسال للاستقبال — عرض سعر'];

  var DEFAULT = [
    {
      id: 'QT-PENDING-001',
      orderRef: 'ORD-2026-0847',
      patient: 'محمود عبد الرحمن',
      company: 'التأمين الوطني',
      date: '08/06/2026',
      items: 3,
      doctor: 'د. سارة عبدالله',
      recommendations: [
        { name: 'ركبة هيدروليكية', code: 'ITM-001', qty: 1 },
        { name: 'قدم Carbon Spring', code: 'ITM-003', qty: 1 },
        { name: 'بطانة Silicone', code: 'ITM-004', qty: 1 }
      ],
      patientType: 'civilian',
      statusKey: 'pending',
      statusLabel: 'في انتظار موافقة الأدمن',
      step: 1,
      approvedAt: null,
      approvedBy: null
    },
    {
      id: 'QT-PENDING-002',
      orderRef: 'ORD-2026-0845',
      patient: 'فاطمة حسين محمد',
      company: 'التأمين الصحي',
      date: '07/06/2026',
      items: 2,
      doctor: 'د. سارة عبدالله',
      recommendations: [
        { name: 'ركبة Polycentric', code: 'ITM-002', qty: 1 },
        { name: 'Pin Lock', code: 'ITM-006', qty: 1 }
      ],
      patientType: 'civilian',
      statusKey: 'pending',
      statusLabel: 'في انتظار موافقة الأدمن',
      step: 1,
      approvedAt: null,
      approvedBy: null
    },
    {
      id: 'QT-PENDING-003',
      orderRef: 'ORD-2026-0839',
      patient: 'مريم خالد إبراهيم',
      company: 'مصر للتأمين',
      date: '07/06/2026',
      items: 2,
      doctor: 'د. سارة عبدالله',
      recommendations: [
        { name: 'قدم Carbon Spring', code: 'ITM-003', qty: 1 },
        { name: 'جوارب تجويف', code: 'ITM-009', qty: 1 }
      ],
      patientType: 'civilian',
      statusKey: 'sent',
      statusLabel: 'معتمد — جاهز لعرض السعر',
      step: 2,
      approvedAt: '07/06/2026 14:30',
      approvedBy: 'أحمد محمود'
    }
  ];

  function resolvePatientType(item) {
    if (item.patientType === 'military' || item.patientType === 'civilian') return item.patientType;
    if (typeof CasesWorkflow !== 'undefined') {
      var c = CasesWorkflow.getByOrderRef(item.orderRef);
      if (c) {
        if (c.patientType === 'military' || c.patientType === 'civilian') return c.patientType;
        if (typeof CasesWorkflow.isMilitary === 'function' && CasesWorkflow.isMilitary(c)) return 'military';
      }
    }
    var company = String(item.company || '');
    if (/قوات|مسلح|عسكر|الدفاع|الحرس|سياد/.test(company)) return 'military';
    return 'civilian';
  }

  function statusMeta(key) {
    if (key === 'sent') {
      return { statusKey: 'sent', statusLabel: 'معتمد — جاهز لعرض السعر', step: 2 };
    }
    return { statusKey: 'pending', statusLabel: 'في انتظار موافقة الأدمن', step: 1 };
  }

  function normalizeItem(item) {
    var meta = statusMeta(item.statusKey === 'sent' ? 'sent' : 'pending');
    return Object.assign({}, item, {
      recommendations: item.recommendations || [],
      patientType: resolvePatientType(item),
      statusKey: meta.statusKey,
      statusLabel: meta.statusLabel,
      step: meta.step,
      approvedAt: item.approvedAt || null,
      approvedBy: item.approvedBy || null
    });
  }

  function mergeWithDefaults(list) {
    return list.map(function (item) {
      var def = DEFAULT.find(function (d) { return d.id === item.id; });
      if (def) {
        if (!item.recommendations || !item.recommendations.length) item.recommendations = def.recommendations.slice();
        if (!item.doctor) item.doctor = def.doctor;
        if (!item.patientType && def.patientType) item.patientType = def.patientType;
      }
      return normalizeItem(item);
    });
  }

  function load() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      if (raw) {
        var parsed = JSON.parse(raw);
        if (Array.isArray(parsed) && parsed.length) {
          return mergeWithDefaults(parsed);
        }
      }
    } catch (e) { /* ignore */ }
    return DEFAULT.map(normalizeItem);
  }

  function saveAll(list) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(list.map(normalizeItem)));
  }

  function ensureSeeded() {
    var savedVersion = localStorage.getItem(STORAGE_KEY + '_version');
    if (!localStorage.getItem(STORAGE_KEY) || savedVersion !== String(SEED_VERSION)) {
      saveAll(DEFAULT);
      localStorage.setItem(STORAGE_KEY + '_version', String(SEED_VERSION));
    }
  }

  function getAll() {
    return load();
  }

  function getById(id) {
    return getAll().find(function (p) { return p.id === id; }) || null;
  }

  function nextId(list) {
    var max = 0;
    list.forEach(function (p) {
      var m = (p.id || '').match(/QT-PENDING-(\d+)/);
      if (m) max = Math.max(max, parseInt(m[1], 10));
    });
    return 'QT-PENDING-' + String(max + 1).padStart(3, '0');
  }

  function add(entry) {
    var list = getAll();
    var meta = statusMeta('pending');
    var item = normalizeItem(Object.assign({
      id: nextId(list),
      approvedAt: null,
      approvedBy: null
    }, entry, meta));
    list.unshift(item);
    saveAll(list);
    if (typeof CasesWorkflow !== 'undefined') {
      CasesWorkflow.onCostCalculated({
        orderRef: item.orderRef,
        patient: item.patient,
        company: item.company,
        date: item.date,
        recommendations: item.recommendations,
        pricingQueueId: item.id,
        path: 'standard'
      });
    }
    return item;
  }

  function approve(id, adminName) {
    var list = getAll();
    var idx = list.findIndex(function (p) { return p.id === id; });
    if (idx === -1) return null;
    if (list[idx].statusKey === 'sent') return list[idx];
    var now = new Date();
    var stamp = String(now.getDate()).padStart(2, '0') + '/' +
      String(now.getMonth() + 1).padStart(2, '0') + '/' +
      now.getFullYear() + ' ' +
      String(now.getHours()).padStart(2, '0') + ':' +
      String(now.getMinutes()).padStart(2, '0');
    list[idx] = normalizeItem(Object.assign({}, list[idx], statusMeta('sent'), {
      approvedAt: stamp,
      approvedBy: adminName || 'الإدارة'
    }));
    saveAll(list);
    if (typeof CasesWorkflow !== 'undefined') {
      var total = estimateTotal(list[idx].recommendations);
      CasesWorkflow.onAdminApproved(id, {
        orderRef: list[idx].orderRef,
        patient: list[idx].patient,
        company: list[idx].company,
        quoteId: list[idx].id.replace('QT-PENDING', 'QT-2026'),
        total: total
      });
    }
    return list[idx];
  }

  function findStockItem(name, code) {
    if (typeof StockCatalog === 'undefined') return null;
    var items = StockCatalog.getAll();
    if (code) {
      var byCode = items.find(function (i) { return i.code === code; });
      if (byCode) return byCode;
    }
    return items.find(function (i) { return i.name === name; }) || null;
  }

  function highestUnitPrice(stockItem) {
    if (!stockItem || !stockItem.prices || !stockItem.prices.length) return 0;
    return Math.max.apply(null, stockItem.prices.map(function (p) { return p.amount || 0; }));
  }

  function estimateTotal(recommendations) {
    if (!recommendations || !recommendations.length) return 0;
    return recommendations.reduce(function (sum, rec) {
      var name = typeof rec === 'string' ? rec : rec.name;
      var code = typeof rec === 'string' ? null : rec.code;
      var qty = typeof rec === 'string' ? 1 : (rec.qty || rec.selectedQty || 1);
      var stock = findStockItem(name, code);
      return sum + highestUnitPrice(stock) * qty;
    }, 0);
  }

  function formatMoney(n) {
    return Number(n || 0).toLocaleString('ar-EG') + ' ج.م';
  }

  return {
    STORAGE_KEY: STORAGE_KEY,
    SEED_VERSION: SEED_VERSION,
    STEP_LABELS: STEP_LABELS,
    ensureSeeded: ensureSeeded,
    getAll: getAll,
    saveAll: saveAll,
    getById: getById,
    add: add,
    approve: approve,
    estimateTotal: estimateTotal,
    formatMoney: formatMoney,
    highestUnitPrice: highestUnitPrice,
    findStockItem: findStockItem
  };
})();
