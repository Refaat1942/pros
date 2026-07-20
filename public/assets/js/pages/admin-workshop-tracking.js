(function () {
  if (document.body.dataset.dashboard !== 'admin') return;
  if (document.body.dataset.activePage !== 'workshop-tracking') return;

  function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;'); }

  function load() {
    var sectionId = document.getElementById('trackingSectionFilter').value;
    var url = '/admin/workshop-tracking/list' + (sectionId ? '?section_id=' + encodeURIComponent(sectionId) : '');
    fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        var summary = res.summary || {};
        document.getElementById('workshopTrackingSummary').textContent =
          'WIP: ' + (summary.total_wip || 0) + ' — مُخصّص: ' + (summary.assigned || 0) + ' — غير مُخصّص: ' + (summary.unassigned || 0);
        var tbody = document.getElementById('workshopTrackingTable');
        var data = res.data || [];
        if (!data.length) {
          tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:24px;color:#64748b;">لا توجد أوامر تحت التشغيل.</td></tr>';
          return;
        }
        tbody.innerHTML = data.map(function (row) {
          return '<tr><td><strong>' + esc(row.case_no) + '</strong></td><td>' + esc(row.patient && row.patient.name) + '</td><td>' +
            esc(row.work_order_no) + '</td><td>' + esc(row.workshop_section && row.workshop_section.name) + '</td><td>' +
            esc(row.assigned_technician && row.assigned_technician.name) + '</td><td>' + esc(row.manufacturing_stage_label) + '</td><td>' +
            (row.workshop_progress_pct || 0) + '%</td><td>' + esc((row.updated_at || '').slice(0, 16).replace('T', ' ')) + '</td></tr>';
        }).join('');
      });
  }

  document.getElementById('btnRefreshWorkshopTracking').addEventListener('click', load);
  document.getElementById('trackingSectionFilter').addEventListener('change', load);
  load();
})();
