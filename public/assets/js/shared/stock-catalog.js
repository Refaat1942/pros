/**

 * StockCatalog — كatalog المخزون المشترك (الإدارة تُعرّف الأصناف والأسعار)

 */

var StockCatalog = (function () {

  var STORAGE_KEY = 'clinic_stock_catalog';

  var LOW_QTY_THRESHOLD = 3;



  var DEFAULT = [

    { code: 'ITM-001', name: 'ركبة هيدروليكية', spec: 'Medium — Ottobock', qty: 8, reserved: 2, category: 'مفاصل', status: 'ok', prices: [

      { id: 'PR-001-1', label: 'دفعة محلية', supplier: 'النيل للتوريدات', supplierType: 'محلي', itemCode: 'ITM-001-01', amount: 42000 },

      { id: 'PR-001-2', label: 'Ottobock Egypt', supplier: 'Ottobock Egypt', supplierType: 'OEM', itemCode: 'ITM-001-02', amount: 95000 }

    ]},

    { code: 'ITM-002', name: 'ركبة Polycentric', spec: 'Large', qty: 3, reserved: 1, category: 'مفاصل', status: 'low', prices: [

      { id: 'PR-002-1', label: 'Össur', supplier: 'Össur Middle East', supplierType: 'مستورد', itemCode: 'ITM-002-01', amount: 72000 },

      { id: 'PR-002-2', label: 'Proteor', supplier: 'Proteor France', supplierType: 'OEM', itemCode: 'ITM-002-02', amount: 68000 }

    ]},

    { code: 'ITM-003', name: 'قدم Carbon Spring', spec: '8 طبقات', qty: 12, reserved: 3, category: 'أقدام', status: 'ok', prices: [

      { id: 'PR-003-1', label: 'Blatchford', supplier: 'Blatchford Group', supplierType: 'مستورد', itemCode: 'ITM-003-01', amount: 55000 }

    ]},

    { code: 'ITM-004', name: 'بطانة Silicone', spec: 'Medium', qty: 24, reserved: 0, category: 'بطانات', status: 'ok', prices: [

      { id: 'PR-004-1', label: 'محلي', supplier: 'الإسكندرية الطبية', supplierType: 'محلي', itemCode: 'ITM-004-01', amount: 8500 },

      { id: 'PR-004-2', label: 'Ottobock', supplier: 'Ottobock Egypt', supplierType: 'OEM', itemCode: 'ITM-004-02', amount: 12000 }

    ]},

    { code: 'ITM-005', name: 'محول Pyramidal', spec: 'Standard', qty: 18, reserved: 2, category: 'محولات', status: 'ok', prices: [

      { id: 'PR-005-1', label: 'Standard', supplier: 'Ottobock Egypt', supplierType: 'موزّع', itemCode: 'ITM-005-01', amount: 15000 }

    ]},

    { code: 'ITM-006', name: 'Pin Lock', spec: '30mm', qty: 2, reserved: 1, category: 'إكسسوارات', status: 'low', prices: [

      { id: 'PR-006-1', label: 'محلي', supplier: 'النيل للتوريدات', supplierType: 'محلي', itemCode: 'ITM-006-01', amount: 3200 },

      { id: 'PR-006-2', label: 'Ottobock', supplier: 'Ottobock Egypt', supplierType: 'OEM', itemCode: 'ITM-006-02', amount: 5800 }

    ]},

    { code: 'ITM-007', name: 'غطاء تجميلي', spec: 'Wide', qty: 12, reserved: 0, category: 'إكسسوارات', status: 'ok', lastMoved: '20/10/2025', prices: [

      { id: 'PR-007-1', label: 'Wide Cover', supplier: 'Proteor France', supplierType: 'مستورد', itemCode: 'ITM-007-01', amount: 18000 }

    ]},

    { code: 'ITM-008', name: 'بطانة Gel', spec: 'Medium', qty: 8, reserved: 1, category: 'بطانات', status: 'ok', prices: [

      { id: 'PR-008-1', label: 'Gel Liner', supplier: 'Össur Middle East', supplierType: 'مستورد', itemCode: 'ITM-008-01', amount: 9500 }

    ]},

    { code: 'ITM-009', name: 'جوارب تجويف', spec: '3 أزواج', qty: 56, reserved: 0, category: 'بطانات', status: 'ok', lastMoved: '02/09/2025', prices: [

      { id: 'PR-009-1', label: 'محلي', supplier: 'الإسكندرية الطبية', supplierType: 'محلي', itemCode: 'ITM-009-01', amount: 450 }

    ]},

    { code: 'ITM-010', name: 'مفصل كوع', spec: 'Small — Mechanical', qty: 1, reserved: 1, category: 'مفاصل', status: 'low', prices: [

      { id: 'PR-010-1', label: 'Mechanical', supplier: 'Ottobock Egypt', supplierType: 'OEM', itemCode: 'ITM-010-01', amount: 38000 },

      { id: 'PR-010-2', label: 'Proteor', supplier: 'Proteor France', supplierType: 'مستورد', itemCode: 'ITM-010-02', amount: 35000 }

    ]}

  ];



  function formatPrice(amount) {

    var n = parseInt(amount, 10) || 0;

    return n.toLocaleString('ar-EG') + ' ج.م';

  }



  function normalizePrice(price) {

    var p = Object.assign({}, price);

    if (!p.itemCode && p.batch) p.itemCode = p.batch;

    delete p.batch;

    if (!p.supplierType) {

      p.supplierType = (p.label && p.label.indexOf('محلي') !== -1) ? 'محلي' : 'مستورد';

    }

    return p;

  }



  function syncStatus(item) {

    item.status = (item.qty || 0) <= LOW_QTY_THRESHOLD ? 'low' : 'ok';

    return item;

  }



  // تصنيف شجري مبسّط للأصناف حسب الفئة (Item Master storage tree)
  var STORE_CLASS_MAP = {
    'مفاصل': 'قطع خام',
    'أقدام': 'قطع خام',
    'محولات': 'قطع خام',
    'بطانات': 'مواد مساعدة',
    'إكسسوارات': 'أدوات مساعدة'
  };

  function deriveStoreClass(category) {
    return STORE_CLASS_MAP[category] || 'مواد خام';
  }

  function deriveBarcode(code) {
    return 'BC-' + String(code || '').replace(/\D/g, '');
  }

  function normalizeItem(item) {

    var copy = Object.assign({}, item);

    delete copy.min;

    delete copy.max;

    delete copy.icon;

    if (!Array.isArray(copy.prices) || !copy.prices.length) {
      var def = DEFAULT.find(function(d) { return d.code === copy.code; });
      if (def && def.prices && def.prices.length) {
        copy.prices = def.prices.map(function(p) { return Object.assign({}, p); });
      } else {
        copy.prices = [];
      }
    }

    copy.prices = copy.prices.map(normalizePrice);

    copy.reserved = copy.reserved || 0;

    copy.qty = copy.qty || 0;

    // حقول بطاقة الصنف الرئيسية (Item Master)
    copy.uom = copy.uom || 'قطعة';
    copy.barcode = copy.barcode || deriveBarcode(copy.code);
    copy.storeClass = copy.storeClass || deriveStoreClass(copy.category);
    if (!copy.lastMoved) {
      var dm = DEFAULT.find(function (d) { return d.code === copy.code; });
      copy.lastMoved = (dm && dm.lastMoved) || '01/06/2026';
    }

    return syncStatus(copy);

  }

  // المتوسط المرجح للتكلفة (WAC) — يُستخدم لتقييم المخزون والتقارير.
  // يرجّح أسعار الدفعات بكمياتها إن وُجدت، وإلا متوسط بسيط.
  function wac(item) {
    var prices = (item && item.prices) || [];
    if (!prices.length) return 0;
    var totalQty = 0, totalVal = 0;
    prices.forEach(function (p) {
      var q = p.qty != null ? p.qty : 1;
      totalQty += q;
      totalVal += (p.amount || 0) * q;
    });
    return totalQty ? Math.round(totalVal / totalQty) : 0;
  }

  // أعلى سعر شراء متاح (للتسعير) — دفعات كميتها > 0 إن وُجدت.
  function highestPrice(item) {
    var prices = (item && item.prices) || [];
    if (!prices.length) return 0;
    var eligible = prices.filter(function (p) { return p.qty == null || p.qty > 0; });
    var pool = (eligible.length ? eligible : prices).map(function (p) { return p.amount || 0; });
    return Math.max.apply(null, pool);
  }

  // قيمة المخزون الإجمالية بطريقة WAC.
  function inventoryValue() {
    return getAll().reduce(function (s, it) { return s + (it.qty || 0) * wac(it); }, 0);
  }

  // الأصناف الراكدة (تجاوزت عدد الأيام منذ آخر حركة).
  function getStagnant(days, refDate) {
    var limit = days || 180;
    var ref = refDate || new Date(2026, 5, 8);
    return getAll().filter(function (it) {
      var parts = String(it.lastMoved || '').split('/');
      if (parts.length < 3) return false;
      var d = new Date(+parts[2], +parts[1] - 1, +parts[0]);
      var diff = Math.floor((ref - d) / 86400000);
      return diff > limit;
    });
  }

  // حركة وارد بالباركود: تضيف دفعة جديدة وتحدّث الرصيد وتعيد حساب WAC ضمنياً.
  function receiveStock(code, batch) {
    var items = getAll();
    var idx = items.findIndex(function (i) { return i.code === code; });
    if (idx === -1) return { ok: false, error: 'not_found' };
    var item = items[idx];
    var qty = parseInt(batch.qty, 10) || 0;
    if (qty <= 0) return { ok: false, error: 'invalid_qty' };
    var prices = (item.prices || []).slice();
    prices.push(normalizePrice({
      id: nextPriceId(code),
      label: batch.invoiceNo ? ('فاتورة ' + batch.invoiceNo) : 'توريد',
      supplier: batch.supplier || 'مورد',
      supplierType: batch.supplierType || 'مستورد',
      itemCode: code + '-IN',
      amount: parseInt(batch.amount, 10) || 0,
      qty: qty,
      invoiceNo: batch.invoiceNo || null,
      receivedAt: batch.date || '08/06/2026'
    }));
    items[idx] = normalizeItem(Object.assign({}, item, {
      qty: (item.qty || 0) + qty,
      prices: prices,
      lastMoved: batch.date || '08/06/2026'
    }));
    saveAll(items);
    return { ok: true, item: items[idx], wac: wac(items[idx]) };
  }



  function getAll() {

    try {

      var raw = localStorage.getItem(STORAGE_KEY);

      if (raw) {

        var parsed = JSON.parse(raw);

        if (Array.isArray(parsed) && parsed.length) {

          return parsed.map(normalizeItem);

        }

      }

    } catch (e) { /* ignore */ }

    return DEFAULT.map(function (item) { return normalizeItem(Object.assign({}, item)); });

  }



  function saveAll(items) {

    localStorage.setItem(STORAGE_KEY, JSON.stringify(items.map(normalizeItem)));

  }



  function cloneDefaultList() {
    return DEFAULT.map(function (item) {
      return normalizeItem(Object.assign({}, item, {
        prices: (item.prices || []).map(function (p) { return Object.assign({}, p); })
      }));
    });
  }

  function ensureSeeded() {

    if (!localStorage.getItem(STORAGE_KEY)) {

      saveAll(cloneDefaultList());

    }

  }

  function resetToSeed() {
    saveAll(cloneDefaultList());
  }



  function nextCode() {

    var items = getAll();

    var maxNum = items.reduce(function (m, item) {

      var n = parseInt(String(item.code || '').replace(/\D/g, ''), 10);

      return isNaN(n) ? m : Math.max(m, n);

    }, 0);

    return 'ITM-' + String(maxNum + 1).padStart(3, '0');

  }



  function nextPriceId(code) {

    var items = getAll();

    var item = items.find(function (i) { return i.code === code; });

    var count = item && item.prices ? item.prices.length : 0;

    return 'PR-' + String(code || 'NEW').replace(/\D/g, '') + '-' + (count + 1);

  }



  function addItem(item) {

    var items = getAll();

    items.push(normalizeItem(item));

    saveAll(items);

    return item;

  }



  function updateItem(code, updates) {

    var items = getAll();

    var idx = items.findIndex(function (i) { return i.code === code; });

    if (idx === -1) return null;

    items[idx] = normalizeItem(Object.assign({}, items[idx], updates, { code: code }));

    saveAll(items);

    return items[idx];

  }



  function removeItem(code) {

    var items = getAll().filter(function (i) { return i.code !== code; });

    saveAll(items);

  }



  function issueQty(code, qty) {

    var items = getAll();

    var idx = items.findIndex(function (i) { return i.code === code; });

    if (idx === -1) return { ok: false, error: 'not_found' };

    var item = items[idx];

    var need = qty || 1;

    if ((item.qty || 0) < need) {

      return { ok: false, error: 'insufficient', available: item.qty || 0, code: code };

    }

    items[idx] = normalizeItem(Object.assign({}, item, { qty: item.qty - need, lastMoved: '08/06/2026' }));

    saveAll(items);

    return { ok: true, item: items[idx] };

  }



  function returnQty(code, qty, meta) {

    var items = getAll();

    var idx = items.findIndex(function (i) { return i.code === code; });

    if (idx === -1) return { ok: false, error: 'not_found' };

    var item = items[idx];

    var add = Math.max(1, parseInt(qty, 10) || 1);

    items[idx] = normalizeItem(Object.assign({}, item, {

      qty: (item.qty || 0) + add,

      lastMoved: '08/06/2026',

      lastReturnRef: meta && meta.returnId ? meta.returnId : item.lastReturnRef

    }));

    saveAll(items);

    return { ok: true, item: items[idx], qtyAdded: add };

  }



  function resolveBarcode(scanned) {

    var s = String(scanned || '').trim().toUpperCase();

    if (!s) return '';

    var items = getAll();

    var byBc = items.find(function (i) { return String(i.barcode || '').toUpperCase() === s; });

    if (byBc) return byBc.code;

    if (s.indexOf('BC-') === 0) {

      var n = parseInt(s.replace(/^BC-/, ''), 10);

      if (!isNaN(n)) return 'ITM-' + String(n).padStart(3, '0');

    }

    return s;

  }



  function getPriceSummary(prices) {

    if (!prices || !prices.length) return { count: 0, min: 0, max: 0 };

    var amounts = prices.map(function (p) { return p.amount || 0; });

    return {

      count: prices.length,

      min: Math.min.apply(null, amounts),

      max: Math.max.apply(null, amounts)

    };

  }



  return {

    getAll: getAll,

    saveAll: saveAll,

    ensureSeeded: ensureSeeded,

    resetToSeed: resetToSeed,

    nextCode: nextCode,

    nextPriceId: nextPriceId,

    addItem: addItem,

    updateItem: updateItem,

    removeItem: removeItem,

    issueQty: issueQty,

    returnQty: returnQty,

    resolveBarcode: resolveBarcode,

    deriveBarcode: deriveBarcode,

    receiveStock: receiveStock,

    wac: wac,

    highestPrice: highestPrice,

    inventoryValue: inventoryValue,

    getStagnant: getStagnant,

    normalizeItem: normalizeItem,

    syncStatus: syncStatus,

    formatPrice: formatPrice,

    getPriceSummary: getPriceSummary,

    DEFAULT: DEFAULT,

    STORAGE_KEY: STORAGE_KEY,

    LOW_QTY_THRESHOLD: LOW_QTY_THRESHOLD

  };

})();

