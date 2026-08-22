(function () {
  if (document.body.dataset.dashboard !== 'admin') return;
  if (document.body.dataset.activePage !== 'dispense-approvals') return;

  var csrf = document.querySelector('meta[name="csrf-token"]');
  // M-15: قراءة رمز CSRF بأمان حتى لو غاب الـ meta (يمنع خطأ JS صريحاً).
  function csrfToken() { return csrf ? csrf.getAttribute('content') : ''; }
  function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;'); }

  function countScansByCode(lines, bomItems) {
    var counts = {};
    (lines || []).forEach(function (code) {
      var key = String(code || '').toUpperCase();
      counts[key] = (counts[key] || 0) + 1;
    });
    return (bomItems || []).map(function (item) {
      var code = String(item.stock_item_code || '').toUpperCase();
      return { item: item, scanned: counts[code] || 0 };
    });
  }

  function openDetail(id) {
    fetch('/admin/dispense-approvals/' + id, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        var req = res.request || {};
        var lines = req.lines || [];
        var items = countScansByCode(lines, req.bom_items || []);
        var meta = document.getElementById('dispenseDetailMeta');
        var tbody = document.getElementById('dispenseDetailItems');
        if (meta) {
          meta.innerHTML =
            '<div><strong>الحالة:</strong> ' + esc(req.case && req.case.case_no) + '</div>' +
            '<div><strong>WO:</strong> ' + esc(req.work_order_no) + '</div>' +
            '<div><strong>المريض:</strong> ' + esc(req.patient && req.patient.name) + '</div>' +
            '<div><strong>BOM:</strong> ' + esc(req.bom && req.bom.bom_no) + '</div>' +
            '<div><strong>عدد المسح (كود الصنف):</strong> ' + lines.length + '</div>';
        }
        if (tbody) {
          tbody.innerHTML = items.length
            ? items.map(function (row) {
              var it = row.item;
              var ok = row.scanned >= (it.qty || 0);
              return '<tr><td>' + esc(it.stock_item_code) + '</td><td>' + esc(it.name) + '</td><td>' +
                esc(it.qty) + '</td><td>' + esc(row.scanned) + '</td><td>' +
                (ok ? '✅' : '⚠️') + '</td></tr>';
            }).join('')
            : '<tr><td colspan="5" style="text-align:center;padding:16px;color:#64748b;">لا توجد بنود.</td></tr>';
        }
        var scansEl = document.getElementById('dispenseDetailScans');
        if (scansEl) {
          scansEl.innerHTML = lines.length
            ? lines.map(function (c, i) { return '<span class="dispense-scan-chip">' + esc(c) + '</span>'; }).join('')
            : '<span class="text-muted">—</span>';
        }
        document.getElementById('dispenseDetailModal').classList.add('open');
      })
      .catch(function () { alert('تعذّر تحميل تفاصيل الطلب'); });
  }

  function load() {
    fetch('/admin/dispense-approvals/list', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(function (res) {
        var tbody = document.getElementById('dispenseApprovalsTable');
        var data = res.data || [];
        if (!data.length) {
          tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:24px;color:#64748b;">لا توجد طلبات معلّقة.</td></tr>';
          return;
        }
        tbody.innerHTML = data.map(function (row) {
          return '<tr><td>' + esc(row.case && row.case.case_no) + '</td><td>' + esc(row.work_order_no) + '</td><td>' +
            esc(row.patient_name) + '</td><td>' + esc(row.bom && row.bom.bom_no) + '</td><td>' +
            esc(row.lines_count || 0) + '</td><td>' +
            esc(row.requested_by && row.requested_by.name) + '</td><td>' + esc((row.created_at || '').slice(0, 16).replace('T', ' ')) +
            '</td><td style="white-space:nowrap;">' +
            '<button type="button" class="btn-action btn-view-dispense" data-id="' + row.id + '">👁️ عرض</button> ' +
            '<button type="button" class="btn-action success btn-approve-dispense" data-id="' + row.id + '">✅ اعتماد</button> ' +
            '<button type="button" class="btn-action danger btn-reject-dispense" data-id="' + row.id + '">✕ رفض</button></td></tr>';
        }).join('');
        tbody.querySelectorAll('.btn-view-dispense').forEach(function (btn) {
          btn.addEventListener('click', function () { openDetail(btn.getAttribute('data-id')); });
        });
        tbody.querySelectorAll('.btn-approve-dispense').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-id');
            if (!confirm('اعتماد الصرف وتنفيذ الخصم؟')) return;
            // M-15: منع النقر المزدوج — تعطيل الزر أثناء الطلب.
            if (btn.disabled) return;
            btn.disabled = true;
            fetch('/admin/dispense-approvals/' + id + '/approve', {
              method: 'POST',
              headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
              credentials: 'same-origin',
            }).then(function (r) { return r.ok ? r.json() : r.json().then(function (j) { throw j; }); })
              .then(function () { load(); })
              .catch(function (err) {
                btn.disabled = false;
                alert((err && err.message) || 'فشل الاعتماد');
              });
          });
        });
        tbody.querySelectorAll('.btn-reject-dispense').forEach(function (btn) {
          btn.addEventListener('click', function () {
            document.getElementById('dispenseRejectId').value = btn.getAttribute('data-id');
            document.getElementById('dispenseRejectReason').value = '';
            document.getElementById('dispenseRejectModal').classList.add('open');
          });
        });
      })
      // M-16: لا يبقى الجدول عالقاً بصمت عند فشل التحميل (500/419/شبكة).
      .catch(function () {
        var tbody = document.getElementById('dispenseApprovalsTable');
        if (tbody) {
          tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:24px;color:#dc2626;">تعذّر تحميل طلبات الصرف — حدّث الصفحة أو أعد المحاولة.</td></tr>';
        }
      });
  }

  document.getElementById('btnRefreshDispenseApprovals').addEventListener('click', load);
  document.getElementById('cancelDispenseReject').addEventListener('click', function () {
    document.getElementById('dispenseRejectModal').classList.remove('open');
  });
  document.getElementById('closeDispenseDetail').addEventListener('click', function () {
    document.getElementById('dispenseDetailModal').classList.remove('open');
  });
  document.getElementById('confirmDispenseReject').addEventListener('click', function () {
    var id = document.getElementById('dispenseRejectId').value;
    var reason = document.getElementById('dispenseRejectReason').value.trim();
    if (!reason) {
      alert('سبب الرفض مطلوب — اكتب ما يجب تعديله في المخزن.');
      return;
    }
    fetch('/admin/dispense-approvals/' + id + '/reject', {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      body: JSON.stringify({ reason: reason }),
    }).then(function (r) { return r.ok ? r.json() : r.json().then(function (j) { throw j; }); })
      .then(function () { document.getElementById('dispenseRejectModal').classList.remove('open'); load(); })
      .catch(function (err) { alert((err && err.message) || 'فشل الرفض'); });
  });
  load();
})();
