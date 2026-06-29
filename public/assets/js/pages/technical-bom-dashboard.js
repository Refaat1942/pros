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

  var state = { bomId: null, items: [], scanned: [], blocked: false };

  var STAGE_META = {
    raw: { label: '📦 مخزن خام', cls: 'bg-amber-100 text-amber-800 border-amber-200' },
    wip: { label: '🏭 مخزن إنتاج', cls: 'bg-cyan-100 text-cyan-800 border-cyan-200' },
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

  function expectedBarcodes() {
    return state.items.map(function (it) {
      return (it.expected_barcode || ('BC-' + String(it.stock_item_code).replace(/\D/g, ''))).toUpperCase();
    });
  }

  function renderRequired() {
    var el = $('dispenseRequired');
    if (!el) return;
    el.innerHTML = '<p class="font-bold text-slate-700 mb-2">أكواد مطلوبة (' + state.items.length + '):</p>' +
      state.items.map(function (it) {
        var bc = it.expected_barcode || ('BC-' + String(it.stock_item_code).replace(/\D/g, ''));
        return '<div class="flex justify-between items-center py-1 border-b border-slate-100 last:border-0">' +
          '<span>' + esc(it.name || it.stock_item_code) + ' ×' + it.qty + '</span>' +
          '<code class="font-mono text-xs bg-white px-2 py-0.5 rounded border">' + esc(bc) + '</code></div>';
      }).join('');
  }

  function revalidateAlarm() {
    var expected = expectedBarcodes();
    var bad = null;
    for (var i = 0; i < state.scanned.length; i++) {
      if (expected.indexOf(state.scanned[i]) === -1) {
        bad = state.scanned[i];
        break;
      }
    }
    if (bad) {
      showAlarm('باركود غير مطابق لأمر التشغيل: ' + bad + ' — تم إيقاف الصرف!');
    } else {
      hideAlarm();
    }
  }

  function renderScanned() {
    var el = $('scannedList');
    if (!el) return;
    if (!state.scanned.length) {
      el.innerHTML = '<span class="text-slate-400 text-xs">لم يُمسح أي باركود بعد.</span>';
      return;
    }
    var expected = expectedBarcodes();
    el.innerHTML = state.scanned.map(function (code, idx) {
      var ok = expected.indexOf(code) !== -1;
      return '<span class="inline-flex items-center gap-1 rounded-full pl-3 pr-1 py-1 text-xs font-bold ' +
        (ok ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800') + '">' +
        (ok ? '✓' : '✗') + ' ' + esc(code) +
        '<button type="button" class="btn-remove-scan ml-0.5 rounded-full w-5 h-5 inline-flex items-center justify-center ' +
        (ok ? 'hover:bg-emerald-200' : 'hover:bg-red-200') + ' text-current leading-none" ' +
        'data-scan-idx="' + idx + '" title="حذف المسح" aria-label="حذف ' + esc(code) + '">×</button></span>';
    }).join('');
  }

  function removeScan(index) {
    if (index < 0 || index >= state.scanned.length) return;
    state.scanned.splice(index, 1);
    revalidateAlarm();
    renderScanned();
    clearBarcodeInputError();
    if ($('barcodeInput')) $('barcodeInput').focus();
  }

  function clearBarcodeInputError() {
    var input = $('barcodeInput');
    if (!input) return;
    input.classList.remove('v-invalid');
    input.removeAttribute('aria-invalid');
    var wrap = input.parentElement;
    if (!wrap) return;
    var msg = wrap.querySelector('.v-error-msg');
    if (msg) msg.remove();
  }

  function showBarcodeInputError(message) {
    var input = $('barcodeInput');
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

  function openModal(bomId) {
    if (!window.axios) return;
    state = { bomId: bomId, items: [], scanned: [], blocked: false };
    hideAlarm();
    clearBarcodeInputError();
    renderScanned();
    if ($('barcodeInput')) $('barcodeInput').value = '';

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
        $('barcodeInput') && $('barcodeInput').focus();
      })
      .catch(function () { toast('تعذّر تحميل قائمة المواد', true); });
  }

  function closeModal() {
    $('dispenseModal') && $('dispenseModal').classList.add('hidden');
    state = { bomId: null, items: [], scanned: [], blocked: false };
  }

  function addScan(raw) {
    var input = $('barcodeInput');
    var code = String(raw || (input && input.value) || '').trim().toUpperCase();
    if (!code) return;
    if (!isValidBarcode(code)) {
      showBarcodeInputError('الباركود غير صالح.');
      return;
    }
    clearBarcodeInputError();
    state.scanned.push(code);
    revalidateAlarm();
    renderScanned();
    if ($('barcodeInput')) { $('barcodeInput').value = ''; $('barcodeInput').focus(); }
  }

  function confirmDispense() {
    if (state.blocked || !state.bomId || !window.axios) return;

    var input = $('barcodeInput');
    var pending = input ? String(input.value || '').trim().toUpperCase() : '';
    clearBarcodeInputError();

    if (pending) {
      addScan(pending);
      if (state.blocked) return;
    }

    if (!state.scanned.length) {
      showBarcodeInputError('هذا الحقل مطلوب.');
      return;
    }

    if (state.scanned.length !== state.items.length) {
      showAlarm('عدد الباركود (' + state.scanned.length + ') لا يطابق بنود القائمة (' + state.items.length + ')');
      return;
    }

    var btn = $('confirmDispense');
    if (btn) btn.disabled = true;

    axios.post('/technical/bom/' + state.bomId + '/dispense', { scanned_barcodes: state.scanned })
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
    var count = b.items_count || 0;
    if (!count) return '<span class="text-xs text-slate-400">—</span>';
    return '<button type="button" class="btn-view-bom-items text-xs font-bold rounded-lg border border-slate-300 text-slate-700 px-3 py-1.5 hover:bg-slate-50" data-bom-id="' + b.id + '">عرض</button>';
  }

  function renderBomItemsTable(items) {
    var tbody = $('bomItemsBody');
    if (!tbody) return;
    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="px-3 py-8 text-center text-slate-400">لا توجد بنود.</td></tr>';
      return;
    }
    tbody.innerHTML = items.map(function (item) {
      return '<tr>' +
        '<td class="px-3 py-2 font-mono text-xs text-slate-500">' + esc(item.stock_item_code) + '</td>' +
        '<td class="px-3 py-2 font-semibold text-slate-800">' + esc(item.name || item.stock_item_code) + '</td>' +
        '<td class="px-3 py-2 text-center font-bold">' + esc(item.qty) + '</td>' +
        '<td class="px-3 py-2 text-center font-bold text-emerald-700">' + esc(item.issued_qty != null ? item.issued_qty : 0) + '</td>' +
        '<td class="px-3 py-2 text-center font-bold text-amber-700">' + esc(item.returned_qty != null ? item.returned_qty : 0) + '</td>' +
        '</tr>';
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
          return {
            stock_item_code: it.stock_item_code,
            name: it.name,
            qty: it.qty,
            issued_qty: it.issued_qty,
            returned_qty: it.returned_qty,
          };
        });
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
    $('btnAddBarcode') && $('btnAddBarcode').addEventListener('click', function () {
      addScan($('barcodeInput') && $('barcodeInput').value);
    });
    $('barcodeInput') && $('barcodeInput').addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); addScan(e.target.value); }
    });
    $('scannedList') && $('scannedList').addEventListener('click', function (e) {
      var btn = e.target.closest('.btn-remove-scan');
      if (!btn) return;
      removeScan(parseInt(btn.getAttribute('data-scan-idx'), 10));
    });
    $('confirmDispense') && $('confirmDispense').addEventListener('click', confirmDispense);
  });
})();
