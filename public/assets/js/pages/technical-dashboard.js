var dashboardMode = (document.body && document.body.getAttribute('data-dashboard')) || 'inventory';
var dashboardDefaults = {
  spec: 'orders',
  adjustments: 'adjustments',
  operations: 'operations',
  inventory: 'inventory'
};

var orders = [];
var inventory = [];
var inventorySuppliers = [];
var pricingQueue = [];

function normalizeInventoryItem(raw) {
  var qty = parseInt(raw.qty, 10) || 0;
  var reserved = parseInt(raw.reserved, 10) || 0;
  var available = raw.available != null ? (parseInt(raw.available, 10) || 0) : (qty - reserved);
  var backorder = raw.backorder != null ? (parseInt(raw.backorder, 10) || 0) : Math.max(0, reserved - qty);
  var status = raw.status === 'backorder' || backorder > 0
    ? 'backorder'
    : (raw.status === 'low' ? 'low' : 'ok');
  return {
    id: raw.id,
    code: raw.code || '',
    name: raw.name || '',
    spec: raw.spec || '—',
    category: raw.category || '—',
    qty: qty,
    reserved: reserved,
    available: available,
    backorder: backorder,
    min_qty: parseInt(raw.min_qty, 10) || 0,
    status: status,
    lastMovedAt: raw.last_moved_at || raw.lastMovedAt || null
  };
}

function setInventoryItems(items) {
  inventory = (items || []).map(normalizeInventoryItem);
}

function csrfToken() {
  var meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? meta.getAttribute('content') : '';
}

function fetchInventoryFromServer() {
  return fetch('/technical/inventory/list', {
    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
  }).then(function (response) {
    if (!response.ok) throw new Error('inventory_fetch_failed');
    return response.json();
  }).then(function (payload) {
    window.__INVENTORY_ITEMS = payload.data || [];
    setInventoryItems(window.__INVENTORY_ITEMS);
  });
}

function refreshPaginated() {
  if (!window.TablePagination) return;
  Array.prototype.slice.call(arguments).forEach(function (id) {
    var el = document.getElementById(id);
    if (el) TablePagination.refresh(el);
  });
}

function itemReorderThreshold(item) {
  var min = parseInt(item.min_qty, 10);
  return min > 0 ? min : StockCatalog.LOW_QTY_THRESHOLD;
}

function syncInventoryStatus() {
  inventory.forEach(function (item) {
    if (item.backorder > 0) {
      item.status = 'backorder';
      return;
    }
    var available = item.available != null ? item.available : (item.qty - (item.reserved || 0));
    item.status = available <= itemReorderThreshold(item) ? 'low' : 'ok';
  });
}

function persistInventory() {
  syncInventoryStatus();
}

function reloadInventory() {
  if (Array.isArray(window.__INVENTORY_ITEMS)) {
    setInventoryItems(window.__INVENTORY_ITEMS);
  } else {
    inventory = [];
  }
  if (Array.isArray(window.__INVENTORY_SUPPLIERS)) {
    inventorySuppliers = window.__INVENTORY_SUPPLIERS;
  }
  syncInventoryStatus();
}

var inventoryFilter = 'all';
var inventorySearchTerm = '';

var pricingFilter = 'all';
var pricingSearchTerm = '';
var ordersSearchTerm = '';
var bomFilter = 'all';
var bomSearchTerm = '';

var sectionTitles = {
  inventory: 'المخزون — توفر الأصناف',
  orders: 'طلبات التوصيف — إرسال للتسعير',
  spec: 'معاينة التوصيف (بدون صرف)',
  pricing: 'طلبات مرسلة للتسعير',
  bom: 'قوائم صرف المواد — مخزن خام / مخزن إنتاج / مخزن تسليم',
  returns: 'إذن ارتجاع — ورشة → مخزن',
  operations: 'مكتب التشغيل — أوامر الصرف والإنتاج',
  adjustments: 'المعدلات — تجارب التركيب والمقاسات'
};

function dashboardPageUrl(page) {
  var seg = window.location.pathname.split('/').filter(Boolean);
  return '/' + (seg[0] || 'technical') + '/' + page;
}

function switchSection(sectionId) {
  if (!document.getElementById('section-' + sectionId)) {
    window.location.href = dashboardPageUrl(sectionId);
    return;
  }
  if (sectionId === 'bom') renderBomSection();
  if (sectionId === 'returns') renderReturnsSection();
  if (sectionId === 'operations') renderOperations();
  if (sectionId === 'adjustments') renderAdjustments();
}

document.querySelectorAll('.nav-menu a[data-section]').forEach(function (link) {
  link.addEventListener('click', function (e) {
    var sectionId = link.getAttribute('data-section');
    if (sectionId && !document.getElementById('section-' + sectionId)) {
      e.preventDefault();
      switchSection(sectionId);
    }
  });
});

function getFilteredInventory() {
  return inventory.filter(function (item) {
    var matchFilter = inventoryFilter === 'all' || item.status === inventoryFilter;
    var matchSearch = !inventorySearchTerm ||
      item.name.indexOf(inventorySearchTerm) !== -1 ||
      item.spec.indexOf(inventorySearchTerm) !== -1 ||
      item.code.indexOf(inventorySearchTerm) !== -1;
    return matchFilter && matchSearch;
  });
}

function getFilteredPricing() {
  return pricingQueue.filter(function (p) {
    var matchFilter = pricingFilter === 'all' || p.statusKey === pricingFilter;
    var matchSearch = !pricingSearchTerm ||
      p.id.indexOf(pricingSearchTerm) !== -1 ||
      p.patient.indexOf(pricingSearchTerm) !== -1 ||
      p.orderRef.indexOf(pricingSearchTerm) !== -1;
    return matchFilter && matchSearch;
  });
}

function getFilteredOrders() {
  return orders.filter(function (o) {
    if (!ordersSearchTerm) return true;
    return o.id.indexOf(ordersSearchTerm) !== -1 ||
      o.name.indexOf(ordersSearchTerm) !== -1 ||
      o.doctor.indexOf(ordersSearchTerm) !== -1;
  });
}

function exportInventory(type) {
  var data = getFilteredInventory();
  var headers = ['كود الصنف', 'اسم الصنف', 'الرصيد المتاح', 'الحالة'];
  var rows = data.map(function (i) {
    var available = i.available != null ? i.available : (i.qty - (i.reserved || 0));
    var statusLabel = i.status === 'backorder' ? 'طلب توريد' : (i.status === 'low' ? 'كمية منخفضة' : 'متوفر');
    return [i.code, i.name, available, statusLabel];
  });
  if (type === 'excel') ExportKit.toExcel(ExportKit.buildFilename('المخزون'), headers, rows);
  else ExportKit.toPDF('توفر المخزون', headers, rows);
}

function exportPricing(type) {
  var data = getFilteredPricing();
  var headers = ['رقم الطلب', 'أمر التشغيل', 'المريض', 'جهة التعاقد', 'التاريخ', 'البنود', 'الحالة'];
  var rows = data.map(function (p) {
    return [p.id, p.orderRef, p.patient, p.company, p.date, p.items, p.statusLabel];
  });
  if (type === 'excel') ExportKit.toExcel(ExportKit.buildFilename('طلبات_التسعير'), headers, rows);
  else ExportKit.toPDF('طلبات التسعير', headers, rows);
}

