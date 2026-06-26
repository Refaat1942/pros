/**
 * عرض سجل دفعات التحصيل — مديونيات مدنية وعسكرية.
 */
(function (global) {
  'use strict';

  function fmtMoney(n) {
    return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function esc(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function parseJson(raw, fallback) {
    if (!raw) return fallback;
    try {
      return JSON.parse(raw);
    } catch (e) {
      return fallback;
    }
  }

  function modeBadgeClass(mode) {
    if (mode === 'full_once' || mode === 'full_multi') return 'paid';
    if (mode === 'partial_once' || mode === 'partial_multi') return 'partial';
    return 'pending';
  }

  function renderSummaryHtml(summary) {
    var s = summary || {};
    var cls = modeBadgeClass(s.mode || 'none');
    return '<div class="debt-collection-summary-card">' +
      '<span class="civ-debt-status civ-debt-status--' + cls + '">' + esc(s.mode_label || '—') + '</span>' +
      (s.payment_count > 0
        ? '<div class="debt-collection-summary-meta">' +
            '<span>عدد الدفعات: <strong>' + s.payment_count + '</strong></span>' +
            (s.first_collected_at ? '<span>أول دفعة: ' + esc(s.first_collected_at) + '</span>' : '') +
            (s.last_collected_at && s.payment_count > 1 ? '<span>آخر دفعة: ' + esc(s.last_collected_at) + '</span>' : '') +
          '</div>'
        : '') +
      '</div>';
  }

  function renderEntriesTable(entries) {
    if (!entries || !entries.length) {
      return '<p class="debt-collection-empty-msg">لا توجد دفعات مسجّلة بعد.</p>';
    }

    var rows = entries.map(function (e) {
      return '<tr>' +
        '<td class="num">' + e.installment_no + '</td>' +
        '<td>' + esc(e.collected_at) + '</td>' +
        '<td class="num">' + fmtMoney(e.amount) + '</td>' +
        '<td class="num" style="color:#059669;">' + fmtMoney(e.running_collected) + '</td>' +
        '<td class="num" style="color:#d97706;">' + fmtMoney(e.remaining_after) + '</td>' +
        '<td>' + esc(e.recorded_by_name) + '</td>' +
        '</tr>';
    }).join('');

    return '<div class="debt-collection-table-wrap">' +
      '<table class="debt-collection-table">' +
        '<thead><tr>' +
          '<th>#</th><th>التاريخ</th><th class="num">المبلغ</th>' +
          '<th class="num">المحصّل التراكمي</th><th class="num">المتبقي بعدها</th><th>بواسطة</th>' +
        '</tr></thead>' +
        '<tbody>' + rows + '</tbody>' +
      '</table></div>';
  }

  function renderCollectionCell(summary, entries, collected) {
    var col = parseFloat(collected) || 0;
    if (col <= 0) {
      return '<span class="debt-collection-empty">—</span>';
    }

    var s = summary || {};
    var cls = modeBadgeClass(s.mode || 'none');
    return '<button type="button" class="debt-collection-summary-btn" onclick="openDebtCollectionModal(this)">' +
      '<span class="debt-collection-badge civ-debt-status civ-debt-status--' + cls + '">' + esc(s.mode_label || 'تفاصيل التحصيل') + '</span>' +
      (s.payment_count > 1 ? '<small class="debt-collection-count">' + s.payment_count + ' دفعات</small>' : '') +
      '</button>';
  }

  function setRowCollectionData(row, summary, entries) {
    if (!row) return;
    row.dataset.collectionSummary = JSON.stringify(summary || {});
    row.dataset.collectionEntries = JSON.stringify(entries || []);
  }

  function updateCollectionCell(row, summary, entries, collected) {
    var cell = row.querySelector('.debt-collection-cell');
    if (!cell) return;
    setRowCollectionData(row, summary, entries);
    cell.innerHTML = renderCollectionCell(summary, entries, collected);
  }

  function openDebtCollectionModal(btn) {
    var row = btn.closest('[data-collection-summary]');
    if (!row) return;

    var modal = document.getElementById('debtCollectionModal');
    var title = document.getElementById('debtCollectionModalTitle');
    var subtitle = document.getElementById('debtCollectionModalSubtitle');
    var summaryEl = document.getElementById('debtCollectionModalSummary');
    var body = document.getElementById('debtCollectionModalBody');

    var summary = parseJson(row.dataset.collectionSummary, {});
    var entries = parseJson(row.dataset.collectionEntries, []);
    var entityTitle = row.dataset.collectionTitle || row.dataset.search || '';

    if (title) title.textContent = '📋 تفاصيل التحصيل';
    if (subtitle) subtitle.textContent = entityTitle;
    if (summaryEl) summaryEl.innerHTML = renderSummaryHtml(summary);
    if (body) body.innerHTML = renderEntriesTable(entries);
    if (modal) modal.style.display = 'flex';
  }

  function closeDebtCollectionModal() {
    var modal = document.getElementById('debtCollectionModal');
    if (modal) modal.style.display = 'none';
  }

  function bindModal() {
    var modal = document.getElementById('debtCollectionModal');
    if (!modal || modal.dataset.bound) return;
    modal.dataset.bound = '1';

    modal.addEventListener('click', function (e) {
      if (e.target === modal) closeDebtCollectionModal();
    });

    var btnX = document.getElementById('btnCloseDebtCollection');
    var btnClose = document.getElementById('btnDebtCollectionModalClose');
    if (btnX) btnX.addEventListener('click', closeDebtCollectionModal);
    if (btnClose) btnClose.addEventListener('click', closeDebtCollectionModal);
  }

  global.openDebtCollectionModal = openDebtCollectionModal;
  global.closeDebtCollectionModal = closeDebtCollectionModal;
  global.DebtCollectionHistory = {
    fmtMoney: fmtMoney,
    renderCollectionCell: renderCollectionCell,
    updateCollectionCell: updateCollectionCell,
    setRowCollectionData: setRowCollectionData,
    renderSummaryHtml: renderSummaryHtml,
    renderEntriesTable: renderEntriesTable,
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindModal);
  } else {
    bindModal();
  }
})(window);
