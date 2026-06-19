/**
 * Client-side table/list pagination — max 10 rows per page (configurable via data-paginate).
 */
(function (global) {
  'use strict';

  var DEFAULT_PER_PAGE = 10;

  function perPage(el) {
    var n = parseInt(el.getAttribute('data-paginate') || DEFAULT_PER_PAGE, 10);
    return n > 0 ? n : DEFAULT_PER_PAGE;
  }

  function isEmptyRow(row) {
    if (row.dataset.paginationSkip === '1') return true;
    var cell = row.querySelector('td[colspan], th[colspan]');
    if (cell) return true;
    if (row.classList.contains('pagination-empty-row')) return true;
    return false;
  }

  function isEmptyListItem(li) {
    if (li.dataset.paginationSkip === '1') return true;
    if (li.classList.contains('orders-empty-msg')) return true;
    if (li.classList.contains('pagination-empty-msg')) return true;
    return false;
  }

  function navId(target) {
    return target.id || ('paginate-' + Math.random().toString(36).slice(2, 9));
  }

  function buildNav(target, state) {
    var existing = state.navEl;
    if (existing) existing.remove();

    if (state.totalDataRows === 0) {
      state.navEl = null;
      return;
    }

    var nav = document.createElement('div');
    nav.className = 'table-pagination-nav';
    nav.setAttribute('data-pagination-for', target.id || '');

    var info = document.createElement('span');
    info.className = 'table-pagination-info';
    info.textContent = 'صفحة ' + state.page + ' / ' + state.totalPages + ' — ' + state.totalRows + ' سجل';

    var prev = document.createElement('button');
    prev.type = 'button';
    prev.className = 'table-pagination-btn';
    prev.textContent = 'السابق';
    prev.disabled = state.page <= 1;

    var next = document.createElement('button');
    next.type = 'button';
    next.className = 'table-pagination-btn';
    next.textContent = 'التالي';
    next.disabled = state.page >= state.totalPages;

    if (state.totalPages > 1) {
      prev.addEventListener('click', function () {
        if (state.page > 1) {
          state.page--;
          applyState(state);
        }
      });
      next.addEventListener('click', function () {
        if (state.page < state.totalPages) {
          state.page++;
          applyState(state);
        }
      });
    }

    nav.appendChild(prev);
    nav.appendChild(info);
    nav.appendChild(next);

    var host = state.insertAfter || target;
    if (host.nextSibling) {
      host.parentNode.insertBefore(nav, host.nextSibling);
    } else {
      host.parentNode.appendChild(nav);
    }
    state.navEl = nav;
  }

  function applyState(state) {
    var start = (state.page - 1) * state.perPage;
    var end = start + state.perPage;
    var visibleData = 0;

    state.rows.forEach(function (row, idx) {
      if (state.isEmpty(row)) {
        row.style.display = state.totalDataRows === 0 ? '' : 'none';
        return;
      }
      var show = visibleData >= start && visibleData < end;
      row.style.display = show ? '' : 'none';
      visibleData++;
    });

    buildNav(state.target, state);
  }

  function collectRows(state) {
    if (state.mode === 'table') {
      var tbody = state.target.tBodies[0];
      if (!tbody) return;
      state.rows = Array.prototype.slice.call(tbody.rows);
    } else {
      state.rows = Array.prototype.slice.call(state.target.children);
    }
    state.totalDataRows = state.rows.filter(function (r) { return !state.isEmpty(r); }).length;
    state.totalRows = state.totalDataRows;
    state.totalPages = Math.max(1, Math.ceil(state.totalDataRows / state.perPage));
    if (state.page > state.totalPages) state.page = 1;
  }

  function paginationHost(el) {
    if (!el) return el;
    var panelBody = el.closest && el.closest('.panel-body');
    return panelBody || el.parentElement || el;
  }

  function bindTable(table) {
    if (!table || table.dataset.paginateBound === '1') return;
    if (table.hasAttribute('data-no-paginate')) return;
    if (table.closest('[data-no-paginate]')) return;

    if (!table.id) table.id = navId(table);

    var state = {
      mode: 'table',
      target: table,
      insertAfter: paginationHost(table),
      perPage: perPage(table),
      page: 1,
      rows: [],
      isEmpty: isEmptyRow,
      navEl: null,
    };

    collectRows(state);
    if (state.totalDataRows === 0) return;

    table.dataset.paginateBound = '1';
    table._paginationState = state;
    applyState(state);
  }

  function bindList(list) {
    if (!list || list.dataset.paginateBound === '1') return;
    if (list.hasAttribute('data-no-paginate')) return;
    if (list.closest('[data-no-paginate]')) return;

    if (!list.id) list.id = navId(list);

    var state = {
      mode: 'list',
      target: list,
      insertAfter: paginationHost(list),
      perPage: perPage(list),
      page: 1,
      rows: [],
      isEmpty: isEmptyListItem,
      navEl: null,
    };

    collectRows(state);
    if (state.totalDataRows === 0) return;

    list.dataset.paginateBound = '1';
    list._paginationState = state;
    applyState(state);
  }

  function refresh(el) {
    if (!el) return;
    var table = el.tagName === 'TABLE' ? el : el.closest('table');
    var list = el.matches && el.matches('ul,ol') ? el : (el.id ? document.getElementById(el.id) : null);
    if (!list && el.closest) {
      var ul = el.closest('ul,ol');
      if (ul && ul.hasAttribute('data-paginate')) list = ul;
    }

    if (table && table.hasAttribute('data-paginate')) {
      delete table.dataset.paginateBound;
      if (table._paginationState && table._paginationState.navEl) {
        table._paginationState.navEl.remove();
      }
      bindTable(table);
    }
    if (list && list.hasAttribute('data-paginate')) {
      delete list.dataset.paginateBound;
      if (list._paginationState && list._paginationState.navEl) {
        list._paginationState.navEl.remove();
      }
      bindList(list);
    }
  }

  function bindDashboard(root) {
    root = root || document;
    root.querySelectorAll('table[data-paginate]').forEach(bindTable);
    root.querySelectorAll('ul[data-paginate], ol[data-paginate]').forEach(bindList);
  }

  function injectStyles() {
    if (document.getElementById('table-pagination-style')) return;
    var style = document.createElement('style');
    style.id = 'table-pagination-style';
    style.textContent =
      '.table-pagination-nav{display:flex;align-items:center;justify-content:center;gap:12px;flex-wrap:wrap;margin-top:0;padding:14px 16px;border-top:1px solid var(--border,#e2e8f0);background:var(--card,#fff)}' +
      '.table-pagination-info{font-size:13px;color:var(--text-muted,#64748b);font-weight:600}' +
      '.table-pagination-btn{padding:8px 16px;border-radius:8px;border:1px solid var(--border,#e2e8f0);background:#fff;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;color:var(--primary,#0e7490)}' +
      '.table-pagination-btn:hover:not(:disabled){background:rgba(14,116,144,.08)}' +
      '.table-pagination-btn:disabled{opacity:.45;cursor:not-allowed}';
    document.head.appendChild(style);
  }

  global.TablePagination = {
    bind: bindDashboard,
    refresh: refresh,
    refreshById: function (id) {
      var el = typeof id === 'string' ? document.getElementById(id) : id;
      if (el) refresh(el);
    },
    DEFAULT_PER_PAGE: DEFAULT_PER_PAGE,
  };

  function boot() {
    injectStyles();
    bindDashboard();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})(typeof window !== 'undefined' ? window : this);
