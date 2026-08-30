/**
 * قائمة أصناف تشغيلية — قراءة فقط، بدون أسعار.
 */
(function () {
  var cfg = window.__CATALOG_BROWSE;
  if (!cfg || !cfg.enabled) return;

  var prefix = cfg.prefix || 'catalog';
  var filter = 'all';
  var searchTerm = '';

  function $(id) { return document.getElementById(prefix + id); }

  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;');
  }

  function normalizeItem(raw) {
    var qty = parseInt(raw.qty, 10) || 0;
    var reserved = parseInt(raw.reserved, 10) || 0;
    var available = raw.available != null ? (parseInt(raw.available, 10) || 0) : (qty - reserved);
    var backorder = raw.backorder != null ? (parseInt(raw.backorder, 10) || 0) : Math.max(0, reserved - qty);
    var status = raw.status === 'backorder' || backorder > 0 ? 'backorder' : (raw.status === 'low' ? 'low' : 'ok');
    return Object.assign({}, raw, { available: available, backorder: backorder, status: status });
  }

  var items = (cfg.items || []).map(normalizeItem);
  var columns = cfg.columns || ['code', 'name', 'available'];

  function statusBadge(status) {
    if (status === 'backorder') return '<span class="badge" style="background:#fef3c7;color:#92400e;">طلب توريد</span>';
    if (status === 'low') return '<span class="badge" style="background:#fee2e2;color:#b91c1c;">منخفض</span>';
    return '<span class="badge" style="background:#dcfce7;color:#166534;">متوفر</span>';
  }

  function cellValue(item, key) {
    if (key === 'status') return statusBadge(item.status);
    if (key === 'code') return '<code>' + esc(item.code || item.catalog_number || '—') + '</code>';
    return esc(item[key] != null ? item[key] : '—');
  }

  function filteredItems() {
    return items.filter(function (item) {
      if (filter !== 'all' && item.status !== filter) return false;
      if (!searchTerm) return true;
      var hay = [item.code, item.name, item.brand, item.catalog_number].join(' ').toLowerCase();
      return hay.indexOf(searchTerm.toLowerCase()) !== -1;
    });
  }

  function render() {
    var tbody = $('Table');
    if (!tbody) return;
    var rows = filteredItems();
    var badge = $('Badge');
    if (badge) badge.textContent = items.length + ' صنف';
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="' + columns.length + '" style="text-align:center;padding:24px;color:var(--text-muted);">لا توجد أصناف مطابقة.</td></tr>';
    } else {
      tbody.innerHTML = rows.map(function (item) {
        return '<tr>' + columns.map(function (col) {
          return '<td' + (col === 'available' ? ' class="col-qty"' : '') + '>' + cellValue(item, col) + '</td>';
        }).join('') + '</tr>';
      }).join('');
    }
    if (window.TablePagination) TablePagination.refreshById(prefix + 'Table');
  }

  function exportList(type) {
    if (!window.ExportKit) return;
    var headers = columns.map(function (c) {
      return c === 'code' ? 'الكود' : (c === 'name' ? 'الصنف' : c);
    });
    var rows = filteredItems().map(function (item) {
      return columns.map(function (c) {
        if (c === 'status') return item.status === 'backorder' ? 'طلب توريد' : (item.status === 'low' ? 'منخفض' : 'متوفر');
        return item[c] != null ? item[c] : '';
      });
    });
    var title = cfg.title || 'قائمة الأصناف';
    if (type === 'pdf') ExportKit.toPDF(title, headers, rows, '');
    else ExportKit.toExcel(title, headers, rows);
  }

  function bindEvents() {
    var search = $('Search');
    if (search) {
      search.addEventListener('input', function (e) {
        searchTerm = (e.target.value || '').trim();
        render();
      });
    }
    var filters = document.getElementById(prefix + 'Filters');
    if (filters) {
      filters.querySelectorAll('.filter-pill').forEach(function (btn) {
        btn.addEventListener('click', function () {
          filter = btn.getAttribute('data-filter') || 'all';
          filters.querySelectorAll('.filter-pill').forEach(function (b) { b.classList.remove('active'); });
          btn.classList.add('active');
          render();
        });
      });
    }
    document.querySelectorAll('[data-catalog-export]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        exportList(btn.getAttribute('data-catalog-export'));
      });
    });
  }

  if (cfg.listUrl) {
    fetch(cfg.listUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
      .then(function (payload) {
        items = (payload.data || []).map(normalizeItem);
        if (payload.columns && payload.columns.length) columns = payload.columns;
        render();
      })
      .catch(function () { render(); });
  } else {
    render();
  }

  bindEvents();
})();
