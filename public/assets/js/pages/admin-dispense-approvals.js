(function () {
  if (document.body.dataset.dashboard !== 'admin') return;
  if (document.body.dataset.activePage !== 'dispense-approvals') return;

  var csrf = document.querySelector('meta[name="csrf-token"]');
  function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;'); }

  function load() {
    fetch('/admin/dispense-approvals/list', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        var tbody = document.getElementById('dispenseApprovalsTable');
        var data = res.data || [];
        if (!data.length) {
          tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:24px;color:#64748b;">لا توجد طلبات معلّقة.</td></tr>';
          return;
        }
        tbody.innerHTML = data.map(function (row) {
          return '<tr><td>' + esc(row.case && row.case.case_no) + '</td><td>' + esc(row.work_order_no) + '</td><td>' +
            esc(row.patient_name) + '</td><td>' + esc(row.bom && row.bom.bom_no) + '</td><td>' +
            esc(row.requested_by && row.requested_by.name) + '</td><td>' + esc((row.created_at || '').slice(0, 16).replace('T', ' ')) +
            '</td><td><button type="button" class="btn-action success btn-approve-dispense" data-id="' + row.id + '">✅ اعتماد</button> ' +
            '<button type="button" class="btn-action danger btn-reject-dispense" data-id="' + row.id + '">✕</button></td></tr>';
        }).join('');
        tbody.querySelectorAll('.btn-approve-dispense').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-id');
            if (!confirm('اعتماد الصرف وتنفيذ الخصم؟')) return;
            fetch('/admin/dispense-approvals/' + id + '/approve', {
              method: 'POST',
              headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf.getAttribute('content'), 'X-Requested-With': 'XMLHttpRequest' },
              credentials: 'same-origin',
            }).then(function (r) { return r.ok ? r.json() : r.json().then(function (j) { throw j; }); })
              .then(function () { load(); })
              .catch(function (err) { alert((err && err.message) || 'فشل الاعتماد'); });
          });
        });
        tbody.querySelectorAll('.btn-reject-dispense').forEach(function (btn) {
          btn.addEventListener('click', function () {
            document.getElementById('dispenseRejectId').value = btn.getAttribute('data-id');
            document.getElementById('dispenseRejectReason').value = '';
            document.getElementById('dispenseRejectModal').classList.add('open');
          });
        });
      });
  }

  document.getElementById('btnRefreshDispenseApprovals').addEventListener('click', load);
  document.getElementById('cancelDispenseReject').addEventListener('click', function () {
    document.getElementById('dispenseRejectModal').classList.remove('open');
  });
  document.getElementById('confirmDispenseReject').addEventListener('click', function () {
    var id = document.getElementById('dispenseRejectId').value;
    fetch('/admin/dispense-approvals/' + id + '/reject', {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf.getAttribute('content'), 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      body: JSON.stringify({ reason: document.getElementById('dispenseRejectReason').value.trim() }),
    }).then(function (r) { return r.ok ? r.json() : r.json().then(function (j) { throw j; }); })
      .then(function () { document.getElementById('dispenseRejectModal').classList.remove('open'); load(); })
      .catch(function (err) { alert((err && err.message) || 'فشل الرفض'); });
  });
  load();
})();
