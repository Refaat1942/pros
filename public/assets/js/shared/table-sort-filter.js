/**
 * Universal table/list sort + text/status filters for dashboard data tables.
 * Works with table-pagination.js (filterHidden + repaginate).
 */
(function (global) {
  'use strict';

  var SKIP_TABLE_SELECTOR =
    '.catalog-slim-table, [data-sort-filter="off"], [data-no-table-enhance]';

  function shouldSkipTable(table) {
    if (!table || table.tagName !== 'TABLE') return true;
    if (table.matches(SKIP_TABLE_SELECTOR)) return true;
    if (table.closest(SKIP_TABLE_SELECTOR)) return true;
    return false;
  }

  function tbodyOf(table) {
    return table.tBodies[0] || null;
  }

  function isEmptyRow(row) {
    if (row.dataset.paginationSkip === '1' && row.dataset.filterHidden !== '1') {
      // allow pagination-only skip from other code paths
    }
    var cell = row.querySelector('td[colspan], th[colspan]');
    if (cell) return true;
    if (row.classList.contains('pagination-empty-row')) return true;
    if (row.classList.contains('pagination-empty-msg')) return true;
    return false;
  }

  function isDataRow(row) {
    return row && !isEmptyRow(row);
  }

  function rowHaystack(row) {
    var parts = [
      row.dataset.search,
      row.dataset.name,
      row.dataset.code,
      row.dataset.role,
      row.dataset.status,
      row.dataset.filter,
    ];
    var joined = parts.filter(Boolean).join(' ').toLowerCase();
    if (joined.trim()) return joined;
    return (row.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
  }

  function cellValue(row, colIndex) {
    var cells = row.cells;
    if (!cells || colIndex >= cells.length) return '';
    return (cells[colIndex].textContent || '').replace(/\s+/g, ' ').trim();
  }

  function parseSortable(val) {
    if (val === '' || val === '—' || val === '-') return null;
    var n = parseFloat(String(val).replace(/[^\d.,\-]/g, '').replace(',', '.'));
    if (!isNaN(n) && /[\d]/.test(val)) return n;
    return val;
  }

  function compareValues(a, b) {
    var na = typeof a === 'number';
    var nb = typeof b === 'number';
    if (na && nb) return a - b;
    if (na && !nb) return -1;
    if (!na && nb) return 1;
    return String(a).localeCompare(String(b), 'ar', { numeric: true, sensitivity: 'base' });
  }

  function searchInputCandidates(tableId) {
    var base = tableId.replace(/(TableBody|Table|List)$/i, '');
    var list = [base + 'Search', tableId + 'Search'];
    if (base.endsWith('ies')) {
      list.push(base.slice(0, -3) + 'ySearch');
    } else if (base.endsWith('s')) {
      list.push(base.slice(0, -1) + 'Search');
    }
    return list;
  }

  function findSearchInput(table) {
    var id = table.id;
    if (id) {
      var explicit = document.querySelector('[data-table-search-for="' + id + '"]');
      if (explicit) return explicit;
      searchInputCandidates(id).forEach(function (candidateId) {
        if (!explicit) {
          var el = document.getElementById(candidateId);
          if (el) explicit = el;
        }
      });
      if (explicit) return explicit;
    }

    var panel = table.closest('.panel, .section-view, .inventory-wrap, [class*="section"]');
    if (!panel) return null;

    var inputs = panel.querySelectorAll(
      '.data-toolbar input[type="text"], .data-toolbar input[type="search"], ' +
        '.inventory-toolbar input, .search-bar input, .panel-header input[type="search"]'
    );
    for (var i = 0; i < inputs.length; i++) {
      var inp = inputs[i];
      if (inp.dataset.dashTableSearchBound === '1') continue;
      if (inp.classList.contains('date-filter-input') || inp.classList.contains('date-filter-native')) continue;
      return inp;
    }
    return null;
  }

  function findCountElement(table) {
    var id = table.id;
    if (!id) return null;
    var byAttr = document.querySelector('[data-table-count-for="' + id + '"]');
    if (byAttr) return byAttr;
    var guess = id.replace(/(TableBody|Table)$/i, '') + 'Count';
    return document.getElementById(guess);
  }

  function isNoSortTh(th) {
    if (!th) return true;
    if (th.dataset.noSort === '1') return true;
    if (th.classList.contains('bulk-select-col')) return true;
    if (th.classList.contains('catalog-sort-th')) return true;
    if (th.querySelector('input, select, button')) return true;
    var label = (th.textContent || '').trim();
    if (label === '#' || label === 'إجراء' || label.indexOf('إجراء') !== -1) return true;
    return false;
  }

  function ensureSortIcons(th) {
    if (!th.querySelector('.dash-sort-icon')) {
      var icon = document.createElement('span');
      icon.className = 'dash-sort-icon';
      icon.textContent = '↕';
      icon.setAttribute('aria-hidden', 'true');
      th.appendChild(document.createTextNode(' '));
      th.appendChild(icon);
    }
  }

  function updateSortHeaders(state) {
    state.sortHeaders.forEach(function (item) {
      var th = item.th;
      var active = item.index === state.sortCol;
      th.classList.toggle('is-sorted', active);
      var icon = th.querySelector('.dash-sort-icon');
      if (icon) {
        icon.textContent = active ? (state.sortDir === 'asc' ? '▲' : '▼') : '↕';
      }
    });
  }

  function sortRows(state) {
    var tbody = state.tbody;
    if (!tbody || state.sortCol < 0) return;

    var rows = state.dataRows.slice();
    var col = state.sortCol;
    var dir = state.sortDir === 'desc' ? -1 : 1;

    rows.sort(function (ra, rb) {
      var av = parseSortable(cellValue(ra, col));
      var bv = parseSortable(cellValue(rb, col));
      if (av === null && bv === null) return 0;
      if (av === null) return 1;
      if (bv === null) return -1;
      return compareValues(av, bv) * dir;
    });

    rows.forEach(function (row) {
      tbody.appendChild(row);
    });
  }

  function applyFilters(state) {
    var q = (state.searchText || '').trim().toLowerCase();
    var status = state.statusFilter || '';
    var visible = 0;

    state.dataRows.forEach(function (row) {
      var hay = rowHaystack(row);
      var matchText = !q || hay.indexOf(q) !== -1;
      var matchStatus = !status || (row.dataset.status || '') === status;
      var show = matchText && matchStatus;

      if (show) {
        row.dataset.filterHidden = '0';
        delete row.dataset.paginationSkip;
        visible++;
      } else {
        row.dataset.filterHidden = '1';
        row.dataset.paginationSkip = '1';
      }
    });

    if (state.countEl && state.countSuffix) {
      state.countEl.textContent = visible + ' ' + state.countSuffix;
    } else if (state.countEl && state.countEl.dataset.dashTableCountBound === '1') {
      state.countEl.textContent = visible + ' سجل';
    }

    if (global.TablePagination) {
      global.TablePagination.repaginate(state.table);
    }
  }

  function bindSortHeaders(state) {
    var thead = state.table.tHead;
    if (!thead) return;

    var ths = thead.querySelectorAll('th');
    state.sortHeaders = [];

    ths.forEach(function (th, index) {
      if (isNoSortTh(th)) return;
      th.classList.add('dash-sort-th');
      ensureSortIcons(th);
      state.sortHeaders.push({ th: th, index: index });

      th.addEventListener('click', function () {
        if (state.sortCol === index) {
          state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
        } else {
          state.sortCol = index;
          state.sortDir = 'asc';
        }
        sortRows(state);
        updateSortHeaders(state);
        applyFilters(state);
      });
    });
  }

  function collectStatusValues(rows) {
    var map = {};
    rows.forEach(function (row) {
      var key = row.dataset.status;
      if (!key) return;
      if (!map[key]) {
        map[key] = row.dataset.statusLabel || key;
      }
    });
    return map;
  }

  function ensureFilterBar(state) {
    if (state.filterBar) return;

    var statuses = collectStatusValues(state.dataRows);
    var statusKeys = Object.keys(statuses);
    var needsBar = !state.searchInput && statusKeys.length > 1;

    if (!needsBar && !state.searchInput) {
      // inject search if no toolbar search found
      needsBar = true;
    }

    if (!needsBar) return;

    var host = state.table.closest('.panel-body, .stock-table-wrap, .panel');
    if (!host) return;

    var bar = document.createElement('div');
    bar.className = 'dash-table-filter-bar';
    bar.setAttribute('data-filter-bar-for', state.table.id || '');

    var search = document.createElement('input');
    search.type = 'search';
    search.placeholder = '🔍 بحث في الجدول...';
    search.setAttribute('aria-label', 'بحث في الجدول');
    search.addEventListener('input', function () {
      state.searchText = search.value;
      applyFilters(state);
    });
    bar.appendChild(search);
    state.injectedSearch = search;

    if (statusKeys.length > 1 && statusKeys.length <= 25) {
      var sel = document.createElement('select');
      sel.setAttribute('aria-label', 'فلتر الحالة');
      sel.innerHTML = '<option value="">كل الحالات</option>' +
        statusKeys.map(function (k) {
          return '<option value="' + k + '">' + statuses[k] + '</option>';
        }).join('');
      sel.addEventListener('change', function () {
        state.statusFilter = sel.value;
        applyFilters(state);
      });
      bar.appendChild(sel);
      state.statusSelect = sel;
    }

    var count = document.createElement('span');
    count.className = 'dash-table-filter-count';
    bar.appendChild(count);
    state.injectedCount = count;

    if (host.firstChild) {
      host.insertBefore(bar, host.firstChild);
    } else {
      host.appendChild(bar);
    }
    state.filterBar = bar;
  }

  function bindSearchInput(state, input) {
    if (!input || input.dataset.dashTableSearchBound === '1') return;
    input.dataset.dashTableSearchBound = '1';
    state.searchInput = input;

    var handler = function () {
      state.searchText = input.value;
      applyFilters(state);
    };
    input.addEventListener('input', handler);
    input.addEventListener('search', handler);
  }

  function refreshDataRows(state) {
    state.dataRows = Array.prototype.slice.call(state.tbody.rows).filter(isDataRow);
  }

  function bindTable(table) {
    if (shouldSkipTable(table)) return;
    if (table.dataset.sortFilterBound === '1') return;

    var tbody = tbodyOf(table);
    if (!tbody) return;

    var dataRows = Array.prototype.slice.call(tbody.rows).filter(isDataRow);
    if (!dataRows.length) return;

    table.dataset.sortFilter = '1';
    table.dataset.sortFilterBound = '1';

    var state = {
      table: table,
      tbody: tbody,
      dataRows: dataRows,
      sortCol: -1,
      sortDir: 'asc',
      sortHeaders: [],
      searchText: '',
      statusFilter: '',
      searchInput: null,
      countEl: findCountElement(table),
      countSuffix: null,
      filterBar: null,
    };

    if (state.countEl) {
      var m = (state.countEl.textContent || '').match(/^\d+\s+(.*)$/);
      state.countSuffix = m ? m[1] : null;
      state.countEl.dataset.dashTableCountBound = '1';
    }

    var linkedSearch = findSearchInput(table);
    if (linkedSearch) {
      bindSearchInput(state, linkedSearch);
      state.searchText = linkedSearch.value;
    } else {
      ensureFilterBar(state);
    }

    bindSortHeaders(state);
    table._sortFilterState = state;

    applyFilters(state);
  }

  function bindList(list) {
    if (!list || list.dataset.sortFilterBound === '1') return;
    if (list.hasAttribute('data-sort-filter') && list.getAttribute('data-sort-filter') === 'off') return;

    var items = Array.prototype.slice.call(list.children).filter(function (li) {
      return !li.classList.contains('pagination-empty-msg');
    });
    if (!items.length) return;

    list.dataset.sortFilterBound = '1';

    var panel = list.closest('.panel, .section-view');
    var search = null;
    if (list.id) {
      searchInputCandidates(list.id).some(function (id) {
        var el = document.getElementById(id);
        if (el) {
          search = el;
          return true;
        }
        return false;
      });
    }
    if (!search && panel) {
      search = panel.querySelector('input[type="search"], .search-bar input');
    }

    function applyListFilter() {
      var q = (search ? search.value : '').trim().toLowerCase();
      items.forEach(function (li) {
        var hay = (li.dataset.search || li.textContent || '').toLowerCase();
        var show = !q || hay.indexOf(q) !== -1;
        li.dataset.filterHidden = show ? '0' : '1';
      });
      if (global.TablePagination) global.TablePagination.repaginate(list);
    }

    if (search && search.dataset.dashTableSearchBound !== '1') {
      search.dataset.dashTableSearchBound = '1';
      search.addEventListener('input', applyListFilter);
      search.addEventListener('search', applyListFilter);
    }

    list._sortFilterListApply = applyListFilter;
  }

  function bindRoot(root) {
    root = root || document;
    root.querySelectorAll('table[data-paginate]').forEach(bindTable);
    root.querySelectorAll('ul[data-paginate], ol[data-paginate]').forEach(bindList);
  }

  function refresh(el) {
    if (!el) return;
    var table = el.tagName === 'TABLE' ? el : (el.closest ? el.closest('table') : null);
    if (table && table._sortFilterState) {
      refreshDataRows(table._sortFilterState);
      if (table._sortFilterState.searchInput) {
        table._sortFilterState.searchText = table._sortFilterState.searchInput.value;
      }
      sortRows(table._sortFilterState);
      applyFilters(table._sortFilterState);
      return;
    }
    if (table && !table.dataset.sortFilterBound) {
      bindTable(table);
    }
    var list = el.tagName === 'UL' || el.tagName === 'OL' ? el : null;
    if (!list && el.id) {
      var byId = document.getElementById(el.id);
      if (byId && (byId.tagName === 'UL' || byId.tagName === 'OL')) list = byId;
    }
    if (list && list._sortFilterListApply) list._sortFilterListApply();
  }

  function hookPagination() {
    if (!global.TablePagination || global.TablePagination._sortFilterHooked) return;
    var orig = global.TablePagination.refresh;
    global.TablePagination.refresh = function (el) {
      refresh(el);
      return orig.call(global.TablePagination, el);
    };
    global.TablePagination._sortFilterHooked = true;
  }

  global.TableSortFilter = {
    bind: bindRoot,
    refresh: refresh,
    refreshById: function (id) {
      refresh(document.getElementById(id));
    },
  };

  function boot() {
    hookPagination();
    bindRoot();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})(typeof window !== 'undefined' ? window : this);