function exportOrders(type) {
  var data = getFilteredOrders();
  var headers = ['رقم الطلب', 'المريض', 'التوصيات الطبية', 'الطبيب', 'التاريخ'];
  var rows = data.map(function (o) {
    return [o.id, o.name, recommendationsText(o.recommendations), o.doctor, o.date];
  });
  if (type === 'excel') ExportKit.toExcel(ExportKit.buildFilename('طلبات_التوصيف'), headers, rows);
  else ExportKit.toPDF('طلبات الصرف', headers, rows);
}

function updateInventoryAnalyticsCards() {
  var el = document.getElementById('analytics-inventory-charts');
  if (!el) return;
  var values = el.querySelectorAll('.ck-stat-value');
  if (values.length < 4) return;
  var total = inventory.length;
  var okCount = inventory.filter(function (i) { return i.status === 'ok'; }).length;
  var backorderCount = inventory.filter(function (i) { return i.status === 'backorder'; }).length;
  var lowCount = inventory.filter(function (i) { return i.status === 'low'; }).length;
  values[0].textContent = String(total);
  values[1].textContent = String(okCount);
  values[2].textContent = String(backorderCount);
  values[3].textContent = String(lowCount);
}

function renderInventory() {
  if (!document.getElementById('inventoryTable')) return;
  var filtered = getFilteredInventory();

  document.getElementById('inventoryBadge').textContent = inventory.length + ' صنف';
  document.getElementById('inventoryFooter').textContent =
    'عرض ' + filtered.length + ' من ' + inventory.length + ' أصناف';

  document.getElementById('inventoryTable').innerHTML = filtered.length ? filtered.map(function (item) {
    var isLow = item.status === 'low';
    var isBackorder = item.status === 'backorder';
    var available = item.available != null ? item.available : (item.qty - (item.reserved || 0));
    var availClass = isBackorder ? 'backorder' : item.status;
    var statusLabel = isBackorder
      ? 'طلب توريد (' + (item.backorder || 0) + ')'
      : (isLow ? 'كمية منخفضة' : 'متوفر');
    var statusClass = isBackorder ? 'backorder' : (isLow ? 'low' : 'available');
    return '<tr>' +
      '<td class="item-code-cell"><span class="item-code">' + item.code + '</span></td>' +
      '<td><div class="item-name">' + item.name + '</div></td>' +
      '<td class="qty-cell"><div class="qty-badge ' + availClass + '">' + available + '</div></td>' +
      '<td class="status-cell">' +
      '<span class="stock-status ' + statusClass + '">' +
      '<span class="status-dot"></span>' +
      statusLabel +
      '</span>' +
      '</td>' +
      '</tr>';
  }).join('') : '<tr><td colspan="4" style="text-align:center;padding:32px;color:var(--text-muted)">لا توجد أصناف في المخزون — أضف الأصناف من لوحة الإدارة</td></tr>';
  refreshPaginated('inventoryTable');
  updateInventoryAnalyticsCards();
}

var inventorySearchEl = document.getElementById('inventorySearch');
if (inventorySearchEl) inventorySearchEl.addEventListener('input', function (e) {
  inventorySearchTerm = e.target.value.trim();
  renderInventory();
});

var inventoryFilters = document.querySelectorAll('#inventoryFilters .filter-pill');
if (inventoryFilters.length) inventoryFilters.forEach(function (pill) {
  pill.addEventListener('click', function () {
    document.querySelectorAll('#inventoryFilters .filter-pill').forEach(function (p) { p.classList.remove('active'); });
    pill.classList.add('active');
    inventoryFilter = pill.getAttribute('data-filter');
    renderInventory();
  });
});

function getPricingStepsHtml(step) {
  var labels = PricingQueue.STEP_LABELS;
  var html = '<div class="pricing-detail-steps">';
  for (var s = 1; s <= 2; s++) {
    var cls = s < step ? 'done' : s === step ? 'active' : 'pending';
    html += '<div class="pricing-detail-step ' + cls + '">' +
      '<span class="step-num">' + s + '</span>' +
      '<span class="step-label">' + labels[s - 1] + '</span>' +
      '</div>';
  }
  html += '</div>';
  return html;
}

function openPricingDetailModal(pricingId) {
  var p = pricingQueue.find(function (x) { return x.id === pricingId; });
  if (!p) return;
  var order = orders.find(function (o) { return o.id === p.orderRef; });
  var recSource = (p.recommendations && p.recommendations.length) ? p.recommendations : (order ? order.recommendations : []);

  document.getElementById('pricingModalTitle').textContent = '🧾 ' + p.id;
  document.getElementById('pricingModalMeta').innerHTML =
    '<div class="order-detail-item"><div class="label">رقم طلب التسعير</div><div class="value">' + p.id + '</div></div>' +
    '<div class="order-detail-item"><div class="label">أمر التشغيل</div><div class="value">' + p.orderRef + '</div></div>' +
    '<div class="order-detail-item"><div class="label">المريض</div><div class="value">' + p.patient + '</div></div>' +
    '<div class="order-detail-item"><div class="label">جهة التعاقد</div><div class="value">' + p.company + '</div></div>' +
    '<div class="order-detail-item"><div class="label">الطبيب</div><div class="value">' + (order ? order.doctor : '—') + '</div></div>' +
    '<div class="order-detail-item"><div class="label">التاريخ</div><div class="value">' + p.date + '</div></div>' +
    '<div class="order-detail-item"><div class="label">الحالة</div><div class="value"><span class="pricing-status ' + p.statusKey + '"><span class="ps-dot"></span>' + p.statusLabel + '</span></div></div>';

  document.getElementById('pricingModalSteps').innerHTML = getPricingStepsHtml(p.step);

  var rows = '';
  if (recSource && recSource.length) {
    rows = recSource.map(function (rec) {
      var n = normalizeRecItem(rec);
      var stock = findStockItem(n.name, n.code);
      var code = stock ? stock.code : (n.code || '—');
      var status = getStockStatusLabel(stock, n.qty);
      return '<tr>' +
        '<td><strong>' + n.name + '</strong>' + (stock && stock.spec ? '<br><span style="font-size:11px;color:var(--text-muted);">' + stock.spec + '</span>' : '') + '</td>' +
        '<td>' + code + '</td>' +
        '<td>' + (stock ? stock.category : '—') + '</td>' +
        '<td class="col-qty"><strong>' + n.qty + '</strong></td>' +
        '<td class="col-qty">' + (stock ? getAvailableQty(stock) : '—') + '</td>' +
        '<td class="col-status"><span class="' + status.cls + '">' + status.text + '</span></td>' +
        '</tr>';
    }).join('');
  }

  document.getElementById('pricingModalItems').innerHTML = rows ||
    '<tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:20px;">لا توجد أصناف مرتبطة بهذا الطلب</td></tr>';

  document.getElementById('pricingModal').classList.add('visible');
}

function closePricingDetailModal() {
  document.getElementById('pricingModal').classList.remove('visible');
}

