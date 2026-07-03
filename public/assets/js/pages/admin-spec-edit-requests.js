(function () {
  if (document.body.dataset.activePage !== 'spec-edit-requests') return;

  var csrf = document.querySelector('meta[name="csrf-token"]');
  var csrfToken = csrf ? csrf.getAttribute('content') : '';
  var activeDetailId = null;

  function $(id) { return document.getElementById(id); }

  function toast(msg, isError) {
    if (window.DashboardToast) {
      window.DashboardToast.show(msg, { id: 'toast', isError: !!isError });
      return;
    }
    alert(msg);
  }

  function jsonFetch(url, options) {
    options = options || {};
    options.headers = Object.assign({
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': csrfToken,
    }, options.headers || {});
    return fetch(url, options).then(function (res) {
      return res.json().then(function (data) {
        if (!res.ok) {
          var err = new Error(data.message || 'Request failed');
          err.response = { data: data };
          throw err;
        }
        return data;
      });
    });
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function modifiedItemsTableHtml(items) {
    var rows = (items || []).map(function (i) {
      if (i.change === 'removed') {
        return '<tr class="spec-edit-item--removed">' +
          '<td style="font-family:monospace;font-size:12px;">' + esc(i.stock_item_code || '—') + '</td>' +
          '<td>🗑️ تم حذف البند: <strong>' + esc(i.name || i.stock_item_code || '—') + '</strong></td>' +
          '<td>' + esc(i.qty) + '</td></tr>';
      }
      var qtyCell = '<strong>' + esc(i.qty) + '</strong>';
      if (i.change === 'updated' && i.previous_qty != null) {
        qtyCell += ' <span class="spec-edit-qty-was">(كان ' + esc(i.previous_qty) + ')</span>';
      }
      return '<tr>' +
        '<td style="font-family:monospace;font-size:12px;">' + esc(i.stock_item_code || '—') + '</td>' +
        '<td>' + esc(i.name || i.stock_item_code || '—') + '</td>' +
        '<td>' + qtyCell + '</td></tr>';
    }).join('');
    if (!rows) {
      rows = '<tr><td colspan="3" style="text-align:center;color:var(--text-muted);">لا توجد بنود معدلة</td></tr>';
    }
    return '<table class="patient-track-table spec-edit-detail-table is-proposed">' +
      '<thead><tr><th>الكود</th><th>الصنف</th><th>الكمية</th></tr></thead><tbody>' + rows + '</tbody></table>';
  }

  function itemsTableHtml(items, isProposed) {
    var rows = (items || []).map(function (i) {
      return '<tr>' +
        '<td style="font-family:monospace;font-size:12px;">' + esc(i.stock_item_code || '—') + '</td>' +
        '<td>' + esc(i.name || i.stock_item_code || '—') + '</td>' +
        '<td>' + (isProposed ? '<strong>' : '') + esc(i.qty) + (isProposed ? '</strong>' : '') + '</td>' +
        '</tr>';
    }).join('');
    if (!rows) {
      rows = '<tr><td colspan="3" style="text-align:center;color:var(--text-muted);">لا توجد بنود</td></tr>';
    }
    return '<table class="patient-track-table spec-edit-detail-table' + (isProposed ? ' is-proposed' : '') + '">' +
      '<thead><tr><th>الكود</th><th>الصنف</th><th>الكمية</th></tr></thead><tbody>' + rows + '</tbody></table>';
  }

  function detailHtml(row) {
    var status = row.status || 'pending';
    var html = '<div class="spec-edit-detail-inner">' +
      '<div class="spec-edit-detail-meta">' +
      '<div><span>المريض</span><strong>' + esc(row.patient_name || '—') + '</strong></div>' +
      '<div><span>رقم الحالة</span><strong>' + esc(row.case_no || '—') + '</strong></div>' +
      '<div><span>مرجع الطلب</span><strong>' + esc(row.order_ref || '—') + '</strong></div>' +
      '<div><span>المصدر</span><strong>' + esc(row.source_label || '—') + '</strong></div>' +
      '<div><span>طلب بواسطة</span><strong>' + esc(row.requested_by || '—') + '</strong></div>' +
      '<div><span>التاريخ</span><strong>' + esc(row.requested_at_label || '—') + '</strong></div>' +
      '</div>' +
      '<div class="spec-edit-detail-grid">' +
      '<div><p class="spec-edit-detail-label">📋 البنود الحالية</p>' + itemsTableHtml(row.original_items, false) + '</div>' +
      '<div><p class="spec-edit-detail-label is-proposed">✏️ البنود المعدلة</p>' + modifiedItemsTableHtml(row.modified_items) + '</div>' +
      '</div>';

    if (row.proposed_tech_notes) {
      html += '<p class="spec-edit-detail-note is-muted"><strong>ملاحظات مقترحة:</strong> ' + esc(row.proposed_tech_notes) + '</p>';
    }
    if (status === 'rejected' && row.rejection_notes) {
      html += '<p class="spec-edit-detail-note is-rejected"><strong>ملاحظة الرفض:</strong> ' + esc(row.rejection_notes) + '</p>';
    }
    if (status === 'approved' && row.reviewed_by) {
      html += '<p class="spec-edit-detail-note is-muted"><strong>اعتُمد بواسطة:</strong> ' + esc(row.reviewed_by) +
        (row.reviewed_at_label ? ' — ' + esc(row.reviewed_at_label) : '') + '</p>';
    }
    html += '</div>';
    return html;
  }

  function rowSearchHay(row) {
    return [
      row.patient_name,
      row.case_no,
      row.order_ref,
      row.requested_by,
      row.source_label,
      row.status_label,
    ].join(' ').toLowerCase();
  }

  function exportRowFromData(row) {
    var orig = (row.original_items || []).map(function (i) {
      return (i.name || i.stock_item_code) + ' × ' + i.qty;
    }).join(' | ');
    var prop = row.modified_summary || (row.modified_items || []).map(function (i) {
      if (i.change === 'removed') {
        return 'حذف: ' + (i.name || i.stock_item_code) + ' (×' + i.qty + ')';
      }
      if (i.change === 'updated') {
        return (i.name || i.stock_item_code) + ' × ' + i.qty + ' (كان ×' + i.previous_qty + ')';
      }
      return (i.name || i.stock_item_code) + ' × ' + i.qty;
    }).join(' | ') || (row.proposed_items || []).map(function (i) {
      return (i.name || i.stock_item_code) + ' × ' + i.qty;
    }).join(' | ');
    return {
      patient: row.patient_name || '—',
      case_no: row.case_no || '—',
      order_ref: row.order_ref || '—',
      source_label: row.source_label || '—',
      requested_at_label: row.requested_at_label || '—',
      status_label: row.status_label || row.status || '—',
      requested_by: row.requested_by || '—',
      original_summary: orig || '—',
      proposed_summary: prop || '—',
      proposed_tech_notes: row.proposed_tech_notes || '—',
      search: rowSearchHay(row) + ' ' + orig + ' ' + prop,
    };
  }

  function tableRowHtml(row) {
    var status = row.status || 'pending';
    var printBtn = status === 'pending' && row.source === 'spec' && row.tech_order_spec_id
      ? '<a href="/spec/spec/' + row.tech_order_spec_id + '/print?embed=1" target="_blank" rel="noopener" class="btn-action" title="طباعة التوصيف">🖨️</a>'
      : '';

    return '<tr class="spec-edit-req-row patient-track-row" data-id="' + row.id + '" data-status="' + status + '" data-search="' +
      esc(rowSearchHay(row)) + '" data-source="' + esc(row.source || 'spec') + '" data-tech-spec-id="' + esc(row.tech_order_spec_id || '') + '">' +
      '<td><strong>' + esc(row.patient_name || '—') + '</strong><div class="patient-track-cell-sub">' + esc(row.requested_by || '—') + '</div></td>' +
      '<td>' + esc(row.case_no || '—') + '</td>' +
      '<td>' + esc(row.order_ref || '—') + '</td>' +
      '<td><span class="badge" style="font-size:11px;">' + esc(row.source_label || '—') + '</span></td>' +
      '<td>' + esc(row.requested_at_label || '—') + '</td>' +
      '<td><span class="badge ' + esc(row.status_badge_class || '') + '">' + esc(row.status_label || status) + '</span></td>' +
      '<td><div class="spec-edit-req-actions">' +
      '<button type="button" class="btn-action spec-edit-detail-btn" data-id="' + row.id + '">📋 التفاصيل</button>' +
      printBtn + '</div></td></tr>';
  }

  function rebuildDetailSources(rows) {
    var host = $('specEditDetailSources');
    if (!host) return;
    host.innerHTML = rows.map(function (row) {
      return '<div class="spec-edit-detail-source" id="spec-edit-detail-source-' + row.id + '" hidden>' + detailHtml(row) + '</div>';
    }).join('');
  }

  function findRowData(id) {
    var rows = window.__SPEC_EDIT_REQ_ROWS || [];
    for (var i = 0; i < rows.length; i++) {
      if (String(rows[i].id) === String(id)) return rows[i];
    }
    return null;
  }

  function openDetailModal(id) {
    var row = findRowData(id);
    var source = $('spec-edit-detail-source-' + id);
    var body = $('specEditDetailBody');
    var modal = $('specEditDetailModal');
    var footer = $('specEditDetailFooter');
    var printLink = $('specEditDetailPrint');
    if (!body || !modal) return;

    activeDetailId = id;
    if ($('specEditDetailTitle')) {
      $('specEditDetailTitle').textContent = row
        ? '📋 تفاصيل طلب التعديل — ' + (row.patient_name || '')
        : '📋 تفاصيل طلب التعديل';
    }

    if (source) {
      body.innerHTML = source.innerHTML;
    } else if (row) {
      body.innerHTML = detailHtml(row);
    } else {
      body.innerHTML = '<p style="text-align:center;color:var(--text-muted);">تعذّر تحميل التفاصيل.</p>';
    }

    if (footer) {
      var isPending = row && row.status === 'pending';
      footer.style.display = isPending ? 'flex' : 'none';
      if ($('specEditDetailRejectNotes')) $('specEditDetailRejectNotes').value = '';
    }

    if (printLink) {
      var showPrint = row && row.status === 'pending' && row.source === 'spec' && row.tech_order_spec_id;
      printLink.style.display = showPrint ? '' : 'none';
      if (showPrint) printLink.href = '/spec/spec/' + row.tech_order_spec_id + '/print?embed=1';
    }

    modal.hidden = false;
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeDetailModal() {
    var modal = $('specEditDetailModal');
    if (!modal) return;
    modal.classList.remove('open');
    modal.hidden = true;
    document.body.style.overflow = '';
    activeDetailId = null;
    if ($('specEditDetailBody')) $('specEditDetailBody').innerHTML = '';
  }

  function bindTableActions() {
    document.querySelectorAll('.spec-edit-detail-btn').forEach(function (btn) {
      btn.onclick = function (e) {
        e.preventDefault();
        openDetailModal(btn.getAttribute('data-id'));
      };
    });
  }

  function approveRequest(id, source) {
    var confirmMsg = source === 'adjustments'
      ? 'اعتماد تعديل بنود المعدلات وتطبيقه على قائمة المواد؟'
      : 'اعتماد تعديل التوصيف وتطبيقه على قائمة المواد؟';
    if (!id || !window.confirm(confirmMsg)) return;

    jsonFetch('/admin/spec-edit-requests/' + id + '/approve', { method: 'POST' })
      .then(function (data) {
        toast(data.message || 'تم الاعتماد');
        closeDetailModal();
        loadList();
      })
      .catch(function (err) {
        toast(err.response?.data?.message || err.message || 'تعذّر الاعتماد', true);
      });
  }

  function rejectRequest(id) {
    var notes = ($('specEditDetailRejectNotes')?.value || '').trim() || null;
    jsonFetch('/admin/spec-edit-requests/' + id + '/reject', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ rejection_notes: notes }),
    })
      .then(function (data) {
        toast(data.message || 'تم الرفض');
        closeDetailModal();
        loadList();
      })
      .catch(function (err) {
        toast(err.response?.data?.message || err.message || 'تعذّر الرفض', true);
      });
  }

  function applyFilters() {
    var q = ($('specEditReqSearch')?.value || '').trim().toLowerCase();
    var visible = 0;
    document.querySelectorAll('.spec-edit-req-row').forEach(function (row) {
      var hay = row.getAttribute('data-search') || '';
      var show = !q || hay.indexOf(q) !== -1;
      row.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    if ($('specEditReqVisible')) $('specEditReqVisible').textContent = visible + ' ظاهر';
    if (window.TablePagination) {
      window.TablePagination.refreshById('specEditReqTable');
    }
  }

  function updateSidebarPendingBadge(count) {
    var badge = document.getElementById('sidebarSpecEditReqBadge');
    if (!badge) return;
    var n = Math.max(0, parseInt(count, 10) || 0);
    badge.textContent = String(n);
    badge.hidden = n === 0;
    badge.style.display = n === 0 ? 'none' : '';
  }

  function syncExportData(rows) {
    window.__SPEC_EDIT_REQ_ROWS = rows;
    window.__SPEC_EDIT_REQ_EXPORT = rows.map(exportRowFromData);
  }

  function loadList() {
    var status = $('specEditReqStatus')?.value || '';
    var search = $('specEditReqSearch')?.value || '';
    var params = new URLSearchParams();
    if (status) params.set('status', status);
    if (search) params.set('search', search);

    jsonFetch('/admin/spec-edit-requests/list?' + params.toString())
      .then(function (data) {
        var tbody = $('specEditReqTableBody');
        if (!tbody) return;
        var rows = data.data || [];
        syncExportData(rows);
        rebuildDetailSources(rows);

        if (!rows.length) {
          tbody.innerHTML = '<tr class="pagination-empty-row"><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted);">لا توجد طلبات.</td></tr>';
        } else {
          tbody.innerHTML = rows.map(tableRowHtml).join('');
          bindTableActions();
        }

        if ($('specEditReqCount')) $('specEditReqCount').textContent = rows.length + ' طلب';
        if (typeof data.pending === 'number') updateSidebarPendingBadge(data.pending);
        applyFilters();
      })
      .catch(function () {
        toast('تعذّر تحميل الطلبات', true);
      });
  }

  function exportSpecEditReq(type) {
    var allRows = window.__SPEC_EDIT_REQ_EXPORT || [];
    var term = ($('specEditReqSearch')?.value || '').trim().toLowerCase();
    var rows = allRows.filter(function (r) {
      return !term || (r.search || '').indexOf(term) !== -1;
    }).map(function (r) {
      return [
        r.patient, r.case_no, r.order_ref, r.source_label, r.requested_at_label,
        r.status_label, r.requested_by, r.original_summary, r.proposed_summary, r.proposed_tech_notes,
      ];
    });
    var headers = [
      'المريض', 'رقم الحالة', 'مرجع الطلب', 'المصدر', 'تاريخ الطلب',
      'الحالة', 'طلب بواسطة', 'البنود الحالية', 'البنود المعدلة', 'ملاحظات',
    ];
    if (!window.ExportKit) {
      alert('أداة التصدير غير متاحة');
      return;
    }
    if (type === 'excel') {
      ExportKit.toExcel(ExportKit.buildFilename('طلبات_تعديل_التوصيف'), headers, rows);
      return;
    }
    ExportKit.toPDF('طلبات تعديل التوصيف والمعدلات', headers, rows, 'لوحة الإدارة');
  }

  $('specEditDetailClose')?.addEventListener('click', closeDetailModal);
  $('specEditDetailModal')?.addEventListener('click', function (e) {
    if (e.target === $('specEditDetailModal')) closeDetailModal();
  });
  $('specEditDetailApprove')?.addEventListener('click', function () {
    if (!activeDetailId) return;
    var row = findRowData(activeDetailId);
    approveRequest(activeDetailId, row ? row.source : 'spec');
  });
  $('specEditDetailReject')?.addEventListener('click', function () {
    if (!activeDetailId) return;
    rejectRequest(activeDetailId);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && $('specEditDetailModal')?.classList.contains('open')) {
      closeDetailModal();
    }
  });

  $('specEditReqSearch')?.addEventListener('input', applyFilters);
  $('specEditReqStatus')?.addEventListener('change', loadList);
  $('specEditReqRefresh')?.addEventListener('click', loadList);
  $('btnSpecEditReqExcel')?.addEventListener('click', function () { exportSpecEditReq('excel'); });
  $('btnSpecEditReqPdf')?.addEventListener('click', function () { exportSpecEditReq('pdf'); });

  bindTableActions();
})();
