/**
 * CasesWorkflow — متابعة حالات المرضى عبر مسار العمل الكامل
 */
var CasesWorkflow = (function () {
  var STORAGE_KEY = 'clinic_cases_workflow';
  var SEED_VERSION = 6;
  var REF_DATE = new Date(2026, 5, 8);

  var STAGES = [
    { key: 'reception', label: 'استقبال' },
    { key: 'exam', label: 'كشف' },
    { key: 'technical', label: 'توصيف فني' },
    { key: 'cost_calc', label: 'حساب تكلفة' },
    { key: 'admin_approval', label: 'انتظار موافقة الأدمن' },
    { key: 'quote', label: 'عرض سعر' },
    { key: 'waiting_return', label: 'انتظار رجوع العميل' },
    { key: 'manufacturing', label: 'جاري التصنيع' },
    { key: 'delivered', label: 'تم التسليم' }
  ];

  var MANUFACTURING_STAGES = {
    warehouse: 'تحضير مخزن',
    workshop: 'الورشة',
    fitting: 'تركيب وتجربة',
    quality: 'فحص جودة',
    issue: 'صرف بالباركود',
    generation: 'توليد',
    assembly: 'تجميع',
    casting: 'صب',
    finishing: 'تشطيب وفحص',
    closed: 'حالة مغلقة'
  };

  var PATIENT_TYPES = {
    civilian: { key: 'civilian', label: 'مدني', icon: '🌐', badge: 'civilian' },
    military: { key: 'military', label: 'عسكري', icon: '🪖', badge: 'military' }
  };

  var DEFAULT = [
    {
      id: 'CASE-2026-001',
      orderRef: 'ORD-2026-0847',
      patient: 'محمود عبد الرحمن',
      company: 'التأمين الوطني',
      stageKey: 'admin_approval',
      quoteId: null,
      quoteDate: null,
      quoteTotal: 162000,
      approvalDate: null,
      approvalConfirmedAt: null,
      manufacturingStage: null,
      totalCost: 162000,
      paid: 0,
      deliveredAt: null,
      createdAt: '08/06/2026',
      pricingQueueId: 'QT-PENDING-001',
      path: 'standard'
    },
    {
      id: 'CASE-2026-002',
      orderRef: 'ORD-2026-0845',
      patient: 'فاطمة حسين محمد',
      company: 'التأمين الصحي',
      stageKey: 'admin_approval',
      quoteId: null,
      quoteDate: null,
      quoteTotal: 77800,
      approvalDate: null,
      approvalConfirmedAt: null,
      manufacturingStage: null,
      totalCost: 77800,
      paid: 0,
      deliveredAt: null,
      createdAt: '07/06/2026',
      pricingQueueId: 'QT-PENDING-002',
      path: 'standard'
    },
    {
      id: 'CASE-2026-003',
      orderRef: 'ORD-2026-0839',
      patient: 'مريم خالد إبراهيم',
      company: 'مصر للتأمين',
      stageKey: 'waiting_return',
      quoteId: 'QT-2026-0839',
      quoteDate: '28/05/2026',
      quoteTotal: 55450,
      approvalDate: null,
      approvalConfirmedAt: null,
      manufacturingStage: null,
      totalCost: 55450,
      paid: 0,
      deliveredAt: null,
      createdAt: '25/05/2026',
      pricingQueueId: 'QT-PENDING-003',
      path: 'standard'
    },
    {
      id: 'CASE-2026-004',
      orderRef: 'ORD-2026-0821',
      patient: 'يوسف عمر محسن',
      company: 'إدارة القوات المسلحة الطبية',
      patientType: 'military',
      rank: 'نقيب',
      sovereignEntity: 'القوات المسلحة',
      stageKey: 'manufacturing',
      quoteId: 'QT-2026-0821',
      quoteDate: '20/05/2026',
      quoteTotal: 95000,
      approvalDate: '21/05/2026',
      approvalConfirmedAt: '21/05/2026 08:30',
      manufacturingStage: 'assembly',
      workOrderNo: 'WO-2026-0821',
      totalCost: 95000,
      paid: 0,
      deliveredAt: null,
      createdAt: '18/05/2026',
      pricingQueueId: null,
      path: 'military',
      recommendations: [
        { name: 'ركبة هيدروليكية', code: 'ITM-001', qty: 1 },
        { name: 'محول Pyramidal', code: 'ITM-005', qty: 1 }
      ]
    },
    {
      id: 'CASE-2026-005',
      orderRef: 'ORD-2026-0810',
      patient: 'سارة أحمد فؤاد',
      company: 'التأمين الوطني',
      stageKey: 'manufacturing',
      quoteId: 'QT-2026-0810',
      quoteDate: '15/05/2026',
      quoteTotal: 110500,
      approvalDate: '22/05/2026',
      approvalConfirmedAt: '22/05/2026 11:20',
      manufacturingStage: 'workshop',
      totalCost: 110500,
      paid: 50000,
      deliveredAt: null,
      createdAt: '10/05/2026',
      pricingQueueId: null,
      path: 'standard',
      recommendations: [
        { name: 'ركبة هيدروليكية', code: 'ITM-001', qty: 1 },
        { name: 'قدم Carbon Spring', code: 'ITM-003', qty: 1 },
        { name: 'بطانة Silicone', code: 'ITM-004', qty: 1 }
      ]
    },
    {
      id: 'CASE-2026-006',
      orderRef: 'ORD-2026-0798',
      patient: 'هدى محمود سعيد',
      company: 'صندوق ذوي الإعاقة',
      stageKey: 'manufacturing',
      quoteId: 'QT-2026-0798',
      quoteDate: '01/05/2026',
      quoteTotal: 88500,
      approvalDate: '08/05/2026',
      approvalConfirmedAt: '08/05/2026 09:45',
      manufacturingStage: 'warehouse',
      totalCost: 88500,
      paid: 0,
      deliveredAt: null,
      createdAt: '28/04/2026',
      pricingQueueId: null,
      path: 'standard',
      recommendations: [
        { name: 'ركبة Polycentric', code: 'ITM-002', qty: 1 },
        { name: 'Pin Lock', code: 'ITM-006', qty: 1 }
      ]
    },
    {
      id: 'CASE-2026-009',
      orderRef: 'ORD-2026-0785',
      patient: 'ليلى حسام الدين',
      company: 'التأمين الوطني',
      stageKey: 'manufacturing',
      quoteId: 'QT-2026-0785',
      quoteDate: '28/05/2026',
      quoteTotal: 10400,
      approvalDate: '05/06/2026',
      approvalConfirmedAt: '05/06/2026 10:00',
      manufacturingStage: 'warehouse',
      totalCost: 10400,
      paid: 0,
      deliveredAt: null,
      createdAt: '25/05/2026',
      pricingQueueId: null,
      path: 'standard',
      recommendations: [
        { name: 'بطانة Gel', code: 'ITM-008', qty: 1 },
        { name: 'جوارب تجويف', code: 'ITM-009', qty: 2 }
      ]
    },
    {
      id: 'CASE-2026-010',
      orderRef: 'ORD-2026-0772',
      patient: 'عبدالله سامي رشاد',
      company: 'صندوق ذوي الإعاقة',
      stageKey: 'manufacturing',
      quoteId: 'QT-2026-0772',
      quoteDate: '20/05/2026',
      quoteTotal: 53000,
      approvalDate: '01/06/2026',
      approvalConfirmedAt: '01/06/2026 14:30',
      manufacturingStage: 'fitting',
      totalCost: 53000,
      paid: 20000,
      deliveredAt: null,
      createdAt: '15/05/2026',
      pricingQueueId: null,
      path: 'standard',
      recommendations: [
        { name: 'مفصل كوع', code: 'ITM-010', qty: 1 },
        { name: 'محول Pyramidal', code: 'ITM-005', qty: 1 }
      ]
    },
    {
      id: 'CASE-2026-007',
      orderRef: 'ORD-2026-0755',
      patient: 'كريم محمد علي',
      company: 'التأمين الصحي',
      stageKey: 'delivered',
      quoteId: 'QT-2026-0755',
      quoteDate: '10/04/2026',
      quoteTotal: 72000,
      approvalDate: '18/04/2026',
      approvalConfirmedAt: '18/04/2026 14:00',
      manufacturingStage: 'quality',
      totalCost: 72000,
      paid: 45000,
      deliveredAt: '02/05/2026',
      createdAt: '05/04/2026',
      pricingQueueId: null,
      path: 'standard'
    },
    {
      id: 'CASE-2026-008',
      orderRef: 'ORD-2026-0742',
      patient: 'أحمد فاروق نبيل',
      company: 'مصر للتأمين',
      stageKey: 'delivered',
      quoteId: 'QT-2026-0742',
      quoteDate: '20/03/2026',
      quoteTotal: 98500,
      approvalDate: '28/03/2026',
      approvalConfirmedAt: '28/03/2026 10:30',
      manufacturingStage: 'quality',
      totalCost: 98500,
      paid: 98500,
      deliveredAt: '15/04/2026',
      createdAt: '15/03/2026',
      pricingQueueId: null,
      path: 'standard'
    },
    {
      id: 'CASE-2026-011',
      orderRef: 'ORD-2026-0855',
      patient: 'منى إبراهيم حسن',
      company: 'التأمين الوطني',
      stageKey: 'manufacturing',
      quoteId: 'QT-2026-0855',
      quoteDate: '15/05/2026',
      quoteTotal: 89800,
      approvalDate: '20/05/2026',
      approvalConfirmedAt: '20/05/2026 09:30',
      manufacturingStage: 'quality',
      totalCost: 89800,
      paid: 40000,
      deliveredAt: null,
      createdAt: '10/05/2026',
      pricingQueueId: null,
      path: 'standard',
      recommendations: [
        { name: 'ركبة Polycentric', code: 'ITM-002', qty: 1 },
        { name: 'بطانة Silicone', code: 'ITM-004', qty: 1 },
        { name: 'Pin Lock', code: 'ITM-006', qty: 1 }
      ]
    }
  ];

  function parseDate(str) {
    if (!str) return null;
    var parts = String(str).split(/[\s\/]/);
    if (parts.length < 3) return null;
    var d = parseInt(parts[0], 10);
    var m = parseInt(parts[1], 10) - 1;
    var y = parseInt(parts[2], 10);
    if (isNaN(d) || isNaN(m) || isNaN(y)) return null;
    return new Date(y, m, d);
  }

  function formatDate(d) {
    if (!d || !(d instanceof Date) || isNaN(d.getTime())) return '—';
    return String(d.getDate()).padStart(2, '0') + '/' +
      String(d.getMonth() + 1).padStart(2, '0') + '/' +
      d.getFullYear();
  }

  function daysBetween(fromStr, toDate) {
    var from = parseDate(fromStr);
    if (!from) return 0;
    var to = toDate || REF_DATE;
    var diff = to.getTime() - from.getTime();
    return Math.max(0, Math.floor(diff / 86400000));
  }

  function stageMeta(key) {
    var idx = STAGES.findIndex(function (s) { return s.key === key; });
    if (idx === -1) idx = 0;
    return {
      stageKey: STAGES[idx].key,
      stageLabel: STAGES[idx].label,
      stageIndex: idx
    };
  }

  function derivePatientType(c) {
    if (c.patientType === 'military' || c.patientType === 'civilian') return c.patientType;
    var company = String(c.company || '');
    if (/قوات|مسلح|عسكر|الدفاع الجوي|الحرس|سياد/.test(company)) return 'military';
    return 'civilian';
  }

  function derivePatientId(c) {
    if (c.patientId) return c.patientId;
    var m = (c.id || '').match(/(\d+)/);
    var seq = m ? m[1].slice(-4).padStart(4, '0') : '0000';
    var prefix = derivePatientType(c) === 'military' ? 'MIL' : 'CIV';
    return 'PT-' + prefix + '-' + seq;
  }

  function normalizeCase(c) {
    var meta = stageMeta(c.stageKey || 'reception');
    var ptype = derivePatientType(c);
    return Object.assign({}, c, {
      stageKey: meta.stageKey,
      stageLabel: meta.stageLabel,
      stageIndex: meta.stageIndex,
      quoteTotal: c.quoteTotal || c.totalCost || 0,
      totalCost: c.totalCost || c.quoteTotal || 0,
      paid: c.paid || 0,
      remaining: Math.max(0, (c.totalCost || c.quoteTotal || 0) - (c.paid || 0)),
      recommendations: Array.isArray(c.recommendations) ? c.recommendations : [],
      patientType: ptype,
      patientId: derivePatientId(c),
      patientQr: c.patientQr || ('QR-' + derivePatientId(c)),
      rank: c.rank || (ptype === 'military' ? 'غير محدد' : null),
      sovereignEntity: c.sovereignEntity || null,
      workOrderNo: c.workOrderNo || null
    });
  }

  function load() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      if (raw) {
        var parsed = JSON.parse(raw);
        if (Array.isArray(parsed) && parsed.length) {
          return parsed.map(normalizeCase);
        }
      }
    } catch (e) { /* ignore */ }
    return DEFAULT.map(normalizeCase);
  }

  function saveAll(list) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(list.map(normalizeCase)));
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
    return getAll().find(function (c) { return c.id === id; }) || null;
  }

  function getByOrderRef(orderRef) {
    return getAll().find(function (c) { return c.orderRef === orderRef; }) || null;
  }

  function getByPricingQueueId(pricingId) {
    return getAll().find(function (c) { return c.pricingQueueId === pricingId; }) || null;
  }

  function getByQuoteId(quoteId) {
    return getAll().find(function (c) { return c.quoteId === quoteId; }) || null;
  }

  function nextCaseId(list) {
    var max = 0;
    list.forEach(function (c) {
      var m = (c.id || '').match(/CASE-2026-(\d+)/);
      if (m) max = Math.max(max, parseInt(m[1], 10));
    });
    return 'CASE-2026-' + String(max + 1).padStart(3, '0');
  }

  function upsert(entry) {
    var list = getAll();
    var idx = -1;
    if (entry.id) idx = list.findIndex(function (c) { return c.id === entry.id; });
    else if (entry.orderRef) idx = list.findIndex(function (c) { return c.orderRef === entry.orderRef; });

    var item = normalizeCase(Object.assign({}, idx >= 0 ? list[idx] : {}, entry));
    if (idx >= 0) list[idx] = item;
    else {
      item.id = item.id || nextCaseId(list);
      list.unshift(item);
    }
    saveAll(list);
    return item;
  }

  function setStage(idOrOrderRef, stageKey, extra) {
    var list = getAll();
    var idx = list.findIndex(function (c) {
      return c.id === idOrOrderRef || c.orderRef === idOrOrderRef;
    });
    if (idx === -1) return null;
    list[idx] = normalizeCase(Object.assign({}, list[idx], { stageKey: stageKey }, extra || {}));
    saveAll(list);
    return list[idx];
  }

  function onCostCalculated(data) {
    var total = data.quoteTotal || data.totalCost || 0;
    if (typeof PricingQueue !== 'undefined' && data.recommendations) {
      total = PricingQueue.estimateTotal(data.recommendations);
    }
    return upsert({
      orderRef: data.orderRef,
      patient: data.patient,
      company: data.company,
      stageKey: 'admin_approval',
      quoteTotal: total,
      totalCost: total,
      createdAt: data.date || formatDate(REF_DATE),
      pricingQueueId: data.pricingQueueId || null,
      path: data.path || 'standard',
      recommendations: data.recommendations || []
    });
  }

  function onAdminApproved(pricingId, quoteData) {
    var c = getByPricingQueueId(pricingId) || (quoteData && quoteData.orderRef ? getByOrderRef(quoteData.orderRef) : null);
    var total = quoteData && quoteData.total ? quoteData.total : (c ? c.totalCost : 0);
    var quoteId = quoteData && quoteData.quoteId ? quoteData.quoteId : (c && c.quoteId ? c.quoteId : pricingId.replace('QT-PENDING', 'QT-2026'));
    var extra = {
      stageKey: 'quote',
      quoteId: quoteId,
      quoteTotal: total,
      totalCost: total
    };
    if (c) return setStage(c.id, 'quote', extra);
    if (quoteData && quoteData.orderRef) {
      return upsert(Object.assign({ stageKey: 'quote' }, quoteData, extra));
    }
    return null;
  }

  function onQuoteIssued(quoteId, data) {
    var c = getByQuoteId(quoteId) || (data && data.orderRef ? getByOrderRef(data.orderRef) : null);
    var patch = {
      stageKey: 'waiting_return',
      quoteId: quoteId,
      quoteDate: data && data.date ? data.date : formatDate(REF_DATE),
      quoteTotal: data && data.total ? data.total : (c ? c.quoteTotal : 0),
      totalCost: data && data.total ? data.total : (c ? c.totalCost : 0)
    };
    if (c) return setStage(c.id, 'waiting_return', patch);
    if (data && data.orderRef) return upsert(Object.assign({ stageKey: 'waiting_return' }, data, patch));
    return null;
  }

  function onApprovalConfirmed(data) {
    var c = (data.quoteId && getByQuoteId(data.quoteId)) ||
      (data.orderRef && getByOrderRef(data.orderRef)) ||
      (data.patient && getAll().find(function (x) {
        return x.patient === data.patient && x.stageKey === 'waiting_return';
      }));
    var now = formatDate(REF_DATE);
    var stamp = now + ' ' + String(REF_DATE.getHours()).padStart(2, '0') + ':' +
      String(REF_DATE.getMinutes()).padStart(2, '0');
    var patch = {
      stageKey: 'manufacturing',
      approvalDate: data.approvalDate || now,
      approvalConfirmedAt: data.approvalConfirmedAt || stamp,
      manufacturingStage: data.manufacturingStage || 'issue',
      workOrderNo: data.workOrderNo || (c && c.workOrderNo) || generateWorkOrderNo(c || data),
      paid: data.paid || (c ? c.paid : 0)
    };
    var result;
    if (c) result = setStage(c.id, 'manufacturing', patch);
    else if (data.orderRef) result = upsert(Object.assign({ stageKey: 'manufacturing' }, data, patch));
    else result = null;
    if (typeof BomInventory !== 'undefined') {
      var bomData = Object.assign({}, data, {
        recommendations: data.recommendations ||
          (c && c.recommendations) ||
          (result && result.recommendations) ||
          []
      });
      BomInventory.createFromApproval(bomData);
    }
    return result;
  }

  function setManufacturingStage(idOrOrderRef, manufacturingStage) {
    var list = getAll();
    var idx = list.findIndex(function (c) {
      return c.id === idOrOrderRef || c.orderRef === idOrOrderRef;
    });
    if (idx === -1) return null;
    list[idx] = normalizeCase(Object.assign({}, list[idx], { manufacturingStage: manufacturingStage }));
    saveAll(list);
    return list[idx];
  }

  function onDelivered(idOrOrderRef, data) {
    var list = getAll();
    var idx = list.findIndex(function (c) {
      return c.id === idOrOrderRef || c.orderRef === idOrOrderRef;
    });
    if (idx === -1) return null;
    var current = list[idx];
    if (typeof BomInventory !== 'undefined') {
      var check = BomInventory.canDeliver(current.id);
      if (!check.ok) {
        return { error: check.reason, case: current };
      }
    }
    var patch = Object.assign({
      stageKey: 'delivered',
      deliveredAt: data && data.deliveredAt ? data.deliveredAt : formatDate(REF_DATE),
      paid: data && data.paid != null ? data.paid : undefined,
      totalCost: data && data.totalCost != null ? data.totalCost : undefined
    }, data || {});
    return setStage(current.id, 'delivered', patch);
  }

  function getBucket(key) {
    return getAll().filter(function (c) {
      if (key === 'waiting_return') return c.stageKey === 'waiting_return';
      if (key === 'in_progress') return c.stageKey === 'manufacturing';
      if (key === 'delivered') return c.stageKey === 'delivered';
      if (key === 'admin_approval') return c.stageKey === 'admin_approval';
      return true;
    });
  }

  function getStageLabel(key) {
    var s = STAGES.find(function (x) { return x.key === key; });
    return s ? s.label : key;
  }

  function getManufacturingLabel(key) {
    return MANUFACTURING_STAGES[key] || key || '—';
  }

  function getPatientTypeMeta(key) {
    return PATIENT_TYPES[key] || PATIENT_TYPES.civilian;
  }

  function getPatientTypeLabel(key) {
    return getPatientTypeMeta(key).label;
  }

  function getByPatientId(patientId) {
    return getAll().find(function (c) { return c.patientId === patientId; }) || null;
  }

  function getByPatientQr(qr) {
    return getAll().find(function (c) { return c.patientQr === qr; }) || null;
  }

  function isMilitary(c) {
    return derivePatientType(c) === 'military';
  }

  function generateWorkOrderNo(c) {
    if (c && c.workOrderNo) return c.workOrderNo;
    var ref = (c && (c.orderRef || c.id)) || '';
    var m = ref.match(/(\d{3,})/);
    return 'WO-2026-' + (m ? m[1].slice(-4) : String(Date.now()).slice(-4));
  }

  function turnaroundDays(c, toDate) {
    if (!c) return 0;
    return daysBetween(c.createdAt, toDate || REF_DATE);
  }

  // متوسط زمن تنفيذ الرحلة للحالات المغلقة (Turnaround) + عدّاد SLA
  function getSlaSummary(slaLimit) {
    var limit = slaLimit || 21;
    var all = getAll();
    var open = all.filter(function (c) { return c.stageKey !== 'delivered'; });
    var delivered = all.filter(function (c) { return c.stageKey === 'delivered'; });
    var avgTat = delivered.length
      ? Math.round(delivered.reduce(function (s, c) {
          return s + daysBetween(c.createdAt, parseDate(c.deliveredAt) || REF_DATE);
        }, 0) / delivered.length)
      : 0;
    var breached = open.filter(function (c) { return turnaroundDays(c) > limit; });
    return { avgTat: avgTat, breached: breached, slaLimit: limit, openCount: open.length };
  }

  function getTypeDistribution() {
    var all = getAll();
    var mil = all.filter(isMilitary).length;
    return { total: all.length, military: mil, civilian: all.length - mil };
  }

  var PHASES = [
    { start: 0, end: 1, icon: '📋', label: 'الملف', tip: 'استقبال → كشف' },
    { start: 2, end: 5, icon: '💰', label: 'التسعير', tip: 'توصيف → تكلفة → موافقة أدمن → عرض' },
    { start: 6, end: 6, icon: '⏳', label: 'انتظار', tip: 'انتظار رجوع العميل' },
    { start: 7, end: 7, icon: '🏭', label: 'تنفيذ', tip: 'جاري التصنيع' },
    { start: 8, end: 8, icon: '✅', label: 'تسليم', tip: 'تم التسليم' }
  ];

  function getPhaseState(phase, current) {
    if (current > phase.end) return 'done';
    if (current >= phase.start && current <= phase.end) return 'active';
    return 'pending';
  }

  function escAttr(str) {
    return String(str || '').replace(/"/g, '&quot;').replace(/</g, '&lt;');
  }

  function getStageIndex(caseItem) {
    if (!caseItem) return 0;
    if (caseItem.stageIndex != null) return caseItem.stageIndex;
    return stageMeta(caseItem.stageKey).stageIndex;
  }

  function getProgressPercent(caseItem) {
    var current = getStageIndex(caseItem);
    return Math.round(((current + 1) / STAGES.length) * 100);
  }

  function getPipelineTooltip(caseItem) {
    var current = getStageIndex(caseItem);
    return STAGES.map(function (s, i) {
      var mark = i < current ? '✓' : (i === current ? '●' : '○');
      return mark + ' ' + s.label;
    }).join('  ←  ');
  }

  function getStageBadgeClass(stageKey) {
    if (stageKey === 'delivered') return 'done';
    if (stageKey === 'manufacturing') return 'progress';
    if (stageKey === 'waiting_return' || stageKey === 'quote') return 'waiting';
    if (stageKey === 'admin_approval') return 'approval';
    return 'default';
  }

  function renderPipeline(caseItem) {
    if (!caseItem) return '';
    var current = getStageIndex(caseItem);
    var label = caseItem.stageLabel || getStageLabel(caseItem.stageKey);
    var stepNum = current + 1;
    var total = STAGES.length;
    var nextLabel = current < total - 1 ? STAGES[current + 1].label : null;
    var stepsHtml = '';

    PHASES.forEach(function (phase, i) {
      var state = getPhaseState(phase, current);
      var inner = state === 'done' ? '✓' : (state === 'active' ? phase.icon : String(i + 1));
      stepsHtml +=
        '<div class="wf-phase ' + state + '" title="' + escAttr(phase.tip) + '">' +
          '<div class="wf-phase-circle">' + inner + '</div>' +
          '<div class="wf-phase-label">' + phase.label + '</div>' +
        '</div>';
      if (i < PHASES.length - 1) {
        var lineState = current >= PHASES[i + 1].start ? 'done' : 'pending';
        stepsHtml += '<div class="wf-phase-line ' + lineState + '"></div>';
      }
    });

    return '<div class="wf-path" title="' + escAttr(getPipelineTooltip(caseItem)) + '">' +
      '<div class="wf-path-head">' +
        '<div class="wf-path-now">' +
          '<span class="wf-path-indicator ' + getStageBadgeClass(caseItem.stageKey) + '"></span>' +
          '<div class="wf-path-text">' +
            '<strong>' + label + '</strong>' +
            '<span>الخطوة ' + stepNum + ' من ' + total + '</span>' +
          '</div>' +
        '</div>' +
      '</div>' +
      '<div class="wf-path-steps">' + stepsHtml + '</div>' +
      (nextLabel
        ? '<div class="wf-path-next">التالي: ' + nextLabel + ' ←</div>'
        : '<div class="wf-path-next done">✓ اكتمل المسار</div>') +
      '</div>';
  }

  function getPricingRef(caseItem) {
    if (!caseItem) return null;
    return caseItem.pricingQueueId || caseItem.orderRef || null;
  }

  function renderQuoteRefCell(caseItem) {
    if (!caseItem) return '—';
    var quoteId = caseItem.quoteId || '—';
    var pricingRef = getPricingRef(caseItem);
    var html = '<div class="quote-ref-cell">' +
      '<strong class="quote-ref-main">' + quoteId + '</strong>' +
      '<span class="quote-ref-label">رقم عرض السعر</span>';
    if (pricingRef && pricingRef !== quoteId) {
      html += '<span class="quote-ref-sub">مرجع التسعير: ' + pricingRef + '</span>';
    }
    html += '</div>';
    return html;
  }

  function formatMoney(n) {
    return Number(n || 0).toLocaleString('ar-EG') + ' ج.م';
  }

  return {
    STORAGE_KEY: STORAGE_KEY,
    SEED_VERSION: SEED_VERSION,
    STAGES: STAGES,
    REF_DATE: REF_DATE,
    ensureSeeded: ensureSeeded,
    getAll: getAll,
    saveAll: saveAll,
    getById: getById,
    getByOrderRef: getByOrderRef,
    getByPricingQueueId: getByPricingQueueId,
    getByQuoteId: getByQuoteId,
    upsert: upsert,
    setStage: setStage,
    setManufacturingStage: setManufacturingStage,
    onCostCalculated: onCostCalculated,
    onAdminApproved: onAdminApproved,
    onQuoteIssued: onQuoteIssued,
    onApprovalConfirmed: onApprovalConfirmed,
    onDelivered: onDelivered,
    getBucket: getBucket,
    getStageLabel: getStageLabel,
    getManufacturingLabel: getManufacturingLabel,
    MANUFACTURING_STAGES: MANUFACTURING_STAGES,
    PATIENT_TYPES: PATIENT_TYPES,
    getPatientTypeMeta: getPatientTypeMeta,
    getPatientTypeLabel: getPatientTypeLabel,
    getByPatientId: getByPatientId,
    getByPatientQr: getByPatientQr,
    isMilitary: isMilitary,
    generateWorkOrderNo: generateWorkOrderNo,
    turnaroundDays: turnaroundDays,
    getSlaSummary: getSlaSummary,
    getTypeDistribution: getTypeDistribution,
    renderPipeline: renderPipeline,
    daysBetween: daysBetween,
    renderQuoteRefCell: renderQuoteRefCell,
    getPricingRef: getPricingRef,
    formatMoney: formatMoney,
    parseDate: parseDate,
    formatDate: formatDate
  };
})();