function renderPricing() {
  if (!document.getElementById('pricingTable')) return;
  pricingQueue = PricingQueue.getAll();
  var filtered = getFilteredPricing();

  var pendCount = pricingQueue.filter(function (p) { return p.statusKey === 'awaiting_admin_approval'; }).length;
  var sentCount = pricingQueue.filter(function (p) { return p.statusKey === 'sent_to_reception'; }).length;

  document.getElementById('prTotal').textContent = pricingQueue.length;
  document.getElementById('prPending').textContent = pendCount;
  document.getElementById('prSent').textContent = sentCount;
  document.getElementById('pricingCount').textContent = pricingQueue.length;
  document.getElementById('pricingFooter').textContent =
    'عرض ' + filtered.length + ' من ' + pricingQueue.length + ' طلبات — في انتظار موافقة الأدمن ثم الإرسال للاستقبال';

  if (filtered.length === 0) {
    document.getElementById('pricingTable').innerHTML =
      '<tr><td colspan="7"><div class="pricing-empty"><div class="empty-icon">📭</div><p>لا توجد طلبات مطابقة للبحث</p></div></td></tr>';
    refreshPaginated('pricingTable');
    return;
  }

  document.getElementById('pricingTable').innerHTML = filtered.map(function (p, idx) {
    var steps = '';
    for (var s = 1; s <= 2; s++) {
      var cls = s < p.step ? 'done' : s === p.step ? 'active' : 'pending';
      steps += '<div class="step ' + cls + '"></div>';
    }
    return '<tr>' +
      '<td style="color:var(--text-muted);font-weight:600;font-size:12px;">' + (idx + 1) + '</td>' +
      '<td><div class="quote-id-cell">' +
      '<div class="quote-icon">🧾</div>' +
      '<div><div class="quote-id">' + p.id + '</div><div class="quote-ref">' + p.orderRef + '</div></div>' +
      '</div></td>' +
      '<td><div class="patient-cell">' +
      '<div class="patient-name">' + p.patient + '</div>' +
      '<div class="patient-company">' + p.company + '</div>' +
      '</div></td>' +
      '<td style="font-size:13px;color:var(--text-muted);">' + p.date + '</td>' +
      '<td style="text-align:center;"><span class="items-badge">' + p.items + '</span></td>' +
      '<td style="text-align:center;">' +
      '<span class="pricing-status ' + p.statusKey + '"><span class="ps-dot"></span>' + p.statusLabel + '</span>' +
      '<div class="pricing-progress">' + steps + '</div>' +
      '</td>' +
      '<td style="text-align:center;">' +
      '<button class="btn-view" onclick="openPricingDetailModal(\'' + p.id + '\')">عرض التفاصيل</button>' +
      '</td>' +
      '</tr>';
  }).join('');
  refreshPaginated('pricingTable');
}

var pricingSearchEl = document.getElementById('pricingSearch');
if (pricingSearchEl) pricingSearchEl.addEventListener('input', function (e) {
  pricingSearchTerm = e.target.value.trim();
  renderPricing();
});

var ordersSearchEl = document.getElementById('ordersSearch');
if (ordersSearchEl) ordersSearchEl.addEventListener('input', function (e) {
  ordersSearchTerm = e.target.value.trim();
  renderOrders();
});

var pricingFilterPills = document.querySelectorAll('#pricingFilters .filter-pill');
if (pricingFilterPills.length) pricingFilterPills.forEach(function (pill) {
  pill.addEventListener('click', function () {
    document.querySelectorAll('#pricingFilters .filter-pill').forEach(function (p) { p.classList.remove('active'); });
    pill.classList.add('active');
    pricingFilter = pill.getAttribute('data-prfilter');
    renderPricing();
  });
});

var selectedOrder = null;

function countOrderRecommendations(source) {
  var counts = {};
  source.forEach(function (row) {
    (row.recommendations || []).forEach(function (rec) {
      var n = normalizeRecItem(rec);
      counts[n.name] = (counts[n.name] || 0) + n.qty;
    });
  });
  return Object.keys(counts).map(function (name) {
    return { label: name.length > 16 ? name.slice(0, 16) + '…' : name, value: counts[name] };
  }).sort(function (a, b) { return b.value - a.value; }).slice(0, 6);
}

function normalizeRecItem(item) {
  if (typeof item === 'string') return { name: item, qty: 1 };
  return { name: item.name, qty: item.qty || item.selectedQty || 1, code: item.code };
}

function recommendationsText(items) {
  if (!items || !items.length) return '—';
  return items.map(function (item) {
    var n = normalizeRecItem(item);
    return n.qty > 1 ? n.name + ' (' + n.qty + ')' : n.name;
  }).join('، ');
}

function renderOrders() {
  if (!document.getElementById('ordersList')) return;
  var filtered = getFilteredOrders();
  var html = filtered.map(function (o) {
    var active = selectedOrder && selectedOrder.id === o.id ? 'active' : '';
    return '<li class="order-item ' + active + '" data-id="' + o.id + '">' +
      '<div class="order-id">' + o.id + '</div>' +
      '<div class="order-name">' + o.name + '</div>' +
      '<div class="order-meta">' + recommendationsText(o.recommendations) + '</div>' +
      '<span class="order-tag">من: ' + o.doctor + '</span>' +
      '</li>';
  }).join('');
  document.getElementById('ordersList').innerHTML = html || '<li class="empty-state" style="padding:20px;text-align:center;color:var(--text-muted);">لا توجد طلبات مطابقة</li>';
  var specList = document.getElementById('ordersListSpec');
  if (specList) specList.innerHTML = html;
  document.getElementById('ordersCount').textContent = filtered.length;

  document.querySelectorAll('.order-item').forEach(function (item) {
    item.addEventListener('click', function () {
      selectOrder(item.getAttribute('data-id'));
    });
  });
  refreshPaginated('ordersList', 'ordersListSpec');
}

function findStockItem(name, code) {
  if (code) {
    var byCode = inventory.find(function (i) { return i.code === code; });
    if (byCode) return byCode;
  }
  return inventory.find(function (i) { return i.name === name; });
}

function getAvailableQty(stockItem) {
  if (!stockItem) return 0;
  return Math.max(0, (stockItem.qty || 0) - (stockItem.reserved || 0));
}

function getStockStatusLabel(stockItem, requestedQty) {
  if (!stockItem) return { text: 'غير موجود بالمخزون', cls: 'dispense-stock-missing' };
  var available = getAvailableQty(stockItem);
  if (available >= requestedQty) {
    return stockItem.status === 'low'
      ? { text: '⚠️ متوفر (منخفض)', cls: 'dispense-stock-low' }
      : { text: '✓ متوفر', cls: 'dispense-stock-ok' };
  }
  return { text: '✗ غير كافٍ', cls: 'dispense-stock-low' };
}

function renderOrderDispenseDetails() {
  if (!selectedOrder || !document.getElementById('bannerName')) return;

  document.getElementById('bannerName').textContent = selectedOrder.name;
  document.getElementById('bannerOrderId').textContent = selectedOrder.id;
  document.getElementById('bannerDoctor').textContent = selectedOrder.doctor;
  document.getElementById('bannerDate').textContent = selectedOrder.date;
  document.getElementById('bannerCompany').textContent = selectedOrder.company || '—';

  var totalQty = 0;
  var rows = (selectedOrder.recommendations || []).map(function (rec) {
    var n = normalizeRecItem(rec);
    var stock = findStockItem(n.name, n.code);
    var code = stock ? stock.code : (n.code || '—');
    var status = getStockStatusLabel(stock, n.qty);
    totalQty += n.qty;
    return '<tr>' +
      '<td><strong>' + n.name + '</strong>' + (stock && stock.spec ? '<br><span style="font-size:11px;color:var(--text-muted);">' + stock.spec + '</span>' : '') + '</td>' +
      '<td>' + code + '</td>' +
      '<td>' + (stock ? stock.category : '—') + '</td>' +
      '<td class="col-qty"><strong>' + n.qty + '</strong></td>' +
      '<td class="col-qty">' + (stock ? getAvailableQty(stock) : '—') + '</td>' +
      '<td class="col-status"><span class="' + status.cls + '">' + status.text + '</span></td>' +
      '</tr>';
  }).join('');

  document.getElementById('bannerItemCount').textContent = totalQty + ' صنف';
  document.getElementById('orderItemsBody').innerHTML = rows || '<tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:20px;">لا توجد أصناف في الطلب</td></tr>';
}

