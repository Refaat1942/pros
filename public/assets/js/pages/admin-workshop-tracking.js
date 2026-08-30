(function () {
  if (document.body.dataset.dashboard !== 'admin') return;
  if (document.body.dataset.activePage !== 'workshop-tracking') return;

  function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;'); }

  function currentQueue() {
    var el = document.getElementById('trackingQueueFilter');
    return (el && el.value) || 'assignment';
  }

  function renderSummary(res, queue) {
    var summary = res.summary || {};
    var el = document.getElementById('workshopTrackingSummary');
    if (!el) return;

    if (queue === 'assignment') {
      el.textContent = 'طابور التخصيص: ' + (summary.total || 0) +
        ' — مُخصّص: ' + (summary.assigned || 0) +
        ' — بانتظار الاعتماد: ' + (summary.awaiting_approval || 0);
    } else {
      el.textContent = 'WIP: ' + (summary.total_wip || summary.total || 0) +
        ' — مُخصّص: ' + (summary.assigned || 0) +
        ' — غير مُخصّص: ' + (summary.unassigned || 0);
    }
  }

  function load() {
    var sectionId = document.getElementById('trackingSectionFilter').value;
    var queue = currentQueue();
    var params = new URLSearchParams();
    params.set('queue', queue);
    if (sectionId) params.set('section_id', sectionId);

    var phaseCol = document.getElementById('trackingPhaseCol');
    if (phaseCol) {
      phaseCol.textContent = queue === 'assignment' ? 'حالة التخصيص' : 'مرحلة التصنيع';
    }

    fetch('/admin/workshop-tracking/list?' + params.toString(), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        renderSummary(res, queue);
        var tbody = document.getElementById('workshopTrackingTable');
        var data = res.data || [];
        if (!data.length) {
          tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:24px;color:#64748b;">' +
            (queue === 'assignment' ? 'لا توجد أوامر بانتظار التخصيص.' : 'لا توجد أوامر تحت التشغيل.') +
            '</td></tr>';
          return;
        }
        tbody.innerHTML = data.map(function (row) {
          var phaseCell = queue === 'assignment'
            ? esc(row.assignment_status_label || '—')
            : esc(row.manufacturing_stage_label || '—');
          return '<tr><td><strong>' + esc(row.case_no) + '</strong></td><td>' + esc(row.patient && row.patient.name) + '</td><td>' +
            esc(row.work_order_no) + '</td><td>' + esc(row.workshop_section && row.workshop_section.name) + '</td><td>' +
            esc(row.assigned_technician && row.assigned_technician.name) + '</td><td>' + phaseCell + '</td><td>' +
            (row.workshop_progress_pct || 0) + '%</td><td>' + esc((row.updated_at || '').slice(0, 16).replace('T', ' ')) + '</td></tr>';
        }).join('');
      });
  }

  document.getElementById('btnRefreshWorkshopTracking').addEventListener('click', load);
  document.getElementById('trackingSectionFilter').addEventListener('change', load);
  document.getElementById('trackingQueueFilter').addEventListener('change', load);
  load();
})();
