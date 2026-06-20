/**
 * Bulk row selection + delete for admin tables.
 * Table: class="bulk-select-table" data-bulk-bar="..." data-bulk-delete-base="/admin/..."
 */
(function () {
  function getCsrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }

  function visibleRows(tbody) {
    return Array.prototype.filter.call(tbody.querySelectorAll('tr'), function (tr) {
      return tr.style.display !== 'none' && tr.querySelector('.bulk-row-select:not(:disabled)');
    });
  }

  function initTable(table) {
    var barId = table.getAttribute('data-bulk-bar');
    var baseUrl = table.getAttribute('data-bulk-delete-base');
    if (!barId || !baseUrl) return;

    var bar = document.getElementById(barId);
    if (!bar) return;

    var countEl = bar.querySelector('.bulk-selected-count');
    var deleteBtn = bar.querySelector('[data-bulk-delete-btn]');
    var selectAll = table.querySelector('.bulk-select-all');
    var tbody = table.querySelector('tbody');
    if (!tbody || !deleteBtn) return;

    function selectedCheckboxes() {
      return visibleRows(tbody)
        .map(function (tr) { return tr.querySelector('.bulk-row-select'); })
        .filter(function (cb) { return cb && cb.checked && !cb.disabled; });
    }

    function updateBar() {
      var checked = selectedCheckboxes();
      if (checked.length) {
        bar.hidden = false;
        if (countEl) countEl.textContent = checked.length + ' محدّد';
      } else {
        bar.hidden = true;
      }
      if (selectAll) {
        var rows = visibleRows(tbody);
        selectAll.checked = rows.length > 0 && rows.every(function (tr) {
          var cb = tr.querySelector('.bulk-row-select');
          return cb && cb.checked;
        });
        selectAll.indeterminate = checked.length > 0 && !selectAll.checked;
      }
    }

    if (selectAll) {
      selectAll.addEventListener('change', function () {
        visibleRows(tbody).forEach(function (tr) {
          var cb = tr.querySelector('.bulk-row-select');
          if (cb && !cb.disabled) cb.checked = selectAll.checked;
        });
        updateBar();
      });
    }

    tbody.addEventListener('change', function (e) {
      if (e.target.classList.contains('bulk-row-select')) updateBar();
    });

    deleteBtn.addEventListener('click', function () {
      var boxes = selectedCheckboxes();
      if (!boxes.length) return;
      if (!confirm('حذف ' + boxes.length + ' عنصر محدّد؟')) return;

      deleteBtn.disabled = true;
      var failed = 0;
      var messages = [];

      Promise.all(boxes.map(function (cb) {
        return fetch(baseUrl + '/' + encodeURIComponent(cb.value), {
          method: 'DELETE',
          headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': getCsrf(),
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
        })
          .then(function (r) {
            if (r.ok) return;
            return r.json().then(function (j) {
              failed++;
              if (j && j.message) messages.push(j.message);
            }).catch(function () { failed++; });
          })
          .catch(function () { failed++; });
      })).then(function () {
        if (failed) {
          var msg = messages.length ? messages[0] : 'تعذّر حذف ' + failed + ' عنصر — قد تكون مرتبطة بسجلات أخرى.';
          alert(msg);
        }
        window.location.reload();
      });
    });
  }

  function boot() {
    document.querySelectorAll('table.bulk-select-table').forEach(initTable);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