function selectOrder(id) {
  selectedOrder = orders.find(function (o) { return o.id === id; });
  var emptyState = document.getElementById('emptyState');
  var specForm = document.getElementById('specForm');
  if (!emptyState || !specForm) return;
  emptyState.style.display = 'none';
  specForm.style.display = 'block';
  var hint = document.getElementById('specSectionHint');
  if (hint) hint.style.display = 'none';
  document.getElementById('techNotes').value = '';
  renderOrderDispenseDetails();
  switchSection('orders');
  renderOrders();
}

function showToast(msg) {
  if (window.DashboardToast) {
    window.DashboardToast.show(msg);
    return;
  }
  var toast = document.getElementById('toast');
  toast.textContent = '✅ ' + msg;
  toast.classList.add('show');
  setTimeout(function () { toast.classList.remove('show'); }, 5000);
}

var techOrderSpecs = [];

function saveTechOrderSpec(order) {
  var entry = {
    orderRef: order.id,
    patient: order.name,
    recommendations: (order.recommendations || []).slice()
  };
  var idx = techOrderSpecs.findIndex(function (s) { return s.orderRef === order.id; });
  if (idx >= 0) techOrderSpecs[idx] = entry;
  else techOrderSpecs.push(entry);
}

var specFormEl = document.getElementById('specForm');
if (specFormEl) specFormEl.addEventListener('submit', function (e) {
  e.preventDefault();
  if (!selectedOrder) return;

  var shortages = [];
  (selectedOrder.recommendations || []).forEach(function (rec) {
    var n = normalizeRecItem(rec);
    var stock = findStockItem(n.name, n.code);
    if (!stock || getAvailableQty(stock) < n.qty) {
      shortages.push(n.name);
    }
  });

  if (shortages.length) {
    if (!confirm('بعض الأصناف غير كافية أو غير موجودة:\n• ' + shortages.join('\n• ') + '\n\nهل تريد المتابعة؟')) return;
  }

  var itemCount = (selectedOrder.recommendations || []).reduce(function (s, rec) {
    return s + normalizeRecItem(rec).qty;
  }, 0);

  showToast('تم حساب التكلفة — في انتظار موافقة الأدمن');

  saveTechOrderSpec(selectedOrder);

  PricingQueue.add({
    orderRef: selectedOrder.id,
    patient: selectedOrder.name,
    company: selectedOrder.company || '—',
    date: selectedOrder.date,
    items: itemCount,
    doctor: selectedOrder.doctor,
    recommendations: (selectedOrder.recommendations || []).slice()
  });
  pricingQueue = PricingQueue.getAll();
  var pricingCountEl = document.getElementById('pricingCount');
  if (pricingCountEl) pricingCountEl.textContent = pricingQueue.length;
  renderPricing();

  orders = orders.filter(function (o) { return o.id !== selectedOrder.id; });
  selectedOrder = null;
  document.getElementById('specForm').style.display = 'none';
  document.getElementById('emptyState').style.display = 'block';
  document.getElementById('ordersCount').textContent = orders.length;
  renderOrders();
});
function renderStockAnalytics() {
  return;
}



var closePricingBtn = document.getElementById('closePricingModal');
if (closePricingBtn) closePricingBtn.addEventListener('click', closePricingDetailModal);
var btnClosePricingModal = document.getElementById('btnClosePricingModal');
if (btnClosePricingModal) btnClosePricingModal.addEventListener('click', closePricingDetailModal);
var pricingModalEl = document.getElementById('pricingModal');
if (pricingModalEl) pricingModalEl.addEventListener('click', function (e) {
  if (e.target === pricingModalEl) closePricingDetailModal();
});

function getFilteredBom() {
  return BomInventory.getAll().filter(function (b) {
    var matchFilter = bomFilter === 'all' || b.stage === bomFilter;
    var matchSearch = !bomSearchTerm ||
      (b.patient && b.patient.indexOf(bomSearchTerm) !== -1) ||
      (b.orderRef && b.orderRef.indexOf(bomSearchTerm) !== -1) ||
      (b.id && b.id.indexOf(bomSearchTerm) !== -1);
    return matchFilter && matchSearch;
  });
}

function exportBom(type) {
  var data = getFilteredBom();
  var headers = ['رقم BOM', 'المريض', 'أمر التشغيل', 'المرحلة', 'عدد البنود', 'تاريخ الإنشاء'];
  var rows = data.map(function (b) {
    return [
      b.id,
      b.patient,
      b.orderRef,
      BomInventory.getStageLabel(b.stage),
      (b.items || []).length,
      b.createdAt
    ];
  });
  if (type === 'excel') ExportKit.toExcel(ExportKit.buildFilename('قوائم_صرف_المواد'), headers, rows);
  else ExportKit.toPDF('قائمة مواد BOM', headers, rows);
}
window.exportBom = exportBom;

function handleBomAction(bomId, action) {
  if (action === 'release') { openBarcodeIssue(bomId); return; }
  var result;
  if (action === 'finish') result = BomInventory.completeToFinished(bomId);
  else return;
  if (!result.ok) {
    showToast('⚠️ ' + (result.error || 'تعذّر تنفيذ العملية'));
    return;
  }
  reloadInventory();
  renderInventory();
  updateInventoryAnalyticsCards();
  renderBomSection();
  if (typeof renderOperations === 'function') renderOperations();
  showToast('✅ تم إغلاق BOM — المنتج تام وجاهز للتسليم');
}
window.handleBomAction = handleBomAction;

/* ===== صرف بالباركود ===== */
var barcodeState = { bomId: null, scanned: [] };

function openBarcodeIssue(bomId) {
  var bom = BomInventory.getById(bomId);
  if (!bom) return;
  var check = BomInventory.canReleaseToWip(bom);
  if (!check.ok) { showToast('⚠️ ' + check.reason); return; }
  barcodeState = { bomId: bomId, scanned: [] };
  var req = document.getElementById('barcodeRequired');
  req.innerHTML = '<h4>أكواد أمر التشغيل المطلوبة:</h4>' + (bom.items || []).map(function (it) {
    var bc = 'BC-' + String(it.code).replace(/\D/g, '');
    return '<div class="barcode-req-item"><span>' + (it.name || it.code) + ' ×' + (it.qty || 1) + '</span><code>' + bc + '</code></div>';
  }).join('');
  document.getElementById('barcodeAlarm').style.display = 'none';
  document.getElementById('barcodeInput').value = '';
  renderScanned();
  document.getElementById('barcodeModal').classList.add('visible');
}

function requiredBarcodes() {
  var bom = BomInventory.getById(barcodeState.bomId);
  return (bom ? (bom.items || []) : []).map(function (it) { return 'BC-' + String(it.code).replace(/\D/g, ''); });
}

function renderScanned() {
  var el = document.getElementById('barcodeScanned');
  if (!barcodeState.scanned.length) { el.innerHTML = '<p style="color:var(--text-muted);font-size:13px;">لم يُمسح أي باركود بعد.</p>'; return; }
  el.innerHTML = barcodeState.scanned.map(function (code) {
    var ok = requiredBarcodes().indexOf(code) !== -1;
    return '<span class="barcode-chip ' + (ok ? 'ok' : 'bad') + '">' + (ok ? '✓' : '✗') + ' ' + code + '</span>';
  }).join('');
}

