/**
 * Operations returns — request material return to warehouse (workshop → store).
 */
(function () {
  if (document.body.dataset.activePage !== 'returns') return;
  if (!document.getElementById('btnNewReturn')) return;

  var csrf = document.querySelector('meta[name="csrf-token"]');
  if (csrf && window.axios) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.getAttribute('content');
    axios.defaults.headers.common['Accept'] = 'application/json';
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
  }

  var LIST_URL = '/operations/returns/list';
  var CREATE_URL = '/operations/returns/create';
  var STORE_URL = '/operations/returns';

  var notesCache = [];
  var eligibleBoms = [];
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
    if (status === 'completed') return 'تم استلام المخزن';
    if (status === 'partial') return 'استلام جزئي بالمخزن';
    if (status === 'authorized') return 'بانتظار استلام المخزن';
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
        { key: 'authorized', label: 'بانتظار المخزن', icon: '📤' },
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

    return '<tr class="return-row" data-note-id="' + n.id + '">' +
      '<td><strong>' + esc(n.return_no) + '</strong></td>' +
      '<td>' + esc(n.work_order_no || '—') + '</td>' +
      '<td>' + esc(n.patient_name || '—') + '</td>' +
      '<td class="bom-items-cell">' + (linesTxt || '—') + '</td>' +
      '<td><span class="badge ' + statusClass(n.status) + '">' + statusLabel(n.status) + '</span></td></tr>';
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
          tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text-muted);">لا توجد طلبات ارتجاع</td></tr>';
        } else {
          tbody.innerHTML = notesCache.map(renderRow).join('');
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
        toast('✅ تم إرسال طلب الارتجاع ' + (res.data.note && res.data.note.return_no || '') + ' — بانتظار استلام المخزن');
        closeReturnCreateModal();
        refreshList();
      })
      .catch(function (err) {
        var msg = (err.response && err.response.data && err.response.data.message) || 'تعذّر إرسال الطلب';
        toast(msg, true);
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

    refreshList();
  });
})();
