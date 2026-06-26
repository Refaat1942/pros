/**
 * Warehouse returns inbox — confirm receipt from operations (barcode scan).
 */
(function () {
  if (document.body.dataset.activePage !== 'returns') return;
  if (!document.getElementById('returnScanModal')) return;

  var csrf = document.querySelector('meta[name="csrf-token"]');
  if (csrf && window.axios) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.getAttribute('content');
    axios.defaults.headers.common['Accept'] = 'application/json';
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
  }

  var LIST_URL = '/technical/returns/list?inbox=1';

  var notesCache = [];
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
    if (status === 'completed') return 'تم الاستلام';
    if (status === 'partial') return 'استلام جزئي';
    if (status === 'authorized') return 'بانتظار الاستلام';
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
        { key: 'authorized', label: 'بانتظار الاستلام', icon: '📥' },
        { key: 'partial', label: 'استلام جزئي', icon: '⏳' },
        { key: 'completed', label: 'تم الاستلام', icon: '✅' },
      ].map(function (s) {
        return '<div class="bom-stat ' + s.key + '"><div class="bom-stat-icon">' + s.icon + '</div>' +
          '<div><div class="bom-stat-label">' + s.label + '</div>' +
          '<div class="bom-stat-value">' + (counts[s.key] || 0) + '</div></div></div>';
      }).join('');
    }

    var badge = $('returnsBadge');
    if (badge) badge.textContent = notes.length + ' طلب';
  }

  function renderRow(n) {
    var linesTxt = (n.lines || []).map(function (ln) {
      return esc(ln.name || ln.stock_item_code) + ' ' + (ln.qty_returned || 0) + '/' + (ln.qty_requested || 0);
    }).join('<br>');

    var action = n.status === 'completed'
      ? '<span class="badge done">تم الاستلام</span>'
      : '<button type="button" class="btn-action success btn-return-scan" data-note-id="' + n.id + '">✓ تأكيد الاستلام</button>';

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
          tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text-muted);">لا توجد طلبات ارتجاع بانتظار الاستلام</td></tr>';
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

  function validateModalFields(ids) {
    if (!window.DashboardValidation) return true;
    for (var i = 0; i < ids.length; i++) {
      var el = $(ids[i]);
      if (el && !DashboardValidation.isFieldValid(el)) return false;
    }
    return true;
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
        '<div class="barcode-required"><h4>بنود متبقية للاستلام:</h4>' +
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
      triggerReturnAlarm('باركود غير مطابق لبنود الطلب!');
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
        toast(completed ? '✅ تم تأكيد استلام جميع المواد' : '✅ تم استلام ' + qty + ' — متبقي في الطلب');
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
        var msg = (err.response && err.response.data && err.response.data.message) || 'تعذّر تأكيد الاستلام';
        if (msg.indexOf('باركود') !== -1) triggerReturnAlarm(msg);
        else toast('⚠️ ' + msg, true);
      })
      .finally(function () {
        if (btn) btn.disabled = false;
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var btnRefresh = $('btnRefreshReturns');
    if (btnRefresh) btnRefresh.addEventListener('click', refreshList);

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
