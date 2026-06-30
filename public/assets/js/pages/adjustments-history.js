/**
 * Adjustments transfer history — cases sent from adjustments to costing.
 */
(function () {
  if (document.body.dataset.dashboard !== 'adjustments') return;
  if (document.body.dataset.activePage !== 'adjustments') return;

  function $(id) { return document.getElementById(id); }

  function filterTable() {
    var q = (($('adjHistorySearch') && $('adjHistorySearch').value) || '').trim().toLowerCase();
    var tbody = $('adjHistoryBody');
    if (!tbody) return;

    var visible = 0;
    tbody.querySelectorAll('tr[data-search]').forEach(function (row) {
      var hay = (row.getAttribute('data-search') || '').toLowerCase();
      var show = !q || hay.indexOf(q) !== -1;
      row.style.display = show ? '' : 'none';
      if (show) visible++;
    });

    var countEl = $('adjHistoryVisibleCount');
    if (countEl) countEl.textContent = visible + ' حالة';
  }

  document.addEventListener('DOMContentLoaded', function () {
    var search = $('adjHistorySearch');
    if (search) {
      search.addEventListener('input', filterTable);
    }

    if (window.TablePagination) {
      TablePagination.refreshById('adjHistoryTable');
    }
  });
})();
