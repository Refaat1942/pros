/**
 * BomInventory — قائمة مواد (BOM) بمراحل: خام → تحت التشغيل → تام
 */
var BomInventory = (function () {
  var STORAGE_KEY = 'clinic_bom_inventory';
  var SEED_VERSION = 5;
  var REF_DATE = new Date(2026, 5, 8);

  var STAGES = {
    raw: { key: 'raw', label: 'خام', desc: 'بانتظار صرف من المخزن' },
    wip: { key: 'wip', label: 'تحت التشغيل', desc: 'صُرفت للورشة' },
    finished: { key: 'finished', label: 'تام', desc: 'منتج مكتمل — جاهز للتسليم للمريض' }
  };

  var DEFAULT = [
    {
      id: 'BOM-001',
      caseId: 'CASE-2026-006',
      orderRef: 'ORD-2026-0798',
      patient: 'هدى محمود سعيد',
      quoteId: 'QT-2026-0798',
      stage: 'raw',
      items: [
        { code: 'ITM-002', name: 'ركبة Polycentric', qty: 1, unitCost: 72000 },
        { code: 'ITM-006', name: 'Pin Lock', qty: 1, unitCost: 5800 }
      ],
      createdAt: '08/05/2026',
      releasedAt: null,
      finishedAt: null
    },
    {
      id: 'BOM-005',
      caseId: 'CASE-2026-009',
      orderRef: 'ORD-2026-0785',
      patient: 'ليلى حسام الدين',
      quoteId: 'QT-2026-0785',
      stage: 'raw',
      items: [
        { code: 'ITM-008', name: 'بطانة Gel', qty: 1, unitCost: 9500 },
        { code: 'ITM-009', name: 'جوارب تجويف', qty: 2, unitCost: 450 }
      ],
      createdAt: '05/06/2026',
      releasedAt: null,
      finishedAt: null
    },
    {
      id: 'BOM-002',
      caseId: 'CASE-2026-005',
      orderRef: 'ORD-2026-0810',
      patient: 'سارة أحمد فؤاد',
      quoteId: 'QT-2026-0810',
      stage: 'wip',
      items: [
        { code: 'ITM-001', name: 'ركبة هيدروليكية', qty: 1, unitCost: 95000, issuedQty: 1, returnedQty: 0 },
        { code: 'ITM-003', name: 'قدم Carbon Spring', qty: 1, unitCost: 55000, issuedQty: 1, returnedQty: 0 },
        { code: 'ITM-004', name: 'بطانة Silicone', qty: 1, unitCost: 12000, issuedQty: 1, returnedQty: 0 }
      ],
      createdAt: '22/05/2026',
      releasedAt: '22/05/2026 11:20',
      finishedAt: null
    },
    {
      id: 'BOM-006',
      caseId: 'CASE-2026-010',
      orderRef: 'ORD-2026-0772',
      patient: 'عبدالله سامي رشاد',
      quoteId: 'QT-2026-0772',
      stage: 'wip',
      items: [
        { code: 'ITM-010', name: 'مفصل كوع', qty: 1, unitCost: 38000, issuedQty: 1, returnedQty: 0 },
        { code: 'ITM-005', name: 'محول Pyramidal', qty: 1, unitCost: 15000, issuedQty: 1, returnedQty: 0 }
      ],
      createdAt: '01/06/2026',
      releasedAt: '03/06/2026 09:15',
      finishedAt: null
    },
    {
      id: 'BOM-003',
      caseId: 'CASE-2026-007',
      orderRef: 'ORD-2026-0755',
      patient: 'كريم محمد علي',
      quoteId: 'QT-2026-0755',
      stage: 'finished',
      items: [
        { code: 'ITM-002', name: 'ركبة Polycentric', qty: 1, unitCost: 72000 },
        { code: 'ITM-003', name: 'قدم Carbon Spring', qty: 1, unitCost: 55000 }
      ],
      createdAt: '18/04/2026',
      releasedAt: '18/04/2026 14:00',
      finishedAt: '02/05/2026'
    },
    {
      id: 'BOM-004',
      caseId: 'CASE-2026-008',
      orderRef: 'ORD-2026-0742',
      patient: 'أحمد فاروق نبيل',
      quoteId: 'QT-2026-0742',
      stage: 'finished',
      items: [
        { code: 'ITM-001', name: 'ركبة هيدروليكية', qty: 1, unitCost: 95000 },
        { code: 'ITM-005', name: 'محول Pyramidal', qty: 1, unitCost: 15000 },
        { code: 'ITM-004', name: 'بطانة Silicone', qty: 1, unitCost: 12000 }
      ],
      createdAt: '28/03/2026',
      releasedAt: '28/03/2026 10:30',
      finishedAt: '15/04/2026'
    },
    {
      id: 'BOM-007',
      caseId: 'CASE-2026-011',
      orderRef: 'ORD-2026-0855',
      patient: 'منى إبراهيم حسن',
      quoteId: 'QT-2026-0855',
      stage: 'finished',
      items: [
        { code: 'ITM-002', name: 'ركبة Polycentric', qty: 1, unitCost: 72000 },
        { code: 'ITM-004', name: 'بطانة Silicone', qty: 1, unitCost: 12000 },
        { code: 'ITM-006', name: 'Pin Lock', qty: 1, unitCost: 5800 }
      ],
      createdAt: '20/05/2026',
      releasedAt: '25/05/2026 10:00',
      finishedAt: '07/06/2026'
    },
    {
      id: 'BOM-008',
      caseId: 'CASE-2026-004',
      orderRef: 'ORD-2026-0821',
      patient: 'يوسف عمر محسن',
      quoteId: 'QT-2026-0821',
      stage: 'wip',
      items: [
        { code: 'ITM-001', name: 'ركبة هيدروليكية', qty: 1, unitCost: 95000 },
        { code: 'ITM-005', name: 'محول Pyramidal', qty: 1, unitCost: 15000 }
      ],
      createdAt: '21/05/2026',
      releasedAt: '21/05/2026 09:00',
      finishedAt: null
    }
  ];

  function formatDate(d) {
    var dt = d || REF_DATE;
    return String(dt.getDate()).padStart(2, '0') + '/' +
      String(dt.getMonth() + 1).padStart(2, '0') + '/' + dt.getFullYear();
  }

  function formatStamp(d) {
    var dt = d || REF_DATE;
    return formatDate(dt) + ' ' +
      String(dt.getHours()).padStart(2, '0') + ':' +
      String(dt.getMinutes()).padStart(2, '0');
  }

  function formatMoney(n) {
    return Number(n || 0).toLocaleString('ar-EG') + ' ج.م';
  }

  function highestUnitCost(stockItem) {
    if (!stockItem || !stockItem.prices || !stockItem.prices.length) return 0;
    if (typeof PricingQueue !== 'undefined' && PricingQueue.highestUnitPrice) {
      return PricingQueue.highestUnitPrice(stockItem);
    }
    return Math.max.apply(null, stockItem.prices.map(function (p) { return p.amount || 0; }));
  }

  function normalizeBom(bom) {
    return Object.assign({}, bom, {
      items: (bom.items || []).map(function (it) {
        return {
          code: it.code,
          name: it.name || it.code,
          qty: it.qty || 1,
          unitCost: it.unitCost || 0
        };
      }),
      stage: STAGES[bom.stage] ? bom.stage : 'raw'
    });
  }

  function getAll() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      if (raw) {
        var parsed = JSON.parse(raw);
        if (Array.isArray(parsed) && parsed.length) {
          return parsed.map(normalizeBom);
        }
      }
    } catch (e) { /* ignore */ }
    return DEFAULT.map(function (b) { return normalizeBom(Object.assign({}, b)); });
  }

  function saveAll(list) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(list.map(normalizeBom)));
  }

  function cloneDefaultList() {
    return DEFAULT.map(function (b) {
      return normalizeBom(Object.assign({}, b, {
        items: (b.items || []).map(function (it) { return Object.assign({}, it); })
      }));
    });
  }

  function ensureSeeded() {
    var savedVersion = localStorage.getItem(STORAGE_KEY + '_version');
    if (!localStorage.getItem(STORAGE_KEY) || savedVersion !== String(SEED_VERSION)) {
      saveAll(cloneDefaultList());
      localStorage.setItem(STORAGE_KEY + '_version', String(SEED_VERSION));
    }
  }

  /** استعادة بيانات العرض التجريبي — تُستدعى عند refresh لوحة المخزن */
  function resetToSeed() {
    saveAll(cloneDefaultList());
    localStorage.setItem(STORAGE_KEY + '_version', String(SEED_VERSION));
  }

  function nextId() {
    var max = getAll().reduce(function (m, b) {
      var n = parseInt(String(b.id || '').replace(/\D/g, ''), 10);
      return isNaN(n) ? m : Math.max(m, n);
    }, 0);
    return 'BOM-' + String(max + 1).padStart(3, '0');
  }

  function getById(id) {
    return getAll().find(function (b) { return b.id === id; }) || null;
  }

  function getByCaseId(caseId) {
    return getAll().find(function (b) { return b.caseId === caseId; }) || null;
  }

  function getByOrderRef(orderRef) {
    return getAll().find(function (b) { return b.orderRef === orderRef; }) || null;
  }

  function getByStage(stage) {
    return getAll().filter(function (b) { return b.stage === stage; });
  }

  function bomTotalValue(bom) {
    return (bom.items || []).reduce(function (s, it) {
      return s + (it.unitCost || 0) * (it.qty || 1);
    }, 0);
  }

  function syncCaseManufacturingStage(caseId, mfgStage) {
    if (typeof CasesWorkflow !== 'undefined' && CasesWorkflow.setManufacturingStage && caseId) {
      CasesWorkflow.setManufacturingStage(caseId, mfgStage);
    }
  }

  function parseQuoteItems(items) {
    return (items || []).map(function (it) {
      var name = String(it.name || '');
      var codeMatch = name.match(/ITM-\d+/);
      var cleanName = name.split('—')[0].split('-')[0].trim();
      return {
        name: cleanName || name,
        code: it.code || (codeMatch ? codeMatch[0] : null),
        qty: it.qty || 1
      };
    }).filter(function (it) { return it.code || it.name; });
  }

  function resolveRecommendations(orderRef, caseItem, dataRecs) {
    if (dataRecs && dataRecs.length) return dataRecs.slice();
    if (caseItem && caseItem.recommendations && caseItem.recommendations.length) {
      return caseItem.recommendations.slice();
    }
    if (typeof PricingQueue !== 'undefined') {
      var all = PricingQueue.getAll();
      var pq = all.find(function (p) {
        return p.orderRef === orderRef ||
          (caseItem && caseItem.pricingQueueId && p.id === caseItem.pricingQueueId);
      });
      if (!pq && caseItem && caseItem.quoteId) {
        pq = all.find(function (p) {
          return p.id.replace('QT-PENDING', 'QT-2026') === caseItem.quoteId;
        });
      }
      if (!pq && caseItem && caseItem.patient) {
        var first = caseItem.patient.split(' ')[0];
        pq = all.find(function (p) {
          return p.patient && p.patient.indexOf(first) !== -1;
        });
      }
      if (pq && pq.recommendations && pq.recommendations.length) {
        return pq.recommendations.slice();
      }
    }
    try {
      var techRaw = localStorage.getItem('clinic_tech_order_specs');
      if (techRaw) {
        var specs = JSON.parse(techRaw);
        if (Array.isArray(specs)) {
          var spec = specs.find(function (s) { return s.orderRef === orderRef; });
          if (spec && spec.recommendations && spec.recommendations.length) {
            return spec.recommendations.slice();
          }
        }
      }
    } catch (e) { /* ignore */ }
    return [];
  }

  function enrichItems(recommendations) {
    var catalog = typeof StockCatalog !== 'undefined' ? StockCatalog.getAll() : [];
    return (recommendations || []).map(function (rec) {
      var stock = catalog.find(function (s) { return s.code === rec.code; });
      return {
        code: rec.code,
        name: rec.name || (stock && stock.name) || rec.code,
        qty: rec.qty || 1,
        unitCost: stock ? highestUnitCost(stock) : 0
      };
    });
  }

  function getSummary() {
    var list = getAll();
    var summary = {};
    Object.keys(STAGES).forEach(function (key) {
      var rows = list.filter(function (b) { return b.stage === key; });
      var itemCount = rows.reduce(function (s, b) { return s + (b.items || []).length; }, 0);
      var totalValue = rows.reduce(function (s, b) { return s + bomTotalValue(b); }, 0);
      summary[key] = {
        key: key,
        label: STAGES[key].label,
        desc: STAGES[key].desc,
        count: rows.length,
        itemCount: itemCount,
        totalValue: totalValue
      };
    });
    return summary;
  }

  function createFromApproval(data) {
    if (!data) return null;
    var caseItem = null;
    if (typeof CasesWorkflow !== 'undefined') {
      caseItem = (data.quoteId && CasesWorkflow.getByQuoteId(data.quoteId)) ||
        (data.orderRef && CasesWorkflow.getByOrderRef(data.orderRef)) ||
        (data.patient && CasesWorkflow.getAll().find(function (c) {
          return c.patient === data.patient && c.stageKey === 'manufacturing';
        }));
    }
    var orderRef = data.orderRef || (caseItem && caseItem.orderRef);
    if (!orderRef && !caseItem) return null;

    var existing = getAll().find(function (b) {
      return (caseItem && b.caseId === caseItem.id) ||
        (orderRef && b.orderRef === orderRef);
    });
    if (existing) return existing;

    var recs = resolveRecommendations(orderRef, caseItem, data.recommendations);
    if ((!recs || !recs.length) && data.quoteItems) {
      recs = parseQuoteItems(data.quoteItems);
    }
    var bom = normalizeBom({
      id: nextId(),
      caseId: caseItem ? caseItem.id : null,
      orderRef: orderRef,
      patient: data.patient || (caseItem && caseItem.patient) || '—',
      quoteId: data.quoteId || (caseItem && caseItem.quoteId) || null,
      stage: 'raw',
      items: enrichItems(recs),
      createdAt: data.approvalDate || formatDate(),
      releasedAt: null,
      finishedAt: null
    });

    var list = getAll();
    list.unshift(bom);
    saveAll(list);
    syncCaseManufacturingStage(bom.caseId, 'issue');
    return bom;
  }

  function resolveScanCode(scanned) {
    if (typeof StockCatalog !== 'undefined' && StockCatalog.resolveBarcode) {
      return StockCatalog.resolveBarcode(scanned);
    }
    var s = String(scanned || '').trim().toUpperCase();
    if (s.indexOf('BC-') === 0) {
      var n = parseInt(s.replace(/^BC-/, ''), 10);
      if (!isNaN(n)) return 'ITM-' + String(n).padStart(3, '0');
    }
    return s;
  }

  // التحقق من باركود الأصناف المصروفة قبل الصرف الفعلي.
  // يُرجع نتيجة بإنذار عند عدم تطابق أي كود مع بنود الـ BOM.
  function verifyBarcodes(bom, scannedCodes) {
    var required = (bom.items || []).map(function (it) { return it.code; });
    var scanned = (scannedCodes || []).map(function (c) { return resolveScanCode(c); });
    var mismatched = scanned.filter(function (c) { return required.indexOf(c) === -1; });
    var missing = required.filter(function (c) { return scanned.indexOf(c) === -1; });
    var ok = mismatched.length === 0 && missing.length === 0;
    return {
      ok: ok,
      mismatched: mismatched,
      missing: missing,
      alarm: mismatched.length > 0,
      reason: mismatched.length
        ? 'باركود غير مطابق لأمر التشغيل: ' + mismatched.join('، ')
        : (missing.length ? 'لم يُمسح بعد: ' + missing.join('، ') : '')
    };
  }

  // صرف بمسح الباركود: يتطلب مطابقة كل أكواد البنود، وإلا يُغلق الصرف.
  function releaseToWipByBarcode(bomId, scannedCodes) {
    var bom = getById(bomId);
    if (!bom) return { ok: false, error: 'not_found' };
    var verify = verifyBarcodes(bom, scannedCodes);
    if (!verify.ok) {
      return { ok: false, error: verify.reason, alarm: verify.alarm, verify: verify };
    }
    return releaseToWip(bomId);
  }

  function canReleaseToWip(bom) {
    if (!bom || bom.stage !== 'raw') return { ok: false, reason: 'ليست في مرحلة خام' };
    if (!bom.items || !bom.items.length) {
      return { ok: false, reason: 'لا توجد بنود — راجع التوصيف الفني أو طابور التسعير' };
    }
    if (typeof StockCatalog === 'undefined') return { ok: true };
    var catalog = StockCatalog.getAll();
    var issues = [];
    (bom.items || []).forEach(function (it) {
      var stock = catalog.find(function (s) { return s.code === it.code; });
      var need = it.qty || 1;
      var avail = stock ? (stock.qty || 0) : 0;
      if (avail < need) {
        issues.push((it.name || it.code) + ' (متاح: ' + avail + ')');
      }
    });
    if (issues.length) return { ok: false, reason: 'كمية غير كافية: ' + issues.join('، ') };
    return { ok: true };
  }

  function releaseToWip(bomId) {
    var list = getAll();
    var idx = list.findIndex(function (b) { return b.id === bomId; });
    if (idx === -1) return { ok: false, error: 'not_found' };
    var bom = list[idx];
    var check = canReleaseToWip(bom);
    if (!check.ok) return { ok: false, error: check.reason };

    if (typeof StockCatalog !== 'undefined' && StockCatalog.issueQty) {
      var updatedItems = (bom.items || []).map(function (it) {
        return Object.assign({}, it, { issuedQty: it.qty || 1, returnedQty: it.returnedQty || 0 });
      });
      for (var i = 0; i < updatedItems.length; i++) {
        var it = updatedItems[i];
        var result = StockCatalog.issueQty(it.code, it.qty || 1);
        if (!result.ok) return { ok: false, error: result.error || 'issue_failed' };
      }
      bom = Object.assign({}, bom, { items: updatedItems });
    }

    list[idx] = normalizeBom(Object.assign({}, bom, {
      stage: 'wip',
      releasedAt: formatStamp()
    }));
    saveAll(list);
    syncCaseManufacturingStage(list[idx].caseId, 'generation');
    return { ok: true, bom: list[idx] };
  }

  function completeToFinished(bomId) {
    var list = getAll();
    var idx = list.findIndex(function (b) { return b.id === bomId; });
    if (idx === -1) return { ok: false, error: 'not_found' };
    var bom = list[idx];
    if (bom.stage !== 'wip') return { ok: false, error: 'not_wip' };

    list[idx] = normalizeBom(Object.assign({}, bom, {
      stage: 'finished',
      finishedAt: formatDate()
    }));
    saveAll(list);
    syncCaseManufacturingStage(list[idx].caseId, 'finishing');
    return { ok: true, bom: list[idx] };
  }

  function canDeliver(caseId) {
    if (typeof CasesWorkflow === 'undefined') return { ok: true };
    var c = CasesWorkflow.getById(caseId);
    if (!c || c.stageKey !== 'manufacturing') {
      return { ok: false, reason: 'الحالة ليست في مرحلة «جاري التصنيع»' };
    }
    var bom = getByCaseId(caseId);
    if (!bom) {
      return { ok: false, reason: 'لا توجد BOM — يجب إنشاؤها عند تأكيد الموافقة' };
    }
    if (bom.stage !== 'finished') {
      return { ok: false, reason: 'BOM في «' + getStageLabel(bom.stage) + '» — يجب إغلاقها «تام» من المخزن أولاً' };
    }
    return { ok: true, bom: bom };
  }

  function getReadyForDelivery() {
    if (typeof CasesWorkflow === 'undefined') return [];
    return CasesWorkflow.getBucket('in_progress').filter(function (c) {
      return canDeliver(c.id).ok;
    });
  }

  function completeByCaseId(caseId) {
    var bom = getByCaseId(caseId);
    if (!bom || bom.stage === 'finished') return bom;
    if (bom.stage !== 'wip') return null;
    var done = completeToFinished(bom.id);
    return done.ok ? done.bom : null;
  }

  function getStageLabel(key) {
    return STAGES[key] ? STAGES[key].label : key;
  }

  function getStageBadgeClass(key) {
    if (key === 'finished') return 'done';
    if (key === 'wip') return 'progress';
    return 'waiting';
  }

  function getReturnableQty(bom, itemCode) {
    if (!bom || bom.stage !== 'wip') return 0;
    var line = (bom.items || []).find(function (it) { return it.code === itemCode; });
    if (!line) return 0;
    var issued = line.issuedQty != null ? line.issuedQty : (line.qty || 1);
    var returned = line.returnedQty || 0;
    return Math.max(0, issued - returned);
  }

  function recordItemReturn(bomId, itemCode, qty) {
    var list = getAll();
    var idx = list.findIndex(function (b) { return b.id === bomId; });
    if (idx === -1) return { ok: false, error: 'not_found' };
    var bom = list[idx];
    if (bom.stage !== 'wip') {
      return { ok: false, error: 'returns_wip_only', reason: 'الارتجاع متاح فقط أثناء «تحت التشغيل» — BOM تام يتطلب موافقة مشرف' };
    }
    var add = Math.max(1, parseInt(qty, 10) || 1);
    var lineIdx = (bom.items || []).findIndex(function (it) { return it.code === itemCode; });
    if (lineIdx === -1) return { ok: false, error: 'item_not_in_bom' };
    var line = bom.items[lineIdx];
    var maxReturn = getReturnableQty(bom, itemCode);
    if (add > maxReturn) {
      return { ok: false, error: 'exceeds_returnable', max: maxReturn, code: itemCode };
    }
    var items = bom.items.slice();
    items[lineIdx] = Object.assign({}, line, { returnedQty: (line.returnedQty || 0) + add });
    list[idx] = normalizeBom(Object.assign({}, bom, { items: items }));
    saveAll(list);
    return { ok: true, bom: list[idx], returnedQty: add };
  }

  function renderItemsList(items, showCost) {
    if (!items || !items.length) return '<span class="text-muted">—</span>';
    return items.map(function (it) {
      var line = (it.name || it.code) + ' ×' + (it.qty || 1);
      if (showCost) line += ' (' + formatMoney((it.unitCost || 0) * (it.qty || 1)) + ')';
      return line;
    }).join('<br>');
  }

  return {
    STORAGE_KEY: STORAGE_KEY,
    SEED_VERSION: SEED_VERSION,
    STAGES: STAGES,
    ensureSeeded: ensureSeeded,
    resetToSeed: resetToSeed,
    getAll: getAll,
    saveAll: saveAll,
    getById: getById,
    getByCaseId: getByCaseId,
    getByOrderRef: getByOrderRef,
    getByStage: getByStage,
    getSummary: getSummary,
    bomTotalValue: bomTotalValue,
    createFromApproval: createFromApproval,
    canReleaseToWip: canReleaseToWip,
    releaseToWip: releaseToWip,
    releaseToWipByBarcode: releaseToWipByBarcode,
    verifyBarcodes: verifyBarcodes,
    resolveScanCode: resolveScanCode,
    getReturnableQty: getReturnableQty,
    recordItemReturn: recordItemReturn,
    completeToFinished: completeToFinished,
    completeByCaseId: completeByCaseId,
    canDeliver: canDeliver,
    getReadyForDelivery: getReadyForDelivery,
    parseQuoteItems: parseQuoteItems,
    getStageLabel: getStageLabel,
    getStageBadgeClass: getStageBadgeClass,
    renderItemsList: renderItemsList,
    formatMoney: formatMoney
  };
})();
