/**
 * CreditNotes — إشعارات دائن (مرتجعات مالية) لمسار المدني بعد التسليم
 */
var CreditNotes = (function () {
  var STORAGE_KEY = 'clinic_credit_notes';
  var DEBTS_KEY = 'clinic_contract_debts';
  var SEED_VERSION = 1;
  var REF_DATE = new Date(2026, 5, 8);

  var DEFAULT_DEBTS = [
    { company: 'شركة التأمين الوطني', due: 485000, collected: 485000, status: 'paid' },
    { company: 'هيئة التأمين الصحي', due: 270000, collected: 450000, status: 'partial' },
    { company: 'التأمين الصحي', due: 270000, collected: 450000, status: 'partial' },
    { company: 'مجلس الدفاع المدني', due: 156000, collected: 156000, status: 'paid' },
    { company: 'شركة مصر للتأمين', due: 890000, collected: 890000, status: 'paid' },
    { company: 'صندوق رعاية ذوي الإعاقة', due: 340000, collected: 340000, status: 'paid' },
    { company: 'وزارة الداخلية — التأمين', due: 275000, collected: 275000, status: 'paid' }
  ];

  var DEFAULT_NOTES = [
    {
      id: 'CN-001',
      caseId: 'CASE-2026-007',
      orderRef: 'ORD-2026-0755',
      patient: 'كريم محمد علي',
      company: 'التأمين الصحي',
      type: 'partial',
      amount: 15000,
      originalTotal: 72000,
      reason: 'رفض جزئي — بطانة غير مطابقة للمواصفات',
      status: 'pending',
      createdAt: '08/06/2026 09:00',
      approvedAt: null,
      approvedBy: null
    }
  ];

  function formatStamp() {
    var d = REF_DATE;
    return String(d.getDate()).padStart(2, '0') + '/' +
      String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear() + ' ' +
      String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
  }

  function formatMoney(n) {
    return (n || 0).toLocaleString('ar-EG');
  }

  function normalizeNote(n) {
    return Object.assign({}, n);
  }

  function loadNotes() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      if (raw) {
        var parsed = JSON.parse(raw);
        if (Array.isArray(parsed)) return parsed.map(normalizeNote);
      }
    } catch (e) { /* ignore */ }
    return null;
  }

  function saveNotes(list) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(list.map(normalizeNote)));
  }

  function loadDebtsRaw() {
    try {
      var raw = localStorage.getItem(DEBTS_KEY);
      if (raw) {
        var parsed = JSON.parse(raw);
        if (Array.isArray(parsed)) return parsed;
      }
    } catch (e) { /* ignore */ }
    return null;
  }

  function saveDebts(list) {
    localStorage.setItem(DEBTS_KEY, JSON.stringify(list));
  }

  function ensureSeeded() {
    var verKey = STORAGE_KEY + '_seed_v';
    var ver = localStorage.getItem(verKey);
    if (ver !== String(SEED_VERSION) || !localStorage.getItem(STORAGE_KEY)) {
      saveNotes(DEFAULT_NOTES.map(function (n) { return Object.assign({}, n); }));
      if (!localStorage.getItem(DEBTS_KEY)) {
        saveDebts(DEFAULT_DEBTS.map(function (d) { return Object.assign({}, d); }));
      }
      localStorage.setItem(verKey, String(SEED_VERSION));
    }
  }

  function getAll() {
    ensureSeeded();
    return loadNotes() || [];
  }

  function getById(id) {
    return getAll().find(function (n) { return n.id === id; }) || null;
  }

  function getDebts() {
    ensureSeeded();
    return loadDebtsRaw() || DEFAULT_DEBTS.slice();
  }

  function nextId() {
    var list = getAll();
    var max = list.reduce(function (m, n) {
      var num = parseInt(String(n.id || '').replace(/\D/g, ''), 10);
      return isNaN(num) ? m : Math.max(m, num);
    }, 0);
    return 'CN-' + String(max + 1).padStart(3, '0');
  }

  function getEligibleCases() {
    if (typeof CasesWorkflow === 'undefined') return [];
    return CasesWorkflow.getAll().filter(function (c) {
      if (c.stageKey !== 'delivered') return false;
      return typeof CasesWorkflow.isMilitary === 'function'
        ? !CasesWorkflow.isMilitary(c)
        : c.patientType !== 'military';
    });
  }

  function findDebtCompany(company) {
    var debts = getDebts();
    var exact = debts.find(function (d) { return d.company === company; });
    if (exact) return exact;
    var norm = String(company || '').replace(/\s+/g, '');
    return debts.find(function (d) {
      return String(d.company || '').replace(/\s+/g, '').indexOf(norm) !== -1 ||
        norm.indexOf(String(d.company || '').replace(/\s+/g, '')) !== -1;
    }) || null;
  }

  function applyCreditToDebt(company, amount) {
    var debts = getDebts();
    var idx = debts.findIndex(function (d) { return d.company === company; });
    if (idx === -1) {
      var fuzzy = findDebtCompany(company);
      if (fuzzy) idx = debts.findIndex(function (d) { return d.company === fuzzy.company; });
    }
    if (idx === -1) {
      debts.push({ company: company, due: 0, collected: 0, status: 'paid' });
      idx = debts.length - 1;
    }
    var d = debts[idx];
    var newDue = Math.max(0, (d.due || 0) - amount);
    var newCollected = Math.max(0, (d.collected || 0) - amount);
    var status = newDue <= 0 ? 'paid' : (newCollected > 0 ? 'partial' : 'pending');
    debts[idx] = Object.assign({}, d, { due: newDue, collected: newCollected, status: status });
    saveDebts(debts);
    return debts[idx];
  }

  function createNote(data) {
    if (typeof CasesWorkflow === 'undefined') return { ok: false, error: 'cases_unavailable' };
    var caseItem = CasesWorkflow.getById(data.caseId);
    if (!caseItem) return { ok: false, error: 'case_not_found' };
    if (caseItem.stageKey !== 'delivered') {
      return { ok: false, error: 'not_delivered', reason: 'إشعار الدائن متاح فقط بعد التسليم' };
    }
    var ptype = typeof CasesWorkflow.isMilitary === 'function'
      ? (CasesWorkflow.isMilitary(caseItem) ? 'military' : 'civilian')
      : caseItem.patientType;
    if (ptype === 'military') {
      return { ok: false, error: 'military_path', reason: 'المسار العسكري: تكلفة سيادية — لا يُصدر Credit Note تأميني' };
    }

    var originalTotal = caseItem.totalCost || caseItem.quoteTotal || 0;
    var type = data.type === 'full' ? 'full' : 'partial';
    var amount = type === 'full'
      ? originalTotal
      : Math.max(1, parseInt(data.amount, 10) || 0);

    if (type === 'partial' && amount >= originalTotal) {
      return { ok: false, error: 'amount_too_high', reason: 'المبلغ الجزئي يجب أن يكون أقل من إجمالي الفاتورة' };
    }

    var note = normalizeNote({
      id: nextId(),
      caseId: caseItem.id,
      orderRef: caseItem.orderRef,
      patient: caseItem.patient,
      company: caseItem.company || data.company || '—',
      type: type,
      amount: amount,
      originalTotal: originalTotal,
      reason: data.reason || '—',
      status: 'pending',
      createdAt: formatStamp(),
      approvedAt: null,
      approvedBy: null
    });

    var list = getAll();
    list.unshift(note);
    saveNotes(list);
    return { ok: true, note: note };
  }

  function approveNote(id, adminUser) {
    var list = getAll();
    var idx = list.findIndex(function (n) { return n.id === id; });
    if (idx === -1) return { ok: false, error: 'not_found' };
    var note = list[idx];
    if (note.status !== 'pending') return { ok: false, error: 'already_processed' };

    if (typeof CasesWorkflow !== 'undefined') {
      var caseItem = CasesWorkflow.getById(note.caseId);
      if (caseItem) {
        var newTotal = Math.max(0, (caseItem.totalCost || 0) - note.amount);
        var newPaid = Math.max(0, (caseItem.paid || 0) - Math.min(note.amount, caseItem.paid || 0));
        CasesWorkflow.upsert(Object.assign({}, caseItem, {
          totalCost: newTotal,
          paid: newPaid,
          creditNoteApplied: note.id,
          creditNoteAmount: note.amount
        }));
      }
    }

    applyCreditToDebt(note.company, note.amount);

    note = Object.assign({}, note, {
      status: 'approved',
      approvedAt: formatStamp(),
      approvedBy: adminUser || 'أحمد محمود'
    });
    list[idx] = note;
    saveNotes(list);
    return { ok: true, note: note, debts: getDebts() };
  }

  function rejectNote(id, adminUser) {
    var list = getAll();
    var idx = list.findIndex(function (n) { return n.id === id; });
    if (idx === -1) return { ok: false, error: 'not_found' };
    var note = list[idx];
    if (note.status !== 'pending') return { ok: false, error: 'already_processed' };
    note = Object.assign({}, note, {
      status: 'rejected',
      approvedAt: formatStamp(),
      approvedBy: adminUser || 'أحمد محمود'
    });
    list[idx] = note;
    saveNotes(list);
    return { ok: true, note: note };
  }

  function statusLabel(status) {
    if (status === 'approved') return 'معتمد';
    if (status === 'rejected') return 'مرفوض';
    if (status === 'pending') return 'بانتظار الموافقة';
    return status || '—';
  }

  function typeLabel(type) {
    return type === 'full' ? 'كامل' : 'جزئي';
  }

  return {
    STORAGE_KEY: STORAGE_KEY,
    DEBTS_KEY: DEBTS_KEY,
    SEED_VERSION: SEED_VERSION,
    ensureSeeded: ensureSeeded,
    getAll: getAll,
    getById: getById,
    getDebts: getDebts,
    getEligibleCases: getEligibleCases,
    createNote: createNote,
    approveNote: approveNote,
    rejectNote: rejectNote,
    statusLabel: statusLabel,
    typeLabel: typeLabel,
    formatMoney: formatMoney,
    findDebtCompany: findDebtCompany
  };
})();