function addScan(code) {
  code = String(code || '').trim().toUpperCase();
  if (!code) return;
  barcodeState.scanned.push(code);
  var ok = requiredBarcodes().indexOf(code) !== -1;
  if (!ok) triggerAlarm('باركود غير مطابق لأمر التشغيل: ' + code + ' — تم إيقاف الصرف!');
  else document.getElementById('barcodeAlarm').style.display = 'none';
  renderScanned();
}

function triggerAlarm(text) {
  var alarm = document.getElementById('barcodeAlarm');
  document.getElementById('barcodeAlarmText').textContent = text;
  alarm.style.display = 'flex';
  alarm.classList.remove('shake'); void alarm.offsetWidth; alarm.classList.add('shake');
  try {
    var ctx = new (window.AudioContext || window.webkitAudioContext)();
    var o = ctx.createOscillator(); var g = ctx.createGain();
    o.type = 'square'; o.frequency.value = 880; o.connect(g); g.connect(ctx.destination);
    g.gain.value = 0.08; o.start(); setTimeout(function () { o.stop(); ctx.close(); }, 400);
  } catch (e) { /* ignore */ }
}

function closeBarcodeModal() { document.getElementById('barcodeModal').classList.remove('visible'); }

function confirmIssue() {
  var req = requiredBarcodes();
  var result = BomInventory.releaseToWipByBarcode(barcodeState.bomId, barcodeState.scanned);
  if (!result.ok) {
    triggerAlarm(result.error || 'تعذّر الصرف');
    return;
  }
  closeBarcodeModal();
  reloadInventory();
  renderInventory();
  updateInventoryAnalyticsCards();
  renderBomSection();
  if (typeof renderOperations === 'function') renderOperations();
  showToast('✅ تم صرف المواد — نقل من مخزن خام إلى مخزن إنتاج');
}

(function bindBarcode() {
  var addBtn = document.getElementById('btnAddScan');
  var input = document.getElementById('barcodeInput');
  if (addBtn) addBtn.addEventListener('click', function () { addScan(input.value); input.value = ''; });
  if (input) input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { addScan(input.value); input.value = ''; } });
  var simCorrect = document.getElementById('btnSimCorrect');
  if (simCorrect) simCorrect.addEventListener('click', function () { barcodeState.scanned = requiredBarcodes().slice(); document.getElementById('barcodeAlarm').style.display = 'none'; renderScanned(); });
  var simWrong = document.getElementById('btnSimWrong');
  if (simWrong) simWrong.addEventListener('click', function () { addScan('BC-999'); });
  ['closeBarcodeModal', 'btnCancelBarcode'].forEach(function (id) { var b = document.getElementById(id); if (b) b.addEventListener('click', closeBarcodeModal); });
  var confirmBtn = document.getElementById('btnConfirmIssue');
  if (confirmBtn) confirmBtn.addEventListener('click', confirmIssue);
})();

/* ===== مكتب التشغيل ===== */
function renderOperations() {
  if (typeof OperationsDesk === 'undefined') return;
  var summary = OperationsDesk.getSummary();
  var sEl = document.getElementById('opsSummary');
  if (sEl) {
    sEl.innerHTML = [
      { k: 'queue', icon: '📦', label: 'بانتظار الصرف', val: summary.queue, cls: 'raw' },
      { k: 'production', icon: '🏭', label: 'تحت التصنيع', val: summary.production, cls: 'wip' },
      { k: 'ready', icon: '✅', label: 'جاهز للتسليم', val: summary.ready, cls: 'finished' }
    ].map(function (s) {
      return '<div class="bom-stat ' + s.cls + '"><div class="bom-stat-icon">' + s.icon + '</div>' +
        '<div><div class="bom-stat-label">' + s.label + '</div><div class="bom-stat-value">' + s.val + '</div></div></div>';
    }).join('');
  }
  var rows = CasesWorkflow.getBucket('in_progress');
  var tbody = document.getElementById('opsTable');
  var badge = document.getElementById('opsBadge');
  if (badge) badge.textContent = rows.length;
  if (!tbody) return;
  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="6" class="empty-cell">لا توجد أوامر تشغيل حالية</td></tr>';
    refreshPaginated('opsTable');
    return;
  }
  tbody.innerHTML = rows.map(function (c) {
    var bom = BomInventory.getByCaseId(c.id);
    var tm = CasesWorkflow.getPatientTypeMeta(c.patientType);
    var stageTxt = (bom ? BomInventory.getStageLabel(bom.stage) : '—') + ' / ' + CasesWorkflow.getManufacturingLabel(c.manufacturingStage);
    var action;
    if (bom && bom.stage === 'raw') action = '<button type="button" class="btn-action primary" onclick="handleBomAction(\'' + bom.id + '\',\'release\')">صرف بالباركود</button>';
    else if (bom && bom.stage === 'wip') action = '<button type="button" class="btn-action success" onclick="handleBomAction(\'' + bom.id + '\',\'finish\')">إنهاء التصنيع</button>';
    else action = '<span class="badge done">جاهز للتسليم</span>';
    return '<tr>' +
      '<td><strong>' + (c.workOrderNo || '—') + '</strong></td>' +
      '<td>' + c.patient + '</td>' +
      '<td><span class="patient-type-badge ' + tm.badge + '">' + tm.icon + ' ' + tm.label + '</span></td>' +
      '<td>' + stageTxt + '</td>' +
      '<td class="bom-items-cell">' + (bom ? BomInventory.renderItemsList(bom.items, false) : '—') + '</td>' +
      '<td class="col-actions">' + action + '</td></tr>';
  }).join('');
  refreshPaginated('opsTable');
}
window.renderOperations = renderOperations;

/* ===== المعدلات — تجارب التركيب ===== */
var fittingRecordsMap = {};

function loadFittingRecords() {
  return fittingRecordsMap;
}

function saveFittingRecords(map) {
  fittingRecordsMap = map || {};
}

function getFittingRecord(caseId) {
  var map = loadFittingRecords();
  return map[caseId] || { trial1: '', trial2: '', notes: '', status: 'pending' };
}

function setFittingRecord(caseId, patch) {
  var map = loadFittingRecords();
  map[caseId] = Object.assign({}, getFittingRecord(caseId), patch);
  saveFittingRecords(map);
  return map[caseId];
}

function getAdjustmentCases() {
  if (typeof CasesWorkflow === 'undefined') return [];
  return CasesWorkflow.getAll().filter(function (c) {
    return c.stageKey === 'manufacturing' &&
      ['fitting', 'quality', 'workshop', 'assembly', 'finishing'].indexOf(c.manufacturingStage) !== -1;
  });
}

