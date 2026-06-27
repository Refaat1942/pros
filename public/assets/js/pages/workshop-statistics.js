/**
 * صفحة إحصائيات الورشة — ChartKit + ExportKit من بيانات السيرفر فقط.
 */
(function () {
  'use strict';

  if (document.body.dataset.dashboard !== 'workshop') return;
  if (document.body.dataset.activePage !== 'statistics') return;

  var HEADERS = ['أمر التشغيل', 'المريض', 'رقم الحالة', 'المسار', 'تاريخ الإغلاق', 'مدة التصنيع', 'قائمة المواد'];

  function initCharts() {
    var root = document.getElementById('workshopStatsRoot');
    var dataEl = document.getElementById('workshopStatsData');

    if (!root || !dataEl || typeof ChartKit === 'undefined') {
      return;
    }

    var payload;

    try {
      payload = JSON.parse(dataEl.textContent || '{}');
    } catch (err) {
      console.error('workshop-statistics: invalid JSON', err);
      return;
    }

    if (!payload.stats && !payload.charts) {
      return;
    }

    ChartKit.mount('workshopStatsRoot', {
      stats: payload.stats || [],
      charts: payload.charts || [],
    });
  }

  function collectTableRows() {
    var tbody = document.getElementById('workshopCompletedTableBody');
    if (!tbody) return [];

    return Array.prototype.slice.call(tbody.querySelectorAll('tr')).filter(function (row) {
      return !row.classList.contains('pagination-empty-row') && row.style.display !== 'none';
    }).map(function (row) {
      var cells = row.querySelectorAll('td');
      return Array.prototype.map.call(cells, function (cell) {
        return (cell.textContent || '').replace(/\s+/g, ' ').trim();
      });
    });
  }

  function exportCompletedTable() {
    if (!window.ExportKit || !ExportKit.toExcel) {
      alert('أداة التصدير غير متاحة');
      return;
    }

    var rows = collectTableRows();

    if (!rows.length) {
      alert('لا توجد بيانات للتصدير');
      return;
    }

    var formatted = rows.map(function (row) {
      return row.map(function (cell, idx) {
        if (idx === 4 && ExportKit.formatDateForExport) {
          return ExportKit.formatDateForExport(cell);
        }
        return cell;
      });
    });

    ExportKit.toExcel(ExportKit.buildFilename('سجل_الإنتاج_المكتمل'), HEADERS, formatted);
  }

  function bindSearch() {
    var input = document.getElementById('workshopCompletedSearch');
    var tbody = document.getElementById('workshopCompletedTableBody');
    var table = document.getElementById('workshopCompletedTable');

    if (!input || !tbody) return;

    input.addEventListener('input', function () {
      var q = input.value.trim().toLowerCase();

      Array.prototype.forEach.call(tbody.querySelectorAll('tr'), function (row) {
        if (row.classList.contains('pagination-empty-row')) return;

        var hay = (row.getAttribute('data-search') || row.textContent || '').toLowerCase();
        var match = !q || hay.indexOf(q) !== -1;
        row.dataset.filterHidden = match ? '0' : '1';
        if (!match) row.style.display = 'none';
      });

      if (window.TablePagination && table) {
        if (table._paginationState) {
          table._paginationState.page = 1;
          TablePagination.repaginate(table);
        } else {
          TablePagination.refresh(table);
        }
      }
    });
  }

  function bindExport() {
    var btn = document.getElementById('workshopCompletedExportExcel');
    if (btn) {
      btn.addEventListener('click', exportCompletedTable);
    }
  }

  function init() {
    initCharts();
    bindSearch();
    bindExport();

    if (window.TablePagination) {
      var table = document.getElementById('workshopCompletedTable');
      if (table) TablePagination.refresh(table);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
