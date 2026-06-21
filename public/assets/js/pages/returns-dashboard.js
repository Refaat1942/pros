/**
 * Warehouse Returns page — return notes linked to WIP BOMs (Axios + DB).
 */
(function () {
  if (document.body.dataset.dashboard !== 'inventory') return;
  if (document.body.dataset.activePage !== 'returns') return;

  var csrf = document.querySelector('meta[name="csrf-token"]');
  if (csrf && window.axios) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.getAttribute('content');
    axios.defaults.headers.common['Accept'] = 'application/json';
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
  }

  var LIST_URL = '/technical/returns/list';
  var CREATE_URL = '/technical/returns/create';
  var STORE_URL = '/technical/returns';

  var notesCache = [];
  var eligibleBoms = [];
  var activeNoteId = null;
  var refreshInFlight = false;

  function $(id) { return document.getElementById(id); }

  function esc(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }

  function toast(msg, isError) {
    if (window.DashboardToast) {
      window.DashboardToast.show(msg, { id: 'toast', prefix: '', isError: isError });
      return;
    }
    var el = $('toast');
    if (!el) return;
    el.textContent = msg;
    el.className = 'toast visible' + (isError ? ' error' : '');
    setTimeout(function () { el.classList.remove('visible'); }, 4500);
  }

  function statusLabel(status) {
    if (status === 'completed') return 'مكتمل';
    if (status === 'partial') return 'جزئي';
    if (status === 'authorized') return 'مصرّح';
    return status || '—';
  }

  function statusClass(status) {
    if (status === 'completed') return 'done';
    if (status === 'partial') return 'progress';
    return 'waiting';
  }

  function deriveBarcode(code) {
    var digits = String(code || '').replace(/\D/g, '');
    return 'BC-' + digits;
  }

  function updateSummary(notes) {
    var counts = { authorized: 0, partial: 0, completed: 0 };
    notes.forEach(function (n) {
      if (counts[n.status] !== undefined) counts[n.status]++;
    });

    var sumEl = $('returnsSummary');
    if (sumEl) {
      sumEl.innerHTML = [
        { key: 'authorized', label: 'مصرّح', icon: '📋' },
        { key: 'partial', label: 'جزئي', icon: '⏳' },
        { key: 'completed', label: 'مكتمل', icon: '✅' },
      ].map(function (s) {
        return '<div class="bom-stat ' + s.key + '"><div class="bom-stat-icon">' + s.icon + '</div>' +
          '<div><div class="bom-stat-label">' + s.label + '</div>' +
          '<div class="bom-stat-value">' + (counts[s.key] || 0) + '</div></div></div>';
      }).join('');
    }

    var badge = $('returnsBadge');
    if (badge) badge.textContent = notes.length + ' إذن';
  }

  function renderRow(n) {
    var linesTxt = (n.lines || []).map(function (ln) {
      return esc(ln.name || ln.stock_item_code) + ' ' + (ln.qty_returned || 0) + '/' + (ln.qty_requested || 0);
    }).join('<br>');

    var action = n.status === 'completed'
      ? '<span class="badge done">مكتمل</span>'
      : '<button type="button" class="btn-action btn-return-scan" data-note-id="' + n.id + '">مسح باركود</button>';

    return '<tr class="return-row" data-note-id="' + n.id + '">' +
      '<td><strong>' + esc(n.return_no) + '</strong></td>' +
      '<td>' + esc(n.work_order_no || '—') + '</td>' +
      '<td>' + esc(n.patient_name || '—') + '</td>' +
      '<td class="bom-items-cell">' + (linesTxt || '—') + '</td>' +
      '<td><span class="badge ' + statusClass(n.status) + '">' + statusLabel(n.status) + '</span></td>' +
      '<td class="col-actions">' + action + '</td></tr>';
  }

  function bindTableEvents() {
    document.querySelectorAll('.btn-return-scan').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openReturnScan(btn.getAttribute('data-note-id'));
      });
    });
  }

  function refreshList() {
    if (!window.axios) {
      toast('تعذّر التحديث — axios غير متاح', true);
      return;
    }
    if (refreshInFlight) return;

    refreshInFlight = true;
    var btn = $('btnRefreshReturns');
    if (btn) { btn.disabled = true; btn.textContent = '↻ جاري التحديث...'; }

    axios.get(LIST_URL)
      .then(function (res) {
        notesCache = res.data.data || [];
        var tbody = $('returnsTable');
        if (!tbody) return;

        if (!notesCache.length) {
          tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text-muted);">لا توجد أذونات ارتجاع</td></tr>';
        } else {
          tbody.innerHTML = notesCache.map(renderRow).join('');
          bindTableEvents();
        }

        updateSummary(notesCache);
        if (window.TablePagination) TablePagination.refreshById('returnsTable');
      })
      .catch(function (err) {
        var msg = (err.response && err.response.data && err.response.data.message) || 'تعذّر تحديث القائمة';
        toast(msg, true);
      })
      .finally(function () {
        refreshInFlight = false;
        if (btn) { btn.disabled = false; btn.textContent = '↻ تحديث'; }
      });
  }

  function loadEligibleBoms() {
    return axios.get(CREATE_URL).then(function (res) {
      eligibleBoms = (res.data.boms || []).filter(function (b) {
        return (b.items || []).length > 0;
      });
      return eligibleBoms;
    });
  }

  function openReturnCreateModal() {
    if (!window.axios) {
      toast('axios غير متاح', true);
      return;
    }

    loadEligibleBoms()
      .then(function (boms) {
        if (!boms.length) {
          toast('⚠️ لا توجد BOM في «تحت التشغيل» ببنود قابلة للارتجاع', true);
          return;
        }

        var sel = $('returnBomSelect');
        if (!sel) return;

        sel.innerHTML = boms.map(function (b) {
          var label = b.bom_no + ' — ' + (b.patient_name || '—') + ' (' + (b.order_ref || '—') + ')';
          return '<option value="' + b.id + '">' + esc(label) + '</option>';
        }).join('');

        renderReturnLinesPicker();
        if ($('returnReason')) $('returnReason').value = '';
        $('returnCreateModal').classList.add('visible');
      })
      .catch(function (err) {
        var msg = (err.response && err.response.data && err.response.data.message) || 'تعذّر تحميل BOM';
        toast(msg, true);
      });
  }

  function updateBomMeta(bom) {
    var meta = $('returnBomMeta');
    if (!meta) return;
    if (!bom) {
      meta.hidden = true;
      meta.innerHTML = '';
      return;
    }
    meta.hidden = false;
    meta.innerHTML = [
      bom.work_order_no ? '<span class="return-bom-meta-item">📋 <strong>أمر التشغيل:</strong> ' + esc(bom.work_order_no) + '</span>' : '',
      bom.patient_name ? '<span class="return-bom-meta-item">👤 <strong>المريض:</strong> ' + esc(bom.patient_name) + '</span>' : '',
      bom.order_ref ? '<span class="return-bom-meta-item">🔗 <strong>الطلب:</strong> ' + esc(bom.order_ref) + '</span>' : '',
    ].filter(Boolean).join('');
  }

  function bindReturnLineCards() {
    document.querySelectorAll('.return-line-card').forEach(function (card) {
      var chk = card.querySelector('.return-line-chk');
      var qty = card.querySelector('.return-line-qty');
      if (!chk) return;

      function syncState() {
        var on = chk.checked;
        card.classList.toggle('is-checked', on);
        card.classList.toggle('is-unchecked', !on);
        if (qty) qty.disabled = !on;
      }

      syncState();
      chk.addEventListener('change', syncState);
      card.addEventListener('click', function (e) {
        if (e.target === chk || e.target.classList.contains('return-line-qty')) return;
        chk.checked = !chk.checked;
        syncState();
      });
      if (qty) {
        qty.addEventListener('click', function (e) { e.stopPropagation(); });
      }
    });
  }

  function setAllReturnLines(checked) {
    document.querySelectorAll('.return-line-chk').forEach(function (chk) {
      chk.checked = checked;
      var card = chk.closest('.return-line-card');
      var qty = card && card.querySelector('.return-line-qty');
      if (card) {
        card.classList.toggle('is-checked', checked);
        card.classList.toggle('is-unchecked', !checked);
      }
      if (qty) qty.disabled = !checked;
    });
  }

  function renderReturnLinesPicker() {
    var bomId = $('returnBomSelect') && $('returnBomSelect').value;
    var bom = eligibleBoms.find(function (b) { return String(b.id) === String(bomId); });
    var el = $('returnLinesPicker');
    if (!el) return;

    updateBomMeta(bom);

    if (!bom || !(bom.items || []).length) {
      el.innerHTML = '<p class="return-lines-empty">' +
        (bom ? 'لا بنود قابلة للارتجاع في هذا BOM' : 'اختر BOM لعرض البنود') +
        '</p>';
      return;
    }

    el.innerHTML = bom.items.map(function (it) {
      var max = it.returnable_qty || 0;
      var bc = it.barcode || deriveBarcode(it.stock_item_code);
      var code = esc(it.stock_item_code);
      return '<div class="return-line-card is-checked" role="group">' +
        '<input type="checkbox" class="return-line-chk" data-code="' + code + '" data-name="' + esc(it.name || it.stock_item_code) + '" checked aria-label="' + esc(it.name || it.stock_item_code) + '">' +
        '<span class="return-line-info">' +
          '<span class="return-line-name">' + esc(it.name || it.stock_item_code) + '</span>' +
          '<span class="return-line-barcode">' + esc(bc) + '</span>' +
        '</span>' +
        '<span class="return-qty-wrap">' +
          '<label>الكمية</label>' +
          '<input type="number" class="return-line-qty" data-code="' + code + '" min="1" max="' + max + '" value="' + max + '">' +
          '<span class="return-qty-max">من ' + max + '</span>' +
        '</span>' +
      '</div>';
    }).join('');

    bindReturnLineCards();
  }

  function closeReturnCreateModal() {
    var modal = $('returnCreateModal');
    if (modal) modal.classList.remove('visible');
  }

  function validateModalFields(ids) {
    if (!window.DashboardValidation) return true;
    for (var i = 0; i < ids.length; i++) {
      var el = $(ids[i]);
      if (el && !DashboardValidation.isFieldValid(el)) return false;
    }
    return true;
  }

  function confirmReturnCreate() {
    if (!validateModalFields(['returnBomSelect', 'returnReason'])) return;

    var bomId = $('returnBomSelect').value;
    var reason = $('returnReason').value.trim();
    var lines = [];

    document.querySelectorAll('.return-line-chk:checked').forEach(function (chk) {
      var code = chk.getAttribute('data-code');
      var qtyEl = document.querySelector('.return-line-qty[data-code="' + code + '"]');
      var qty = qtyEl ? parseInt(qtyEl.value, 10) : 0;
      if (qty > 0) {
        lines.push({
          stock_item_code: code,
          name: chk.getAttribute('data-name'),
          qty: qty,
        });
      }
    });

    if (!lines.length) {
      toast('اختر بنداً واحداً على الأقل', true);
      return;
    }

    var btn = $('btnConfirmReturnCreate');
    if (btn) btn.disabled = true;

    axios.post(STORE_URL, { bom_id: bomId, reason: reason, lines: lines })
      .then(function (res) {
        toast('✅ تم إصدار إذن ارتجاع ' + (res.data.note && res.data.note.return_no || ''));
        closeReturnCreateModal();
        refreshList();
      })
      .catch(function (err) {
        var msg = (err.response && err.response.data && err.response.data.message) || 'تعذّر إنشاء الإذن';
        toast(msg, true);
      })
      .finally(function () {
        if (btn) btn.disabled = false;
      });
  }

  function openReturnScan(noteId) {
    var note = notesCache.find(function (n) { return String(n.id) === String(noteId); });
    if (!note) return;

    activeNoteId = note.id;
    var pending = (note.lines || []).filter(function (ln) {
      return (ln.qty_returned || 0) < (ln.qty_requested || 0);
    });

    var info = $('returnScanInfo');
    if (info) {
      info.innerHTML =
        '<div class="return-scan-note-head">' +
          '<strong>' + esc(note.return_no) + '</strong>' +
          '<span>' + esc(note.patient_name || '') + '</span>' +
        '</div>' +
        '<div class="barcode-required"><h4>بنود متبقية للمسح:</h4>' +
        pending.map(function (ln) {
          var bc = deriveBarcode(ln.stock_item_code);
          var rem = (ln.qty_requested || 0) - (ln.qty_returned || 0);
          return '<div class="barcode-req-item"><span>' + esc(ln.name || ln.stock_item_code) + ' ×' + rem + '</span><code>' + esc(bc) + '</code></div>';
        }).join('') + '</div>';
    }

    if ($('returnScanAlarm')) $('returnScanAlarm').style.display = 'none';
    if ($('returnBarcodeInput')) $('returnBarcodeInput').value = '';
    if ($('returnQtyInput')) $('returnQtyInput').value = '1';
    $('returnScanModal').classList.add('visible');
  }

  function closeReturnScanModal() {
    var modal = $('returnScanModal');
    if (modal) modal.classList.remove('visible');
    activeNoteId = null;
  }

  function triggerReturnAlarm(text) {
    var alarm = $('returnScanAlarm');
    if ($('returnScanAlarmText')) $('returnScanAlarmText').textContent = text;
    if (alarm) {
      alarm.style.display = 'flex';
      alarm.classList.remove('shake');
      void alarm.offsetWidth;
      alarm.classList.add('shake');
    }
  }

  function confirmReturnScan() {
    if (!validateModalFields(['returnBarcodeInput', 'returnQtyInput'])) return;
    if (!activeNoteId) return;

    var note = notesCache.find(function (n) { return String(n.id) === String(activeNoteId); });
    if (!note) return;

    var barcode = $('returnBarcodeInput').value.trim();
    var qty = parseInt($('returnQtyInput').value, 10) || 1;

    var line = (note.lines || []).find(function (ln) {
      var bc = deriveBarcode(ln.stock_item_code);
      return bc === barcode || ln.stock_item_code === barcode;
    });

    if (!line) {
      triggerReturnAlarm('باركود غير مطابق لبنود الإذن!');
      return;
    }

    var remaining = (line.qty_requested || 0) - (line.qty_returned || 0);
    if (qty < 1 || qty > remaining) {
      toast('كمية غير صالحة — المتبقي: ' + remaining, true);
      return;
    }

    var btn = $('btnReturnScan');
    if (btn) btn.disabled = true;

    axios.post('/technical/returns/' + activeNoteId + '/complete', {
      scanned_lines: [{ line_id: line.id, barcode: barcode, qty_returned: qty }],
    })
      .then(function (res) {
        if ($('returnScanAlarm')) $('returnScanAlarm').style.display = 'none';
        var completed = res.data.note && res.data.note.status === 'completed';
        toast(completed ? '✅ اكتمل الارتجاع — تمت استعادة المخزون' : '✅ تم ارتجاع ' + qty + ' — متبقي في الإذن');
        refreshList();
        if (completed) {
          closeReturnScanModal();
        } else {
          var updated = res.data.note;
          if (updated) {
            notesCache = notesCache.map(function (n) { return n.id === updated.id ? updated : n; });
            openReturnScan(activeNoteId);
          }
        }
      })
      .catch(function (err) {
        var msg = (err.response && err.response.data && err.response.data.message) || 'تعذّر الارتجاع';
        if (msg.indexOf('باركود') !== -1) triggerReturnAlarm(msg);
        else toast('⚠️ ' + msg, true);
      })
      .finally(function () {
        if (btn) btn.disabled = false;
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var btnNew = $('btnNewReturn');
    if (btnNew) btnNew.addEventListener('click', openReturnCreateModal);

    var btnRefresh = $('btnRefreshReturns');
    if (btnRefresh) btnRefresh.addEventListener('click', refreshList);

    var bomSel = $('returnBomSelect');
    if (bomSel) bomSel.addEventListener('change', renderReturnLinesPicker);

    var selectAll = $('returnSelectAll');
    if (selectAll) selectAll.addEventListener('click', function () { setAllReturnLines(true); });

    var deselectAll = $('returnDeselectAll');
    if (deselectAll) deselectAll.addEventListener('click', function () { setAllReturnLines(false); });

    ['closeReturnCreateModal', 'btnCancelReturnCreate'].forEach(function (id) {
      var b = $(id);
      if (b) b.addEventListener('click', closeReturnCreateModal);
    });

    var confCreate = $('btnConfirmReturnCreate');
    if (confCreate) confCreate.addEventListener('click', confirmReturnCreate);

    ['closeReturnScanModal', 'btnCloseReturnScan'].forEach(function (id) {
      var b = $(id);
      if (b) b.addEventListener('click', closeReturnScanModal);
    });

    var scanBtn = $('btnReturnScan');
    var scanInput = $('returnBarcodeInput');
    if (scanBtn) scanBtn.addEventListener('click', confirmReturnScan);
    if (scanInput) scanInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') confirmReturnScan();
    });

    refreshList();
  });
})();