function renderAdjustments() {
  var tbody = document.getElementById('adjustmentsTable');
  if (!tbody) return;
  var rows = getAdjustmentCases();
  var badge = document.getElementById('adjBadge');
  if (badge) badge.textContent = rows.length;
  var sumEl = document.getElementById('adjSummary');
  if (sumEl) {
    var t1 = rows.filter(function (c) { return getFittingRecord(c.id).trial1; }).length;
    var t2 = rows.filter(function (c) { return getFittingRecord(c.id).trial2; }).length;
    sumEl.innerHTML = [
      { cls: 'wip', icon: '📏', label: 'حالات نشطة', val: rows.length },
      { cls: 'raw', icon: '1️⃣', label: 'تجربة أولى', val: t1 },
      { cls: 'finished', icon: '2️⃣', label: 'تجربة ثانية', val: t2 }
    ].map(function (s) {
      return '<div class="bom-stat ' + s.cls + '"><div class="bom-stat-icon">' + s.icon + '</div>' +
        '<div><div class="bom-stat-label">' + s.label + '</div><div class="bom-stat-value">' + s.val + '</div></div></div>';
    }).join('');
  }
  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="7" class="empty-cell">لا توجد حالات للمعدلات حالياً</td></tr>';
    refreshPaginated('adjustmentsTable');
    return;
  }
  tbody.innerHTML = rows.map(function (c) {
    var rec = getFittingRecord(c.id);
    var stageTxt = CasesWorkflow.getManufacturingLabel(c.manufacturingStage);
    var statusBadge = rec.trial2 ? 'done' : rec.trial1 ? 'progress' : 'waiting';
    var statusLabel = rec.trial2 ? 'مكتمل' : rec.trial1 ? 'تجربة 1' : 'بانتظار';
    return '<tr>' +
      '<td><strong>' + (c.workOrderNo || c.orderRef) + '</strong></td>' +
      '<td>' + c.patient + '</td>' +
      '<td>' + stageTxt + '</td>' +
      '<td>' + (rec.trial1 || '—') + '</td>' +
      '<td>' + (rec.trial2 || '—') + '</td>' +
      '<td style="max-width:180px;font-size:13px;color:var(--text-muted);">' + (rec.notes || '—') + '</td>' +
      '<td class="col-actions"><button type="button" class="btn-action primary" onclick="openFittingModal(\'' + c.id + '\')">تسجيل تجربة</button> ' +
      '<span class="badge ' + statusBadge + '">' + statusLabel + '</span></td></tr>';
  }).join('');
  refreshPaginated('adjustmentsTable');
}
window.renderAdjustments = renderAdjustments;

var fittingModalCaseId = null;

function openFittingModal(caseId) {
  fittingModalCaseId = caseId;
  var c = CasesWorkflow.getById(caseId);
  if (!c) return;
  var modal = document.getElementById('fittingModal');
  if (!modal) return;
  var rec = getFittingRecord(caseId);
  document.getElementById('fittingModalTitle').textContent = '📏 ' + c.patient + ' — ' + (c.workOrderNo || c.orderRef);
  document.getElementById('fittingTrial1').value = rec.trial1 || '';
  document.getElementById('fittingTrial2').value = rec.trial2 || '';
  document.getElementById('fittingNotes').value = rec.notes || '';
  modal.classList.add('visible');
}
window.openFittingModal = openFittingModal;

function closeFittingModal() {
  var modal = document.getElementById('fittingModal');
  if (modal) modal.classList.remove('visible');
  fittingModalCaseId = null;
}

function validateModalFields(ids) {
  if (!window.DashboardValidation) return true;
  for (var i = 0; i < ids.length; i++) {
    var el = document.getElementById(ids[i]);
    if (el && !DashboardValidation.isFieldValid(el)) return false;
  }
  return true;
}

function confirmFittingSave() {
  if (!validateModalFields(['fittingTrial1', 'fittingTrial2', 'fittingNotes'])) return;
  if (!fittingModalCaseId) return;
  var t1 = document.getElementById('fittingTrial1').value.trim();
  var t2 = document.getElementById('fittingTrial2').value.trim();
  var notes = document.getElementById('fittingNotes').value.trim();
  setFittingRecord(fittingModalCaseId, {
    trial1: t1,
    trial2: t2,
    notes: notes,
    status: t2 ? 'completed' : t1 ? 'trial1' : 'pending'
  });
  if (t2 && typeof CasesWorkflow !== 'undefined' && CasesWorkflow.setManufacturingStage) {
    CasesWorkflow.setManufacturingStage(fittingModalCaseId, 'quality');
  } else if (t1 && typeof CasesWorkflow !== 'undefined' && CasesWorkflow.setManufacturingStage) {
    CasesWorkflow.setManufacturingStage(fittingModalCaseId, 'fitting');
  }
  closeFittingModal();
  renderAdjustments();
  renderStockAnalytics();
  showToast('✅ تم حفظ بيانات المعد');
}

(function bindFitting() {
  ['closeFittingModal', 'btnCancelFitting'].forEach(function (id) {
    var b = document.getElementById(id);
    if (b) b.addEventListener('click', closeFittingModal);
  });
  var saveBtn = document.getElementById('btnSaveFitting');
  if (saveBtn) saveBtn.addEventListener('click', confirmFittingSave);
})();

/* ===== حركة وارد + WAC ===== */
function openReceiveModal() {
  var sel = document.getElementById('rcvItem');
  if (!sel) return;
  if (!inventory.length) {
    showToast('⚠️ لا توجد أصناف ً');
    return;
  }
  sel.innerHTML = inventory.map(function (it) {
    return '<option value="' + it.id + '">' + it.name + ' (' + it.code + ') — رصيد: ' + it.qty + '</option>';
  }).join('');
  var supSel = document.getElementById('rcvSupplier');
  if (supSel) {
    var supplierOptions = '<option value="">— اختر المورد —</option>' + inventorySuppliers.map(function (s) {
      return '<option value="' + s.id + '">' + s.name + '</option>';
    }).join('');
    supSel.innerHTML = supplierOptions;
  }
  var dateEl = document.getElementById('rcvDate');
  if (dateEl && !dateEl.value) dateEl.value = new Date().toISOString().slice(0, 10);
  updateWacPreview();
  document.getElementById('receiveModal').classList.add('visible');
}
function closeReceiveModal() { document.getElementById('receiveModal').classList.remove('visible'); }
function updateWacPreview() {
  var itemId = parseInt(document.getElementById('rcvItem').value, 10);
  var item = inventory.find(function (i) { return i.id === itemId; });
  if (!item) { document.getElementById('rcvWacPreview').textContent = '—'; return; }
  var qty = parseInt(document.getElementById('rcvQty').value, 10) || 0;
  document.getElementById('rcvWacPreview').innerHTML =
    'الرصيد الحالي: <strong>' + item.qty + '</strong>' +
    (qty ? ' → بعد الاستلام: <strong>' + (item.qty + qty) + '</strong>' : '');
}
function confirmReceive() {
  if (!validateModalFields(['rcvItem', 'rcvQty', 'rcvAmount', 'rcvSupplier', 'rcvInvoice', 'rcvDate'])) return;
  var payload = {
    stock_item_id: parseInt(document.getElementById('rcvItem').value, 10),
    qty: parseInt(document.getElementById('rcvQty').value, 10),
    unit_price: parseFloat(document.getElementById('rcvAmount').value),
    supplier_id: parseInt(document.getElementById('rcvSupplier').value, 10),
    invoice_no: document.getElementById('rcvInvoice').value.trim(),
    moved_at: document.getElementById('rcvDate').value
  };
  fetch('/technical/inventory/receive', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': csrfToken()
    },
    body: JSON.stringify(payload)
  }).then(function (response) {
    return response.json().then(function (body) {
      if (!response.ok) throw new Error((body && body.message) || 'receive_failed');
      return body;
    });
  }).then(function (body) {
    closeReceiveModal();
    if (body.item) {
      window.__INVENTORY_ITEMS = (window.__INVENTORY_ITEMS || []).map(function (row) {
        return row.id === body.item.id ? body.item : row;
      });
      if (!window.__INVENTORY_ITEMS.some(function (row) { return row.id === body.item.id; })) {
        window.__INVENTORY_ITEMS.push(body.item);
      }
    }
    return fetchInventoryFromServer();
  }).then(function () {
    renderInventory();
    showToast('✅ تم استلام الوارد بنجاح');
  }).catch(function (err) {
    showToast('⚠️ ' + (err.message || 'تعذّر الاستلام — تحقق من البيانات'));
  });
}
(function bindReceive() {
  var btn = document.getElementById('btnReceiveStock');
  if (btn) btn.addEventListener('click', openReceiveModal);
  ['closeReceiveModal', 'btnCancelReceive'].forEach(function (id) { var b = document.getElementById(id); if (b) b.addEventListener('click', closeReceiveModal); });
  var conf = document.getElementById('btnConfirmReceive');
  if (conf) conf.addEventListener('click', confirmReceive);
  ['rcvItem', 'rcvQty', 'rcvAmount'].forEach(function (id) { var e = document.getElementById(id); if (e) e.addEventListener('input', updateWacPreview); if (e) e.addEventListener('change', updateWacPreview); });
})();

