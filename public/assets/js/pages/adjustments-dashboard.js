/**
 * Adjustments Desk (المعدلات) — review spec BOM (read-only) + add items, then push to costing.
 */
(function () {
  if (document.body.dataset.dashboard !== 'adjustments') return;
  if (document.body.dataset.activePage !== 'adjustments') return;

  var csrf = document.querySelector('meta[name="csrf-token"]');
  if (csrf && window.axios) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.getAttribute('content');
    axios.defaults.headers.common['Accept'] = 'application/json';
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
  }

  var LIST_URL = '/adjustments/adjustments/list';
  var SHOW_URL = function (id) { return '/adjustments/adjustments/' + id; };
  var ADD_URL = function (id) { return '/adjustments/adjustments/' + id + '/items'; };
  var COMPLETE_URL = function (id) { return '/adjustments/adjustments/' + id + '/complete'; };

  var casesCache = [];
  var catalogCache = [];
  var activeCase = null;
  var refreshInFlight = false;

  function $(id) { return document.getElementById(id); }

  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }

  function apiMessage(err, fallback) {
    var data = err && err.response && err.response.data;
    if (data && data.message) return data.message;
    if (data && data.errors) {
      var first = Object.values(data.errors)[0];
      if (first && first[0]) return first[0];
    }
    return fallback || 'حدث خطأ غير متوقع.';
  }

  function clearFormError() {
    var el = $('adjFormError');
    if (el) { el.textContent = ''; el.style.display = 'none'; }
  }

  function showError(msg) {
    clearFormError();
    var el = $('adjFormError');
    if (el) {
      el.textContent = msg;
      el.style.display = 'block';
    }
    window.alert(msg);
  }

  function toast(msg, isError) {
    if (isError) {
      showError(msg);
      return;
    }
    clearFormError();
    if (window.DashboardToast) {
      window.DashboardToast.show(msg, { id: 'toast', prefix: '✅ ', isError: false });
      return;
    }
    var el = $('toast');
    if (!el) return;
    el.textContent = msg;
    el.className = 'toast show';
    setTimeout(function () { el.classList.remove('show'); }, 4500);
  }

  function bomItemsOf(c) {
    return (c.bom && c.bom.items) || [];
  }

  function renderRow(c) {
    var isMil = c.patient_type === 'military' || c.path === 'military';
    var items = bomItemsOf(c);
    var search = [c.case_no, c.order_ref, c.patient && c.patient.name].join(' ');

    return '<tr class="adj-row" data-case-id="' + c.id + '" data-search="' + esc(search) + '">' +
      '<td><strong>' + esc(c.case_no) + '</strong><div class="text-xs text-muted">' + esc(c.order_ref) + '</div></td>' +
      '<td>' + esc(c.patient && c.patient.name) + '</td>' +
      '<td><span class="patient-type-badge ' + (isMil ? 'military' : 'civilian') + '">' +
        (isMil ? '🪖 عسكري' : '🌐 مدني') + '</span></td>' +
      '<td>' + items.length + '</td>' +
      '<td class="col-actions">' +
        '<button type="button" class="btn-action primary btn-open-adj" data-case-id="' + c.id + '">مراجعة وإضافة</button>' +
      '</td></tr>';
  }

  function updateAnalytics(cases) {
    var total = cases.length;
    var mil = cases.filter(function (c) { return c.patient_type === 'military' || c.path === 'military'; }).length;
    var civ = total - mil;
    var avg = total ? Math.round(cases.reduce(function (s, c) { return s + bomItemsOf(c).length; }, 0) / total) : 0;

    if ($('adjBadge')) $('adjBadge').textContent = total;

    var analytics = $('analytics-adjustments');
    if (analytics) {
      var values = analytics.querySelectorAll('.ck-stat-value');
      if (values.length >= 4) {
        values[0].textContent = total;
        values[1].textContent = mil;
        values[2].textContent = civ;
        values[3].textContent = avg;
      }
    }
  }

  function bindTableEvents() {
    document.querySelectorAll('.btn-open-adj').forEach(function (btn) {
      btn.addEventListener('click', function () { openModal(btn.getAttribute('data-case-id')); });
    });
  }

  function filterSearch() {
    var q = ($('adjSearch') && $('adjSearch').value || '').trim().toLowerCase();
    document.querySelectorAll('.adj-row').forEach(function (row) {
      var hay = (row.getAttribute('data-search') || '').toLowerCase();
      row.style.display = !q || hay.indexOf(q) !== -1 ? '' : 'none';
    });
  }

  function setRefreshBusy(busy) {
    var btn = $('btnRefreshAdj');
    if (!btn) return;
    btn.disabled = busy;
    btn.textContent = busy ? '↻ جاري التحديث...' : '↻ تحديث';
  }

  function refreshList(ev) {
    if (ev && ev.preventDefault) ev.preventDefault();
    if (!window.axios) { toast('تعذّر التحديث — axios غير متاح', true); return; }
    if (refreshInFlight) return;

    refreshInFlight = true;
    setRefreshBusy(true);

    axios.get(LIST_URL)
      .then(function (res) {
        casesCache = res.data.data || [];
        var tbody = $('adjustmentsTable');
        if (!tbody) return;

        if (!casesCache.length) {
          tbody.innerHTML = '<tr><td colspan="5" class="empty-cell">لا توجد حالات بالمعدلات حالياً — تظهر بعد إرسال التوصيف الفني.</td></tr>';
        } else {
          tbody.innerHTML = casesCache.map(renderRow).join('');
          bindTableEvents();
        }

        updateAnalytics(casesCache);
        if (window.TablePagination) TablePagination.refreshById('adjustmentsTable');
        filterSearch();
      })
      .catch(function (err) {
        var msg = (err.response && err.response.data && err.response.data.message) || 'تعذّر تحديث القائمة';
        toast(msg, true);
      })
      .finally(function () {
        refreshInFlight = false;
        setRefreshBusy(false);
      });
  }

  function renderBomItems(items) {
    var tbody = $('adjBomItems');
    if (!tbody) return;
    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="4" class="empty-cell">لا توجد بنود بعد.</td></tr>';
      return;
    }
    tbody.innerHTML = items.map(function (it) {
      var ro = it.read_only || it.source === 'spec';
      return '<tr>' +
        '<td>' + esc(it.stock_item_code) + '</td>' +
        '<td>' + esc(it.name) + '</td>' +
        '<td>' + esc(it.qty) + '</td>' +
        '<td>' + (ro
          ? '<span class="badge">🔒 الفني</span>'
          : '<span class="badge done">معدّلات</span>') + '</td>' +
        '</tr>';
    }).join('');
  }

  function openModal(caseId) {
    if (!window.axios) return;
    axios.get(SHOW_URL(caseId))
      .then(function (res) {
        activeCase = res.data.case;
        var modal = $('adjModal');
        if (!modal) return;

        var title = $('adjModalTitle');
        if (title) {
          title.textContent = '🧩 ' + ((activeCase.patient && activeCase.patient.name) || '—') + ' — ' + activeCase.case_no;
        }

        renderBomItems((activeCase.bom && activeCase.bom.items) || []);

        catalogCache = res.data.stock_catalog || [];
        var datalist = $('adjCatalog');
        if (datalist) {
          datalist.innerHTML = catalogCache.map(function (i) {
            return '<option value="' + esc(i.code) + '">' + esc(i.name) + '</option>';
          }).join('');
        }

        if ($('adjItemCode')) $('adjItemCode').value = '';
        if ($('adjItemName')) $('adjItemName').value = '';
        if ($('adjItemQty')) $('adjItemQty').value = '1';
        clearFormError();

        modal.classList.add('visible');
      })
      .catch(function (err) {
        showError(apiMessage(err, 'تعذّر فتح الحالة'));
      });
  }

  function closeModal() {
    var modal = $('adjModal');
    if (modal) modal.classList.remove('visible');
    activeCase = null;
  }

  function autofillName() {
    var codeEl = $('adjItemCode');
    var nameEl = $('adjItemName');
    if (!codeEl || !nameEl) return;
    var code = codeEl.value.trim().toLowerCase();
    var match = catalogCache.filter(function (i) { return String(i.code).toLowerCase() === code; })[0];
    if (match) nameEl.value = match.name || '';
  }

  function addItem() {
    if (!activeCase || !window.axios) return;
    var code = ($('adjItemCode') && $('adjItemCode').value.trim()) || '';
    var name = ($('adjItemName') && $('adjItemName').value.trim()) || '';
    var qty = parseInt(($('adjItemQty') && $('adjItemQty').value) || '0', 10);

    if (!code || !qty || qty < 1) { showError('أدخل كود الصنف وكمية صحيحة'); return; }

    var btn = $('btnAddAdjItem');
    if (btn) btn.disabled = true;

    axios.post(ADD_URL(activeCase.id), { items: [{ stock_item_code: code, name: name || code, qty: qty }] })
      .then(function (res) {
        clearFormError();
        toast('تمت إضافة البند');
        renderBomItems((res.data.bom && res.data.bom.items) || []);
        if ($('adjItemCode')) $('adjItemCode').value = '';
        if ($('adjItemName')) $('adjItemName').value = '';
        if ($('adjItemQty')) $('adjItemQty').value = '1';
      })
      .catch(function (err) {
        showError(apiMessage(err, 'تعذّر إضافة البند'));
      })
      .finally(function () { if (btn) btn.disabled = false; });
  }

  function completeAdjustments() {
    if (!activeCase || !window.axios) return;
    var btn = $('btnCompleteAdj');
    if (btn) btn.disabled = true;

    axios.post(COMPLETE_URL(activeCase.id), {})
      .then(function () {
        toast('✅ تم إغلاق المعدلات ودفع الحالة للتكاليف');
        closeModal();
        refreshList();
      })
      .catch(function (err) {
        showError(apiMessage(err, 'تعذّر إغلاق المعدلات'));
      })
      .finally(function () { if (btn) btn.disabled = false; });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var refresh = $('btnRefreshAdj');
    if (refresh) refresh.addEventListener('click', refreshList);

    var search = $('adjSearch');
    if (search) search.addEventListener('input', filterSearch);

    var closeBtn = $('closeAdjModal');
    var cancelBtn = $('btnCancelAdj');
    var addBtn = $('btnAddAdjItem');
    var completeBtn = $('btnCompleteAdj');
    var modal = $('adjModal');
    var codeInput = $('adjItemCode');
    if (codeInput) {
      codeInput.addEventListener('change', autofillName);
      codeInput.addEventListener('input', autofillName);
    }

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    if (addBtn) addBtn.addEventListener('click', addItem);
    if (completeBtn) completeBtn.addEventListener('click', completeAdjustments);
    if (modal) {
      modal.addEventListener('click', function (ev) { if (ev.target === modal) closeModal(); });
    }

    refreshList();
  });
})();
