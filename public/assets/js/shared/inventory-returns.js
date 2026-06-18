/**
 * InventoryReturns — إذن ارتجاع داخلي (ورشة → مخزن) مرتبط بـ BOM وأمر التشغيل
 */
var InventoryReturns = (function () {
  var STORAGE_KEY = 'clinic_inventory_returns';
  var SEED_VERSION = 1;
  var REF_DATE = new Date(2026, 5, 8);

  var DEFAULT = [
    {
      id: 'RTN-001',
      bomId: 'BOM-006',
      caseId: 'CASE-2026-010',
      orderRef: 'ORD-2026-0772',
      patient: 'عبدالله سامي رشاد',
      workOrderNo: 'WO-2026-0288',
      status: 'authorized',
      lines: [
        {
          code: 'ITM-005',
          name: 'محول Pyramidal',
          qtyRequested: 1,
          qtyReturned: 0,
          reason: 'فائض عن الحاجة في الورشة'
        }
      ],
      createdAt: '06/06/2026 10:00',
      authorizedAt: '06/06/2026 10:15',
      completedAt: null,
      createdBy: 'محمد فتحي',
      auditTrail: []
    }
  ];

  function formatStamp() {
    var d = REF_DATE;
    return String(d.getDate()).padStart(2, '0') + '/' +
      String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear() + ' ' +
      String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
  }

  function normalizeNote(note) {
    var n = Object.assign({}, note);
    n.lines = Array.isArray(n.lines) ? n.lines : [];
    n.auditTrail = Array.isArray(n.auditTrail) ? n.auditTrail : [];
    return n;
  }

  function load() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      if (raw) {
        var parsed = JSON.parse(raw);
        if (Array.isArray(parsed)) return parsed.map(normalizeNote);
      }
    } catch (e) { /* ignore */ }
    return null;
  }

  function saveAll(list) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(list.map(normalizeNote)));
  }

  function ensureSeeded() {
    var verKey = STORAGE_KEY + '_seed_v';
    var ver = localStorage.getItem(verKey);
    if (ver !== String(SEED_VERSION) || !localStorage.getItem(STORAGE_KEY)) {
      saveAll(DEFAULT.map(function (n) { return Object.assign({}, n); }));
      localStorage.setItem(verKey, String(SEED_VERSION));
    }
  }

  function getAll() {
    ensureSeeded();
    return load() || [];
  }

  function getById(id) {
    return getAll().find(function (n) { return n.id === id; }) || null;
  }

  function nextId() {
    var list = getAll();
    var max = list.reduce(function (m, n) {
      var num = parseInt(String(n.id || '').replace(/\D/g, ''), 10);
      return isNaN(num) ? m : Math.max(m, num);
    }, 0);
    return 'RTN-' + String(max + 1).padStart(3, '0');
  }

  function pushAudit(note, entry) {
    note.auditTrail = (note.auditTrail || []).concat([Object.assign({
      at: formatStamp(),
      user: 'محمد فتحي'
    }, entry)]);
  }

  function getEligibleBoms() {
    if (typeof BomInventory === 'undefined') return [];
    return BomInventory.getAll().filter(function (b) { return b.stage === 'wip'; });
  }

  function createReturnNote(bomId, lines, meta) {
    if (typeof BomInventory === 'undefined') return { ok: false, error: 'bom_unavailable' };
    var bom = BomInventory.getById(bomId);
    if (!bom) return { ok: false, error: 'not_found' };
    if (bom.stage !== 'wip') {
      return { ok: false, error: 'returns_wip_only', reason: 'الارتجاع متاح فقط لقوائم «تحت التشغيل»' };
    }
    var normalizedLines = (lines || []).map(function (ln) {
      var bomLine = (bom.items || []).find(function (it) { return it.code === ln.code; });
      if (!bomLine) return null;
      var req = Math.max(1, parseInt(ln.qtyRequested, 10) || 1);
      var max = BomInventory.getReturnableQty(bom, ln.code);
      if (req > max) req = max;
      if (req <= 0) return null;
      return {
        code: ln.code,
        name: bomLine.name || ln.name || ln.code,
        qtyRequested: req,
        qtyReturned: 0,
        reason: ln.reason || (meta && meta.reason) || '—'
      };
    }).filter(Boolean);

    if (!normalizedLines.length) {
      return { ok: false, error: 'no_lines', reason: 'لا توجد بنود قابلة للارتجاع' };
    }

    var caseItem = null;
    if (typeof CasesWorkflow !== 'undefined' && bom.caseId) {
      caseItem = CasesWorkflow.getById(bom.caseId);
    }

    var note = normalizeNote({
      id: nextId(),
      bomId: bom.id,
      caseId: bom.caseId,
      orderRef: bom.orderRef,
      patient: bom.patient,
      workOrderNo: caseItem && caseItem.workOrderNo ? caseItem.workOrderNo : null,
      status: 'authorized',
      lines: normalizedLines,
      createdAt: formatStamp(),
      authorizedAt: formatStamp(),
      completedAt: null,
      createdBy: (meta && meta.createdBy) || 'محمد فتحي',
      auditTrail: []
    });
    pushAudit(note, {
      action: 'authorize',
      desc: 'إصدار إذن ارتجاع — ' + normalizedLines.length + ' بند'
    });

    var list = getAll();
    list.unshift(note);
    saveAll(list);
    return { ok: true, note: note };
  }

  function verifyReturnBarcode(note, scanned) {
    var code = typeof StockCatalog !== 'undefined' && StockCatalog.resolveBarcode
      ? StockCatalog.resolveBarcode(scanned)
      : (typeof BomInventory !== 'undefined' ? BomInventory.resolveScanCode(scanned) : scanned);
    var line = (note.lines || []).find(function (ln) {
      return ln.code === code && (ln.qtyReturned || 0) < (ln.qtyRequested || 0);
    });
    if (!line) {
      var inNote = (note.lines || []).some(function (ln) { return ln.code === code; });
      return {
        ok: false,
        alarm: true,
        reason: inNote
          ? 'تم استكمال ارتجاع هذا البند'
          : 'باركود غير مطابق لإذن الارتجاع: ' + String(scanned).trim()
      };
    }
    return { ok: true, code: code, line: line };
  }

  function processReturnScan(returnId, scanned, qty) {
    var list = getAll();
    var idx = list.findIndex(function (n) { return n.id === returnId; });
    if (idx === -1) return { ok: false, error: 'not_found' };
    var note = list[idx];
    if (note.status === 'completed') return { ok: false, error: 'already_completed' };
    if (note.status !== 'authorized' && note.status !== 'partial') {
      return { ok: false, error: 'not_authorized' };
    }

    var verify = verifyReturnBarcode(note, scanned);
    if (!verify.ok) return { ok: false, error: verify.reason, alarm: verify.alarm };

    var lineIdx = note.lines.findIndex(function (ln) { return ln.code === verify.code; });
    var line = note.lines[lineIdx];
    var remaining = (line.qtyRequested || 0) - (line.qtyReturned || 0);
    var add = Math.min(remaining, Math.max(1, parseInt(qty, 10) || 1));

    if (typeof BomInventory !== 'undefined') {
      var bomRec = BomInventory.recordItemReturn(note.bomId, verify.code, add);
      if (!bomRec.ok) return { ok: false, error: bomRec.reason || bomRec.error, alarm: bomRec.error === 'item_not_in_bom' };
    }

    if (typeof StockCatalog !== 'undefined' && StockCatalog.returnQty) {
      var stockRes = StockCatalog.returnQty(verify.code, add, { returnId: note.id });
      if (!stockRes.ok) return { ok: false, error: 'stock_return_failed' };
    }

    var lines = note.lines.slice();
    lines[lineIdx] = Object.assign({}, line, { qtyReturned: (line.qtyReturned || 0) + add });
    note = Object.assign({}, note, { lines: lines });
    pushAudit(note, {
      action: 'return_scan',
      desc: 'مسح باركود ارتجاع ' + verify.code + ' — كمية ' + add,
      barcode: String(scanned).trim()
    });

    var allDone = lines.every(function (ln) { return (ln.qtyReturned || 0) >= (ln.qtyRequested || 0); });
    note.status = allDone ? 'completed' : 'partial';
    if (allDone) note.completedAt = formatStamp();

    list[idx] = note;
    saveAll(list);
    return { ok: true, note: note, qtyReturned: add, completed: allDone };
  }

  function getSummary() {
    var all = getAll();
    return {
      total: all.length,
      authorized: all.filter(function (n) { return n.status === 'authorized'; }).length,
      partial: all.filter(function (n) { return n.status === 'partial'; }).length,
      completed: all.filter(function (n) { return n.status === 'completed'; }).length
    };
  }

  function statusLabel(status) {
    if (status === 'completed') return 'مكتمل';
    if (status === 'partial') return 'جزئي';
    if (status === 'authorized') return 'مصرّح';
    return status || '—';
  }

  return {
    STORAGE_KEY: STORAGE_KEY,
    SEED_VERSION: SEED_VERSION,
    ensureSeeded: ensureSeeded,
    getAll: getAll,
    getById: getById,
    getEligibleBoms: getEligibleBoms,
    createReturnNote: createReturnNote,
    verifyReturnBarcode: verifyReturnBarcode,
    processReturnScan: processReturnScan,
    getSummary: getSummary,
    statusLabel: statusLabel
  };
})();