/* ===== إذن ارتجاع (legacy stub — صفحة returns تستخدم returns-dashboard.js) ===== */
var returnScanState = { returnId: null };

function renderReturnsSection() {
  if (document.body.dataset.activePage === 'returns') return;
  if (typeof InventoryReturns === 'undefined') return;
  var summary = InventoryReturns.getSummary();
  var sumEl = document.getElementById('returnsSummary');
  if (sumEl) {
    sumEl.innerHTML = [
      { key: 'authorized', label: 'مصرّح', icon: '📋' },
      { key: 'partial', label: 'جزئي', icon: '⏳' },
      { key: 'completed', label: 'مكتمل', icon: '✅' }
    ].map(function (s) {
      return '<div class="bom-stat ' + s.key + '"><div class="bom-stat-icon">' + s.icon + '</div>' +
        '<div><div class="bom-stat-label">' + s.label + '</div>' +
        '<div class="bom-stat-value">' + (summary[s.key] || 0) + '</div></div></div>';
    }).join('');
  }
  var badge = document.getElementById('returnsBadge');
  if (badge) badge.textContent = summary.total + ' إذن';

  var tbody = document.getElementById('returnsTable');
  if (!tbody) return;
  var notes = InventoryReturns.getAll();
  if (!notes.length) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text-muted);">لا توجد أذونات ارتجاع</td></tr>';
    refreshPaginated('returnsTable');
    return;
  }
  tbody.innerHTML = notes.map(function (n) {
    var linesTxt = (n.lines || []).map(function (ln) {
      return (ln.name || ln.code) + ' ' + (ln.qtyReturned || 0) + '/' + (ln.qtyRequested || 0);
    }).join('<br>');
    var statusCls = n.status === 'completed' ? 'done' : (n.status === 'partial' ? 'progress' : 'waiting');
    var action = n.status === 'completed'
      ? '<span class="badge done">مكتمل</span>'
      : '<button type="button" class="btn-action" onclick="openReturnScan(\'' + n.id + '\')">مسح باركود</button>';
    return '<tr>' +
      '<td><strong>' + n.id + '</strong></td>' +
      '<td>' + (n.workOrderNo || '—') + '</td>' +
      '<td>' + (n.patient || '—') + '</td>' +
      '<td class="bom-items-cell">' + linesTxt + '</td>' +
      '<td><span class="badge ' + statusCls + '">' + InventoryReturns.statusLabel(n.status) + '</span></td>' +
      '<td class="col-actions">' + action + '</td></tr>';
  }).join('');
  refreshPaginated('returnsTable');
}
window.renderReturnsSection = renderReturnsSection;

function openReturnCreateModal() {
  var boms = InventoryReturns.getEligibleBoms();
  var sel = document.getElementById('returnBomSelect');
  if (!boms.length) {
    showToast('⚠️ لا توجد BOM في «مخزن إنتاج»');
    return;
  }
  sel.innerHTML = boms.map(function (b) {
    return '<option value="' + b.id + '">' + b.id + ' — ' + b.patient + ' (' + b.orderRef + ')</option>';
  }).join('');
  renderReturnLinesPicker();
  document.getElementById('returnReason').value = '';
  document.getElementById('returnCreateModal').classList.add('visible');
}

function renderReturnLinesPicker() {
  var bomId = document.getElementById('returnBomSelect').value;
  var bom = BomInventory.getById(bomId);
  var el = document.getElementById('returnLinesPicker');
  if (!bom || !el) { if (el) el.innerHTML = ''; return; }
  el.innerHTML = '<h4 style="margin:12px 0 8px;font-size:14px;">البنود القابلة للارتجاع:</h4>' +
    (bom.items || []).map(function (it) {
      var max = BomInventory.getReturnableQty(bom, it.code);
      if (max <= 0) return '';
      var bc = StockCatalog.deriveBarcode ? StockCatalog.deriveBarcode(it.code) : ('BC-' + String(it.code).replace(/\D/g, ''));
      return '<label class="return-line-row">' +
        '<input type="checkbox" class="return-line-chk" data-code="' + it.code + '" data-name="' + (it.name || it.code) + '" data-max="' + max + '" checked>' +
        '<span>' + (it.name || it.code) + ' <code>' + bc + '</code></span>' +
        '<input type="number" class="return-line-qty" data-code="' + it.code + '" min="1" max="' + max + '" value="' + max + '" style="width:70px;margin-right:8px;">' +
        '<span style="color:var(--text-muted);font-size:12px;">/ ' + max + '</span></label>';
    }).filter(Boolean).join('') || '<p style="color:var(--text-muted);">لا بنود قابلة للارتجاع</p>';
}

function closeReturnCreateModal() {
  document.getElementById('returnCreateModal').classList.remove('visible');
}

function confirmReturnCreate() {
  if (!validateModalFields(['returnBomSelect', 'returnReason'])) return;
  var bomId = document.getElementById('returnBomSelect').value;
  var reason = document.getElementById('returnReason').value.trim();
  var lines = [];
  document.querySelectorAll('.return-line-chk:checked').forEach(function (chk) {
    var code = chk.getAttribute('data-code');
    var qtyEl = document.querySelector('.return-line-qty[data-code="' + code + '"]');
    lines.push({
      code: code,
      name: chk.getAttribute('data-name'),
      qtyRequested: qtyEl ? qtyEl.value : 1,
      reason: reason
    });
  });
  var res = InventoryReturns.createReturnNote(bomId, lines, { reason: reason });
  if (!res.ok) {
    showToast('⚠️ ' + (res.reason || res.error || 'تعذّر إنشاء الإذن'));
    return;
  }
  closeReturnCreateModal();
  renderReturnsSection();
  showToast('✅ تم إصدار إذن ارتجاع ' + res.note.id);
}

