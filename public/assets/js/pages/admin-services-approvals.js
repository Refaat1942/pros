(function () {
  if (document.body.dataset.dashboard !== 'admin') return;
  if (document.body.dataset.activePage !== 'services-approvals') return;

  var csrf = document.querySelector('meta[name="csrf-token"]');
  var labels = window.__BENEFICIARY_LABELS || {};

  function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;'); }

  function load() {
    fetch('/admin/services-approvals/list', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        var tbody = document.getElementById('servicesApprovalsTable');
        var data = res.data || [];
        if (!data.length) {
          tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:24px;color:#64748b;">لا توجد تصديقات معلّقة.</td></tr>';
          return;
        }
        tbody.innerHTML = data.map(function (row) {
          var cat = row.patient && row.patient.military_beneficiary_category;
          return '<tr><td>' + esc(row.case && row.case.case_no) + '</td><td>' + esc(row.patient && row.patient.name) + '</td><td>' +
            esc(labels[cat] || cat || '—') + '</td><td>' + esc((row.created_at || '').slice(0, 16).replace('T', ' ')) +
            '</td><td><button type="button" class="btn-action success btn-approve-services" data-case-id="' +
            (row.case && row.case.id) + '">✅ تصديق</button></td></tr>';
        }).join('');
        tbody.querySelectorAll('.btn-approve-services').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var caseId = btn.getAttribute('data-case-id');
            if (!confirm('تصديق إدارة الخدمات وإصدار أمر الشغل؟')) return;
            fetch('/admin/services-approvals/' + caseId + '/approve', {
              method: 'POST',
              headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf.getAttribute('content'), 'X-Requested-With': 'XMLHttpRequest' },
              credentials: 'same-origin',
            }).then(function (r) { return r.ok ? r.json() : r.json().then(function (j) { throw j; }); })
              .then(function () { load(); })
              .catch(function (err) { alert((err && err.message) || 'فشل التصديق'); });
          });
        });
      });
  }

  document.getElementById('btnRefreshServicesApprovals').addEventListener('click', load);
  load();
})();
