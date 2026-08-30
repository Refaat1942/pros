/**
 * Warehouse BOM page — barcode dispense to workshop (Axios + Tailwind alarm).
 */
(function () {
  if (document.body.dataset.activePage !== 'bom') return;

  var csrf = document.querySelector('meta[name="csrf-token"]');
  if (csrf && window.axios) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.getAttribute('content');
    axios.defaults.headers.common['Accept'] = 'application/json';
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
  }

  var state = { bomId: null, items: [], lines: [], blocked: false };
  var dispenseModal = document.getElementById('dispenseModal');

  function $d(id) {
    if (dispenseModal) {
      var scoped = dispenseModal.querySelector('#' + id);
      if (scoped) return scoped;
    }
    return document.getElementById(id);
  }

  function parseQtyNumber(raw) {
    return parseFloat(String(raw || '').trim().replace(',', '.'));
  }

  function formatQty(n) {
    if (!isFinite(n)) return '0';
    return String(n).replace(/\.?0+$/, function (m) { return m === '.' ? '' : m; });
  }

  function findItemForScan(scan) {
    var upper = String(scan || '').trim().toUpperCase();
    if (!upper) return null;
    var found = null;
    state.items.forEach(function (it) {
      if (found) return;
      var code = String(it.stock_item_code || '').toUpperCase();
      var bc = expectedBarcodeFor(it);
      if (upper === code || upper === bc) found = it;
    });
    return found;
  }

  function parseClientQty(raw, item) {
    var s = String(raw || '').trim();
    if (!s) {
      return item.fractional_uom ? null : 1;
    }
    var gram = s.match(/^(\d+(?:\.\d+)?)\s*(?:جرام|gram|g)$/i);
    if (gram && /كيلو|kg|kilo/i.test(String(item.uom || ''))) {
      return parseFloat(gram[1]) / 1000;
    }
    var cm = s.match(/^(\d+(?:\.\d+)?)\s*(?:سم|cm|سنتي)$/i);
    if (cm && /متر|meter|m$/i.test(String(item.uom || ''))) {
      return parseFloat(cm[1]) / 100;
    }
    if (/^\d+(?:\.\d+)?$/.test(s.replace(',', '.'))) {
      return parseQtyNumber(s);
    }
    return null;
  }

  var STAGE_META = {
    raw: { label: '📦 مخزن خام', cls: 'bg-amber-100 text-amber-800 border-amber-200' },
    wip: { label: '🏭 قيد التصنيع', cls: 'bg-cyan-100 text-cyan-800 border-cyan-200' },
    finished: { label: '✅ مخزن تسليم', cls: 'bg-emerald-100 text-emerald-800 border-emerald-200' },
  };

  function $(id) { return document.getElementById(id); }

  function toast(msg, isError) {
    if (window.DashboardToast) {
      window.DashboardToast.show(msg, { id: 'toast', isError: !!isError });
      return;
    }
    var el = $('toast');
    if (!el) {
      alert(msg);
      return;
    }
    el.textContent = msg;
    el.classList.remove('hidden');
    el.classList.add('show');
    setTimeout(function () {
      el.classList.remove('show');
      el.classList.add('hidden');
    }, 5000);
  }

  function esc(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }

  function playAlarm() {
    try {
      var ctx = new (window.AudioContext || window.webkitAudioContext)();
      [880, 660, 880].forEach(function (freq, i) {
        var o = ctx.createOscillator();
        var g = ctx.createGain();
        o.type = 'square';
        o.frequency.value = freq;
        o.connect(g);
        g.connect(ctx.destination);
        g.gain.value = 0.1;
        o.start(ctx.currentTime + i * 0.15);
        o.stop(ctx.currentTime + i * 0.15 + 0.12);
      });
      setTimeout(function () { ctx.close(); }, 600);
    } catch (e) { /* ignore */ }
  }

  function showAlarm(text) {
    state.blocked = true;
    var alarm = $('dispenseAlarm');
    var alarmText = $('dispenseAlarmText');
    if (alarmText) alarmText.textContent = text;
    if (alarm) {
      alarm.classList.remove('hidden');
      alarm.classList.add('animate-pulse');
    }
    var confirm = $('confirmDispense');
    if (confirm) confirm.disabled = true;
    playAlarm();
  }

  function hideAlarm() {
    state.blocked = false;
    var alarm = $('dispenseAlarm');
    if (alarm) alarm.classList.add('hidden');
    var confirm = $('confirmDispense');
    if (confirm) confirm.disabled = false;
  }

  function expectedBarcodeFor(it) {
    return String(it.expected_barcode || ('BC-' + it.stock_item_code)).toUpperCase();
  }

  function normalizeScan(raw) {
    var scan = String(raw || '').trim().toUpperCase();
    if (!scan) return '';
    var match = null;
    state.items.forEach(function (it) {
      if (match) return;
      var code = String(it.stock_item_code || '').toUpperCase();
      var bc = expectedBarcodeFor(it);
      if (scan === code || scan === bc) {
        match = code;
      }
    });
    return match || scan;
  }

  function sanitizeScanInput(raw) {
    var s = String(raw || '').replace(/[\x00-\x1F\x7F]/g, '').trim();
    s = s.replace(/^[^A-Za-z0-9]+/, '').replace(/[^A-Za-z0-9\-_]+$/, '');
    return s.trim().toUpperCase();
  }

  function expectedCounts() {
    var counts = {};
    state.items.forEach(function (it) {
      var code = String(it.stock_item_code || '').toUpperCase();
      counts[code] = (counts[code] || 0) + (parseQtyNumber(it.qty) || 0);
    });
    return counts;
  }

  function expectedTotal() {
    return state.items.reduce(function (sum, it) { return sum + (parseQtyNumber(it.qty) || 0); }, 0);
  }

  function lineCounts() {
    var counts = {};
    state.lines.forEach(function (line) {
      counts[line.code] = (counts[line.code] || 0) + (line.qty || 0);
    });
    return counts;
  }

  function dispensedTotal() {
    return state.lines.reduce(function (sum, line) { return sum + (line.qty || 0); }, 0);
  }

  function renderRequired() {
    var el = $('dispenseRequired');
    if (!el) return;
    var scanned = lineCounts();
    el.innerHTML = '<p class="font-bold text-slate-800 mb-3 text-base">أكواد مطلوبة (' + state.items.length + ' صنف · ' + formatQty(expectedTotal()) + '):</p>' +
      state.items.map(function (it) {
        var code = String(it.stock_item_code || '').toUpperCase();
        var bc = expectedBarcodeFor(it);
        var required = parseQtyNumber(it.qty) || 0;
        var done = scanned[code] || 0;
        var complete = done >= required && required > 0 && Math.abs(done - required) < 0.0001;
        var statusLabel = complete
          ? '✓ تم'
          : (done > 0 ? formatQty(done) + ' / ' + formatQty(required) : '—');
        var rowCls = complete
          ? 'bg-emerald-50 border-emerald-200'
          : (done > 0 ? 'bg-amber-50 border-amber-200' : 'bg-white border-slate-100');
        return '<div class="flex justify-between items-center gap-3 py-2 px-3 rounded-lg border ' + rowCls + ' mb-1.5 last:mb-0">' +
          '<div class="flex items-center gap-2 min-w-0">' +
          '<span class="shrink-0 w-8 h-8 rounded-full inline-flex items-center justify-center text-sm font-black ' +
          (complete ? 'bg-emerald-600 text-white' : (done > 0 ? 'bg-amber-500 text-white' : 'bg-slate-200 text-slate-500')) + '">' +
          (complete ? '✓' : (done > 0 ? done : '○')) + '</span>' +
          '<span class="truncate">' + esc(it.name || it.stock_item_code) + ' ×' + it.qty + ' ' + esc(it.uom || 'قطعة') + '</span>' +
          '</div>' +
          '<div class="text-left shrink-0">' +
          '<code class="font-mono text-sm bg-white px-2 py-1 rounded border block">' + esc(code) + '</code>' +
          '<span class="text-[10px] text-slate-400 block text-center mt-0.5">' + esc(bc) + '</span>' +
          '<span class="text-xs font-bold mt-1 block text-center ' + (complete ? 'text-emerald-700' : 'text-slate-500') + '">' + statusLabel + '</span>' +
          '</div></div>';
      }).join('');
    renderScanProgress();
  }

  function renderScanProgress() {
    var total = expectedTotal();
    var done = dispensedTotal();
    var label = $d('dispenseScanProgressLabel');
    var bar = $d('dispenseScanProgressBar');
    if (label) label.textContent = formatQty(done) + ' / ' + formatQty(total);
    if (bar) {
      var pct = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;
      bar.style.width = pct + '%';
      var complete = total > 0 && Math.abs(done - total) < 0.0001;
      bar.className = 'h-full transition-all duration-200 ' + (complete ? 'bg-emerald-500' : 'bg-cyan-500');
    }
  }

  function revalidateAlarm() {
    var counts = expectedCounts();
    var seen = {};
    var bad = null;
    var over = null;
    for (var i = 0; i < state.lines.length; i++) {
      var code = state.lines[i].code;
      if (!(code in counts)) { bad = code; break; }
      seen[code] = (seen[code] || 0) + (state.lines[i].qty || 0);
      if (seen[code] > counts[code] + 0.0001) { over = code; break; }
    }
    if (bad) {
      showAlarm('كود الصنف غير مطابق لأمر التشغيل: ' + bad + ' — تم إيقاف الصرف!');
    } else if (over) {
      showAlarm('كمية الصنف ' + over + ' تتجاوز المطلوب — تم إيقاف الصرف!');
    } else {
      hideAlarm();
    }
  }

  function renderScanned() {
    var el = $d('dispenseScannedList');
    if (!el) return;
    if (!state.lines.length) {
      el.innerHTML = '<span class="text-slate-400 text-sm">لم يُمسح أي باركود بعد.</span>';
      renderScanProgress();
      renderRequired();
      return;
    }
    var counts = expectedCounts();
    var seen = {};
    el.innerHTML = state.lines.map(function (line, idx) {
      seen[line.code] = (seen[line.code] || 0) + (line.qty || 0);
      var ok = (line.code in counts) && seen[line.code] <= counts[line.code] + 0.0001;
      return '<span class="inline-flex items-center gap-1 rounded-full pl-3 pr-1 py-1 text-xs font-bold ' +
        (ok ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800') + '">' +
        (ok ? '✓' : '✗') + ' ' + esc(line.code) + ' · ' + esc(line.label) +
        '<button type="button" class="btn-remove-scan ml-0.5 rounded-full w-5 h-5 inline-flex items-center justify-center ' +
        (ok ? 'hover:bg-emerald-200' : 'hover:bg-red-200') + ' text-current leading-none" ' +
        'data-scan-idx="' + idx + '" title="حذف المسح" aria-label="حذف ' + esc(line.code) + '">×</button></span>';
    }).join('');
    renderScanProgress();
    renderRequired();
  }

  function removeScan(index) {
    if (index < 0 || index >= state.lines.length) return;
    state.lines.splice(index, 1);
    revalidateAlarm();
    renderScanned();
    clearBarcodeInputError();
    if ($d('dispenseBarcodeInput')) $d('dispenseBarcodeInput').focus();
  }

  function clearBarcodeInputError() {
    var input = $d('dispenseBarcodeInput');
    if (!input) return;
    input.classList.remove('v-invalid');
    input.removeAttribute('aria-invalid');
    var wrap = input.parentElement;
    if (!wrap) return;
    var msg = wrap.querySelector('.v-error-msg');
    if (msg) msg.remove();
  }

  function showBarcodeInputError(message) {
    var input = $d('dispenseBarcodeInput');
    if (!input) return;
    input.classList.add('v-invalid');
    input.setAttribute('aria-invalid', 'true');
    var wrap = input.parentElement;
    if (!wrap) return;
    var msg = wrap.querySelector('.v-error-msg');
    if (!msg) {
      msg = document.createElement('div');
      msg.className = 'v-error-msg';
      msg.setAttribute('role', 'alert');
      wrap.appendChild(msg);
    }
    msg.textContent = message;
    input.focus();
  }

  function isValidBarcode(code) {
    return /^[A-Za-z0-9\-_]{1,100}$/.test(String(code || '').trim());
  }

  function clearQtyInputError() {
    var input = $('dispenseQtyInput');
    if (!input) return;
    input.classList.remove('v-invalid');
    input.removeAttribute('aria-invalid');
    var wrap = input.parentElement;
    if (!wrap) return;
    var msg = wrap.querySelector('.v-error-msg');
    if (msg) msg.remove();
  }

  function showQtyInputError(message) {
    var input = $('dispenseQtyInput');
    if (!input) return;
    input.classList.add('v-invalid');
    input.setAttribute('aria-invalid', 'true');
    var wrap = input.parentElement;
    if (!wrap) return;
    var msg = wrap.querySelector('.v-error-msg');
    if (!msg) {
      msg = document.createElement('div');
      msg.className = 'v-error-msg';
      msg.setAttribute('role', 'alert');
      wrap.appendChild(msg);
    }
    msg.textContent = message;
    input.focus();
  }

  function openModal(bomId) {
    if (!window.axios) return;
    state = { bomId: bomId, items: [], lines: [], blocked: false };
    hideAlarm();
    clearBarcodeInputError();
    clearQtyInputError();
    renderScanned();
    renderScanProgress();
    if ($d('dispenseBarcodeInput')) $d('dispenseBarcodeInput').value = '';
    if ($('dispenseQtyInput')) $('dispenseQtyInput').value = '';

    axios.get('/technical/bom/' + bomId)
      .then(function (res) {
        state.items = res.data.items || [];
        if (!state.items.length) {
          toast('لا توجد بنود في القائمة', true);
          return;
        }
        var printLink = $('printIssueVoucherLink');
        if (printLink) {
          if (res.data.issue_voucher_print_url) {
            printLink.href = res.data.issue_voucher_print_url;
            printLink.classList.remove('hidden');
          } else {
            printLink.classList.add('hidden');
            printLink.removeAttribute('href');
          }
        }
        renderRequired();
        $('dispenseModal').classList.remove('hidden');
        $d('dispenseBarcodeInput') && $d('dispenseBarcodeInput').focus();
      })
      .catch(function () { toast('تعذّر تحميل قائمة المواد', true); });
  }

  function closeModal() {
    $('dispenseModal') && $('dispenseModal').classList.add('hidden');
    state = { bomId: null, items: [], lines: [], blocked: false };
  }

  function addDispenseLine() {
    var barcodeInput = $d('dispenseBarcodeInput');
    var qtyInput = $('dispenseQtyInput');
    var rawBarcode = sanitizeScanInput(barcodeInput && barcodeInput.value || '');
    var qtyRaw = String(qtyInput && qtyInput.value || '').trim();
    if (!rawBarcode) return;
    if (!isValidBarcode(rawBarcode)) {
      showBarcodeInputError('الباركود غير صالح.');
      return;
    }
    var item = findItemForScan(rawBarcode);
    if (!item) {
      showBarcodeInputError('الصنف غير موجود في قائمة المواد.');
      return;
    }
    if (item.fractional_uom && !qtyRaw) {
      clearBarcodeInputError();
      showQtyInputError('أدخل الكمية — مثلاً 100 جرام أو 0.1');
      return;
    }
    var clientQty = parseClientQty(qtyRaw, item);
    if (item.fractional_uom && (clientQty === null || clientQty <= 0)) {
      showQtyInputError('صيغة الكمية غير مفهومة.');
      return;
    }
    var qty = item.fractional_uom ? clientQty : 1;
    var barcode = expectedBarcodeFor(item);
    if (rawBarcode !== String(item.stock_item_code || '').toUpperCase() && rawBarcode !== barcode) {
      barcode = rawBarcode;
    } else {
      barcode = barcode || rawBarcode;
    }
    clearBarcodeInputError();
    clearQtyInputError();
    state.lines.push({
      code: String(item.stock_item_code || '').toUpperCase(),
      barcode: barcode,
      qty: qty,
      qtyRaw: qtyRaw || (item.fractional_uom ? String(qty) : ''),
      uom: item.uom || 'قطعة',
      label: qtyRaw || (item.fractional_uom ? formatQty(qty) + ' ' + (item.uom || '') : '1 قطعة'),
    });
    revalidateAlarm();
    renderScanned();
    if (barcodeInput) { barcodeInput.value = ''; }
    if (qtyInput) { qtyInput.value = ''; }
    if (barcodeInput) barcodeInput.focus();
  }

  function buildDispensePayload() {
    return state.lines.map(function (line) {
      var row = { barcode: line.barcode };
      if (line.qtyRaw) {
        row.qty = line.qtyRaw;
      } else if (line.qty !== 1) {
        row.qty = line.qty;
      }
      return row;
    });
  }

  function confirmDispense() {
    if (state.blocked || !state.bomId || !window.axios) return;

    var input = $d('dispenseBarcodeInput');
    var qtyInput = $('dispenseQtyInput');
    var pending = input ? String(input.value || '').trim().toUpperCase() : '';
    var pendingQty = qtyInput ? String(qtyInput.value || '').trim() : '';
    clearBarcodeInputError();
    clearQtyInputError();

    if (pending || pendingQty) {
      if (pendingQty && !pending) {
        showBarcodeInputError('امسح الباركود أولاً.');
        return;
      }
      if (pending) {
        if (qtyInput && pendingQty) qtyInput.value = pendingQty;
        addDispenseLine();
        if (state.blocked) return;
      }
    }

    if (!state.lines.length) {
      showBarcodeInputError('يجب مسح باركود واحد على الأقل.');
      return;
    }

    var total = expectedTotal();
    var done = dispensedTotal();
    if (Math.abs(done - total) > 0.0001) {
      showAlarm('الكمية المصروفة (' + formatQty(done) + ') لا تطابق المطلوب (' + formatQty(total) + ')');
      return;
    }

    var btn = $('confirmDispense');
    if (btn) btn.disabled = true;

    axios.post('/technical/bom/' + state.bomId + '/dispense', { dispense_lines: buildDispensePayload() })
      .then(function (res) {
        closeModal();
        toast(res.data.message || '✅ تم الصرف بنجاح');
        refreshBoms();
      })
      .catch(function (err) {
        var data = err.response && err.response.data;
        var msg = (data && data.message) || 'تعذّر الصرف';
        if (data && (data.blocked || data.alarm)) {
          showAlarm(msg);
        } else {
          toast(msg, true);
        }
        if (btn) btn.disabled = state.blocked;
      });
  }

  function renderItemsCell(b) {
    var cfg = bomListConfig();
    if (!cfg.enabled) {
      return '<span class="text-xs text-slate-400">غير متاح</span>';
    }
    var count = b.items_count || 0;
    if (!count) return '<span class="text-xs text-slate-400">—</span>';

    var previewHtml = '';
    var preview = b.items_preview || [];
    if (preview.length) {
      previewHtml = '<div class="text-xs text-slate-600 text-right space-y-0.5 mb-1.5 max-w-[220px] ml-auto">' +
        preview.map(function (line) { return '<div class="truncate">' + esc(line) + '</div>'; }).join('') +
        (count > preview.length ? '<div class="text-slate-400">+' + (count - preview.length) + ' أصناف</div>' : '') +
        '</div>';
    }

    return previewHtml +
      '<button type="button" class="btn-view-bom-items text-xs font-bold rounded-lg border border-slate-300 text-slate-700 px-3 py-1.5 hover:bg-slate-50"' +
      ' data-bom-id="' + b.id + '">عرض (' + count + ')</button>';
  }

  function bomListConfig() {
    return {
      enabled: window.__BOM_LIST_ENABLED !== false,
      columns: window.__BOM_LIST_COLUMNS || ['code', 'name', 'qty', 'uom', 'issued_qty', 'returned_qty'],
      labels: window.__BOM_LIST_COLUMN_LABELS || {},
    };
  }

  function bomCellValue(item, col) {
    if (col === 'code') return item.stock_item_code || item.code || '—';
    if (col === 'unit_cost' && item.unit_cost != null) {
      return Number(item.unit_cost).toLocaleString('ar-EG');
    }
    var val = item[col];
    if (val === null || val === undefined || val === '') {
      if (col === 'uom') return 'قطعة';
      if (col === 'issued_qty' || col === 'returned_qty' || col === 'qty') return '0';
      return '—';
    }
    return val;
  }

  function bomCellClass(col) {
    if (col === 'code') return 'px-3 py-2 font-mono text-xs text-slate-500';
    if (col === 'name') return 'px-3 py-2 font-semibold text-slate-800';
    if (col === 'issued_qty') return 'px-3 py-2 text-center font-bold text-emerald-700';
    if (col === 'returned_qty') return 'px-3 py-2 text-center font-bold text-amber-700';
    if (col === 'qty' || col === 'unit_cost') return 'px-3 py-2 text-center font-bold';
    return 'px-3 py-2 text-center text-slate-600';
  }

  function renderBomItemsTable(items) {
    var cfg = bomListConfig();
    var tbody = $('bomItemsBody');
    if (!tbody) return;
    var cols = cfg.columns;
    if (!cfg.enabled) {
      tbody.innerHTML = '<tr><td colspan="' + Math.max(1, cols.length) + '" class="px-3 py-8 text-center text-slate-400">قائمة البنود غير مفعّلة لدورك.</td></tr>';
      return;
    }
    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="' + Math.max(1, cols.length) + '" class="px-3 py-8 text-center text-slate-400">لا توجد بنود.</td></tr>';
      return;
    }
    tbody.innerHTML = items.map(function (item) {
      return '<tr>' + cols.map(function (col) {
        return '<td class="' + bomCellClass(col) + '">' + esc(bomCellValue(item, col)) + '</td>';
      }).join('') + '</tr>';
    }).join('');
  }

  function openBomItemsModal(btn) {
    var modal = $('bomItemsModal');
    var subtitle = $('bomItemsSubtitle');
    if (!modal || !btn) return;

    var bomNo = btn.getAttribute('data-bom-no') || '—';
    var patient = btn.getAttribute('data-patient') || '—';
    var wo = btn.getAttribute('data-work-order') || '—';
    if (subtitle) subtitle.textContent = bomNo + ' · ' + patient + ' · ' + wo;

    var embedded = btn.getAttribute('data-items');
    if (embedded) {
      try {
        renderBomItemsTable(JSON.parse(embedded));
        modal.classList.remove('hidden');
        return;
      } catch (e) { /* fetch below */ }
    }

    var bomId = btn.getAttribute('data-bom-id');
    if (!bomId || !window.axios) return;
    axios.get('/technical/bom/' + bomId)
      .then(function (res) {
        var data = res.data || {};
        if (subtitle && data.bom_no) {
          subtitle.textContent = (data.bom_no || bomNo) + ' · ' + (data.patient_name || patient) + ' · ' +
            ((data.case && data.case.work_order_no) || wo);
        }
        var items = (data.items || []).map(function (it) {
          return it;
        });
        if (data.items_list_enabled === false) {
          toast('قائمة البنود غير مفعّلة لدورك', true);
          return;
        }
        renderBomItemsTable(items);
        modal.classList.remove('hidden');
      })
      .catch(function () { toast('تعذّر تحميل بنود القائمة', true); });
  }

  function closeBomItemsModal() {
    var modal = $('bomItemsModal');
    if (modal) modal.classList.add('hidden');
  }

  function pathBadge(b) {
    var pt = (b.case && b.case.patient_type) || b.patient_type || '';
    var isMil = pt === 'military' || b.path === 'military';
    var cls = isMil ? 'bg-indigo-100 text-indigo-700' : 'bg-emerald-100 text-emerald-700';
    var label = b.path_label || (isMil ? '🪖 عسكري' : '🌐 مدني');
    return '<span class="text-xs font-bold px-2 py-0.5 rounded-lg ' + cls + '">' + esc(label) + '</span>';
  }

  function renderBomRow(b) {
    var meta = STAGE_META[b.stage] || { label: b.stage, cls: 'bg-slate-100' };
    var wo = (b.case && b.case.work_order_no) ? b.case.work_order_no : '—';
    var path = b.path || ((b.case && b.case.patient_type === 'military') ? 'military' : 'civilian');
    var printBtn = b.issue_voucher_print_url
      ? '<a href="' + esc(b.issue_voucher_print_url) + '" target="_blank" rel="noopener" ' +
        'class="btn-print-voucher rounded-xl border border-violet-600 text-violet-800 px-3 py-2 text-xs font-bold hover:bg-violet-50 ml-1">🖨️ طباعة إذن الصرف</a>'
      : '';
    var action = '';
    if (b.stage === 'raw') {
      action = '<button type="button" class="btn-dispense rounded-xl bg-emerald-600 text-white px-4 py-2 text-xs font-bold hover:bg-emerald-700 shadow-sm" data-bom-id="' + b.id + '">📤 صرف للورشة</button>' + printBtn;
    } else if (b.stage === 'wip') {
      action = printBtn + '<span class="text-xs text-slate-500">🏭 تم التحويل للورشة — يُغلق من مكتب التشغيل</span>';
    } else {
      action = '<span class="text-xs text-slate-400">—</span>';
    }
    return '<tr class="bom-row hover:bg-slate-50" data-bom-id="' + b.id + '" data-stage="' + b.stage + '" data-path="' + esc(path) + '" data-search="' +
      esc([b.bom_no, b.patient_name, wo].join(' ')) + '">' +
      '<td class="px-4 py-3 font-mono font-bold">' + esc(b.bom_no) + '</td>' +
      '<td class="px-4 py-3"><div class="flex items-center gap-2 flex-wrap"><span class="font-semibold text-slate-800">' + esc(b.patient_name) + '</span>' + pathBadge(b) + '</div></td>' +
      '<td class="px-4 py-3 font-mono text-xs text-violet-700">' + esc(wo) + '</td>' +
      '<td class="px-4 py-3"><span class="text-xs font-bold px-2 py-1 rounded-lg border ' + meta.cls + '">' + meta.label + '</span></td>' +
      '<td class="px-4 py-3 text-center">' + renderItemsCell(b) + '</td>' +
      '<td class="px-4 py-3">' + action + '</td></tr>';
  }

  function bindBomEvents() {
    document.querySelectorAll('.btn-dispense').forEach(function (btn) {
      btn.addEventListener('click', function () { openModal(btn.getAttribute('data-bom-id')); });
    });
  }

  function refreshBoms() {
    axios.get('/technical/bom/list')
      .then(function (res) {
        var boms = res.data.data || [];
        var tbody = $('bomTableBody');
        if (!tbody) return;
        if (!boms.length) {
          tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-12 text-center text-slate-400">لا توجد قوائم مواد.</td></tr>';
        } else {
          tbody.innerHTML = boms.map(renderBomRow).join('');
          bindBomEvents();
          applyFilters();
        }
        if (window.TablePagination) TablePagination.refreshById('bomTableBody');
      })
      .catch(function () { toast('تعذّر تحديث القائمة', true); });
  }

  var activeFilter = 'all';

  function applyFilters() {
    var q = ($('bomSearch') && $('bomSearch').value || '').trim().toLowerCase();
    document.querySelectorAll('.bom-row').forEach(function (row) {
      var stage = row.getAttribute('data-stage');
      var hay = (row.getAttribute('data-search') || '').toLowerCase();
      var stageOk = activeFilter === 'all' || stage === activeFilter;
      var searchOk = !q || hay.indexOf(q) !== -1;
      row.style.display = stageOk && searchOk ? '' : 'none';
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    bindBomEvents();

    var tableBody = $('bomTableBody');
    if (tableBody) {
      tableBody.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-view-bom-items');
        if (btn) openBomItemsModal(btn);
      });
    }

    $('closeBomItemsModal') && $('closeBomItemsModal').addEventListener('click', closeBomItemsModal);
    $('bomItemsModal') && $('bomItemsModal').addEventListener('click', closeBomItemsModal);

    $('btnRefreshBoms') && $('btnRefreshBoms').addEventListener('click', refreshBoms);
    $('bomSearch') && $('bomSearch').addEventListener('input', applyFilters);

    document.querySelectorAll('.bom-filter').forEach(function (btn) {
      btn.addEventListener('click', function () {
        activeFilter = btn.getAttribute('data-filter');
        document.querySelectorAll('.bom-filter').forEach(function (b) {
          b.classList.remove('active', 'bg-slate-800', 'text-white');
        });
        btn.classList.add('active', 'bg-slate-800', 'text-white');
        applyFilters();
      });
    });

    $('closeDispenseModal') && $('closeDispenseModal').addEventListener('click', closeModal);
    $('cancelDispense') && $('cancelDispense').addEventListener('click', closeModal);
    $('dispenseBackdrop') && $('dispenseBackdrop').addEventListener('click', closeModal);

    var barcodeInput = $d('dispenseBarcodeInput');
    if (barcodeInput) {
      var scanDebounce = null;
      barcodeInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          var qtyInput = $('dispenseQtyInput');
          var item = findItemForScan(e.target.value);
          if (item && item.fractional_uom && qtyInput && !String(qtyInput.value || '').trim()) {
            qtyInput.focus();
            return;
          }
          addDispenseLine();
        }
      });
      barcodeInput.addEventListener('input', function () {
        if (scanDebounce) clearTimeout(scanDebounce);
        var val = barcodeInput.value;
        if (!val || val.length < 3) return;
        scanDebounce = setTimeout(function () {
          var cleaned = sanitizeScanInput(val);
          if (cleaned.length >= 3 && (cleaned.length >= 6 || /[\r\n\t]/.test(val))) {
            barcodeInput.value = cleaned;
            var qtyInput = $('dispenseQtyInput');
            var item = findItemForScan(cleaned);
            if (item && item.fractional_uom && qtyInput && !String(qtyInput.value || '').trim()) {
              return;
            }
            addDispenseLine();
          }
        }, 150);
      });
      barcodeInput.addEventListener('paste', function (e) {
        var pasted = (e.clipboardData && e.clipboardData.getData('text')) || '';
        if (pasted.trim()) {
          e.preventDefault();
          barcodeInput.value = sanitizeScanInput(pasted);
          var qtyInput = $('dispenseQtyInput');
          var item = findItemForScan(barcodeInput.value);
          if (item && item.fractional_uom && qtyInput && !String(qtyInput.value || '').trim()) {
            qtyInput.focus();
            return;
          }
          addDispenseLine();
        }
      });
    }
    $('dispenseQtyInput') && $('dispenseQtyInput').addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); addDispenseLine(); }
    });
    var scannedList = $d('dispenseScannedList');
    if (scannedList) {
      scannedList.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-remove-scan');
        if (!btn) return;
        removeScan(parseInt(btn.getAttribute('data-scan-idx'), 10));
      });
    }
    $('confirmDispense') && $('confirmDispense').addEventListener('click', confirmDispense);
  });
})();