function openReturnScan(returnId) {
  var note = InventoryReturns.getById(returnId);
  if (!note) return;
  returnScanState = { returnId: returnId };
  var pending = (note.lines || []).filter(function (ln) {
    return (ln.qtyReturned || 0) < (ln.qtyRequested || 0);
  });
  document.getElementById('returnScanInfo').innerHTML =
    '<p><strong>' + note.id + '</strong> — ' + (note.patient || '') + '</p>' +
    '<div class="barcode-required"><h4>بنود متبقية:</h4>' +
    pending.map(function (ln) {
      var bc = StockCatalog.deriveBarcode ? StockCatalog.deriveBarcode(ln.code) : ('BC-' + String(ln.code).replace(/\D/g, ''));
      var rem = (ln.qtyRequested || 0) - (ln.qtyReturned || 0);
      return '<div class="barcode-req-item"><span>' + (ln.name || ln.code) + ' ×' + rem + '</span><code>' + bc + '</code></div>';
    }).join('') + '</div>';
  document.getElementById('returnScanAlarm').style.display = 'none';
  document.getElementById('returnBarcodeInput').value = '';
  document.getElementById('returnQtyInput').value = '1';
  document.getElementById('returnScanModal').classList.add('visible');
}
window.openReturnScan = openReturnScan;

function closeReturnScanModal() {
  document.getElementById('returnScanModal').classList.remove('visible');
}

function triggerReturnAlarm(text) {
  var alarm = document.getElementById('returnScanAlarm');
  document.getElementById('returnScanAlarmText').textContent = text;
  alarm.style.display = 'flex';
  alarm.classList.remove('shake'); void alarm.offsetWidth; alarm.classList.add('shake');
}

function confirmReturnScan() {
  if (!validateModalFields(['returnBarcodeInput', 'returnQtyInput'])) return;
  var code = document.getElementById('returnBarcodeInput').value;
  var qty = document.getElementById('returnQtyInput').value;
  var res = InventoryReturns.processReturnScan(returnScanState.returnId, code, qty);
  if (!res.ok) {
    if (res.alarm) triggerReturnAlarm(res.error || 'باركود غير مطابق');
    else showToast('⚠️ ' + (res.error || 'تعذّر الارتجاع'));
    return;
  }
  document.getElementById('returnScanAlarm').style.display = 'none';
  reloadInventory();
  renderInventory();
  updateInventoryAnalyticsCards();
  renderBomSection();
  renderReturnsSection();
  if (res.completed) {
    closeReturnScanModal();
    showToast('✅ اكتمل الارتجاع — تمت استعادة المخزون');
  } else {
    openReturnScan(returnScanState.returnId);
    showToast('✅ تم ارتجاع ' + res.qtyReturned + ' — متبقي في الإذن');
  }
}

(function bindReturns() {
  if (document.body.dataset.activePage === 'returns') return;
  var btnNew = document.getElementById('btnNewReturn');
  if (btnNew) btnNew.addEventListener('click', openReturnCreateModal);
  var bomSel = document.getElementById('returnBomSelect');
  if (bomSel) bomSel.addEventListener('change', renderReturnLinesPicker);
  ['closeReturnCreateModal', 'btnCancelReturnCreate'].forEach(function (id) {
    var b = document.getElementById(id);
    if (b) b.addEventListener('click', closeReturnCreateModal);
  });
  var confCreate = document.getElementById('btnConfirmReturnCreate');
  if (confCreate) confCreate.addEventListener('click', confirmReturnCreate);
  ['closeReturnScanModal', 'btnCloseReturnScan'].forEach(function (id) {
    var b = document.getElementById(id);
    if (b) b.addEventListener('click', closeReturnScanModal);
  });
  var scanBtn = document.getElementById('btnReturnScan');
  var scanInput = document.getElementById('returnBarcodeInput');
  if (scanBtn) scanBtn.addEventListener('click', confirmReturnScan);
  if (scanInput) scanInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') confirmReturnScan();
  });
})();

function renderBomSummary() {
  var summary = BomInventory.getSummary();
  var el = document.getElementById('bomSummary');
  if (!el) return;
  el.innerHTML = ['raw', 'wip', 'finished'].map(function (key) {
    var s = summary[key];
    return '<div class="bom-stat ' + key + '">' +
      '<div class="bom-stat-icon">' + (key === 'raw' ? '📦' : key === 'wip' ? '🏭' : '✅') + '</div>' +
      '<div><div class="bom-stat-label">' + s.label + '</div>' +
      '<div class="bom-stat-value">' + s.count + ' قائمة</div>' +
      '<div class="bom-stat-sub">' + s.itemCount + ' بند · ' + s.desc + '</div></div></div>';
  }).join('');
  var badge = document.getElementById('bomBadge');
  if (badge) badge.textContent = BomInventory.getAll().length + ' قوائم';
}

function renderBomSection() {
  renderBomSummary();
  var data = getFilteredBom();
  var tbody = document.getElementById('bomTable');
  if (!tbody) return;
  if (!data.length) {
    tbody.innerHTML = '<tr><td colspan="6" class="empty-cell">لا توجد قوائم مواد مطابقة</td></tr>';
  } else {
    tbody.innerHTML = data.map(function (b) {
      var actionBtn = '';
      if (b.stage === 'raw') {
        var check = BomInventory.canReleaseToWip(b);
        actionBtn = '<button type="button" class="btn-action primary" onclick="handleBomAction(\'' + b.id + '\',\'release\')"' +
          (check.ok ? '' : ' disabled title="' + check.reason + '"') + '>صرف بالباركود</button>';
      } else if (b.stage === 'wip') {
        actionBtn = '<button type="button" class="btn-action success" onclick="handleBomAction(\'' + b.id + '\',\'finish\')">إغلاق BOM — تام</button>';
      } else {
        actionBtn = '<span class="badge done">مكتمل</span>';
      }
      return '<tr>' +
        '<td><strong>' + b.id + '</strong></td>' +
        '<td>' + b.patient + '</td>' +
        '<td>' + b.orderRef + '</td>' +
        '<td><span class="stage-badge ' + BomInventory.getStageBadgeClass(b.stage) + '">' + BomInventory.getStageLabel(b.stage) + '</span></td>' +
        '<td class="bom-items-cell">' + BomInventory.renderItemsList(b.items, false) + '</td>' +
        '<td class="col-actions">' + actionBtn + '</td></tr>';
    }).join('');
  }
  var footer = document.getElementById('bomFooter');
  if (footer) footer.textContent = 'عرض ' + data.length + ' من ' + BomInventory.getAll().length + ' قائمة';
  refreshPaginated('bomTable');
}

document.querySelectorAll('[data-bomfilter]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    bomFilter = btn.getAttribute('data-bomfilter');
    document.querySelectorAll('[data-bomfilter]').forEach(function (b) {
      b.classList.toggle('active', b === btn);
    });
    renderBomSection();
  });
});

var bomSearchEl = document.getElementById('bomSearch');
if (bomSearchEl) {
  bomSearchEl.addEventListener('input', function (e) {
    bomSearchTerm = e.target.value.trim();
    renderBomSection();
  });
}

renderStockAnalytics();

reloadInventory();
if (document.getElementById('ordersList')) renderOrders();
if (document.getElementById('inventoryTable')) {
  renderInventory();
}
if (document.getElementById('pricingTable')) renderPricing();
if (document.getElementById('bomTable')) renderBomSection();
if (document.getElementById('opsTable')) renderOperations();
if (document.getElementById('returnsTable') && document.body.dataset.activePage !== 'returns') renderReturnsSection();
if (document.getElementById('adjustmentsTable')) renderAdjustments();

var defaultSection = dashboardDefaults[dashboardMode] || 'inventory';
if (document.getElementById('section-' + defaultSection)) {
  switchSection(defaultSection);
} else {
  var pathPage = window.location.pathname.split('/').filter(Boolean).pop();
  if (pathPage && document.getElementById('section-' + pathPage)) {
    switchSection(pathPage);
  }
}
