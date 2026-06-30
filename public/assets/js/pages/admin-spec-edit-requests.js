(function () {
  if (document.body.dataset.activePage !== 'spec-edit-requests') return;

  var csrf = document.querySelector('meta[name="csrf-token"]');
  var csrfToken = csrf ? csrf.getAttribute('content') : '';

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

  function cardHtml(row) {
    var reasons = window.__specEditRejectionReasons || {};
    var reasonOptions = Object.keys(reasons).map(function (key) {
      return '<option value="' + key + '">' + reasons[key] + '</option>';
    }).join('');

    var original = (row.original_items || []).map(function (i) {
      return '<li>' + (i.name || i.stock_item_code) + ' × ' + i.qty + '</li>';
    }).join('');
    var proposed = (row.proposed_items || []).map(function (i) {
      return '<li>' + (i.name || i.stock_item_code) + ' × ' + i.qty + '</li>';
    }).join('');

    var actions = '';
    if (row.status === 'pending') {
      actions = '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;align-items:flex-end;">' +
        (row.tech_order_spec_id && row.source === 'spec'
          ? '<a href="/spec/spec/' + row.tech_order_spec_id + '/print?embed=1" target="_blank" rel="noopener" class="btn-action" style="text-decoration:none;">🖨️ طباعة التوصيف</a>'
          : '') +
        '<button type="button" class="btn-action success spec-edit-approve-btn" data-id="' + row.id + '" data-source="' + (row.source || 'spec') + '">✅ موافقة</button>' +
        '<select class="spec-edit-reject-reason" data-id="' + row.id + '" style="padding:8px;border:1px solid var(--border);border-radius:8px;font-size:12px;min-width:180px;">' +
        '<option value="">— سبب الرفض (اختياري) —</option>' + reasonOptions + '</select>' +
        '<input type="text" class="spec-edit-reject-notes" data-id="' + row.id + '" placeholder="ملاحظة إضافية (اختياري)" style="padding:8px;border:1px solid var(--border);border-radius:8px;font-size:12px;flex:1;min-width:160px;">' +
        '<button type="button" class="btn-action danger spec-edit-reject-btn" data-id="' + row.id + '">❌ رفض</button></div>';
    } else if (row.status === 'rejected') {
      actions = '<p style="font-size:12px;margin:12px 0 0;color:#b91c1c;background:#fef2f2;padding:8px 10px;border-radius:8px;">' +
        '<strong>سبب الرفض:</strong> ' + (row.rejection_reason_label || '—') +
        (row.rejection_notes ? ' — ' + row.rejection_notes : '') + '</p>';
    }

    return '<article class="spec-edit-req-card panel" style="margin:0;padding:16px;" data-id="' + row.id + '" data-status="' + row.status + '" data-search="' +
      (row.patient_name + ' ' + row.case_no + ' ' + row.order_ref).replace(/"/g, '') + '">' +
      '<div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:12px;margin-bottom:12px;">' +
      '<div><strong style="font-size:15px;">' + (row.patient_name || '—') + '</strong>' +
      '<div style="font-size:12px;color:var(--text-muted);margin-top:4px;">' + (row.case_no || '—') + ' · ' + (row.order_ref || '—') + ' · ' + (row.requested_at_label || '—') + '</div>' +
      '<div style="font-size:12px;color:var(--text-muted);margin-top:2px;">طلب بواسطة: ' + (row.requested_by || '—') +
      ' · <span class="badge" style="font-size:11px;">' + (row.source_label || 'توصيف فني') + '</span></div></div>' +
      '<span class="badge ' + (row.status_badge_class || '') + '">' + (row.status_label || row.status) + '</span></div>' +
      '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">' +
      '<div><div style="font-size:11px;font-weight:800;color:var(--text-muted);margin-bottom:6px;">الحالي</div><ul style="margin:0;padding-right:18px;font-size:13px;">' + original + '</ul></div>' +
      '<div><div style="font-size:11px;font-weight:800;color:var(--primary);margin-bottom:6px;">المقترح</div><ul style="margin:0;padding-right:18px;font-size:13px;">' + proposed + '</ul></div></div>' +
      (row.proposed_tech_notes ? '<p style="font-size:12px;margin:0 0 12px;color:var(--text-muted);"><strong>ملاحظات مقترحة:</strong> ' + row.proposed_tech_notes + '</p>' : '') +
      actions + '</article>';
  }

  function bindCardActions() {
    document.querySelectorAll('.spec-edit-approve-btn').forEach(function (btn) {
      btn.onclick = function () {
        var id = btn.getAttribute('data-id');
        var source = btn.getAttribute('data-source') || 'spec';
        var confirmMsg = source === 'adjustments'
          ? 'اعتماد تعديل بنود المعدلات وتطبيقه على قائمة المواد؟'
          : 'اعتماد تعديل التوصيف وتطبيقه على قائمة المواد؟';
        if (!id || !window.confirm(confirmMsg)) return;
        jsonFetch('/admin/spec-edit-requests/' + id + '/approve', { method: 'POST' })
          .then(function (data) {
            toast(data.message || 'تم الاعتماد');
            loadList();
          })
          .catch(function (err) {
            toast(err.response?.data?.message || err.message || 'تعذّر الاعتماد', true);
          });
      };
    });

    document.querySelectorAll('.spec-edit-reject-btn').forEach(function (btn) {
      btn.onclick = function () {
        var id = btn.getAttribute('data-id');
        var reasonEl = document.querySelector('.spec-edit-reject-reason[data-id="' + id + '"]');
        var notesEl = document.querySelector('.spec-edit-reject-notes[data-id="' + id + '"]');
        var reason = reasonEl ? reasonEl.value : '';
        jsonFetch('/admin/spec-edit-requests/' + id + '/reject', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            rejection_reason_key: reason || null,
            rejection_notes: notesEl ? notesEl.value.trim() || null : null,
          }),
        })
          .then(function (data) {
            toast(data.message || 'تم الرفض');
            loadList();
          })
          .catch(function (err) {
            toast(err.response?.data?.message || err.message || 'تعذّر الرفض', true);
          });
      };
    });
  }

  function applyFilters() {
    var q = ($('specEditReqSearch')?.value || '').trim().toLowerCase();
    var status = $('specEditReqStatus')?.value || '';
    var visible = 0;
    document.querySelectorAll('.spec-edit-req-card').forEach(function (card) {
      var hay = (card.getAttribute('data-search') || '').toLowerCase();
      var st = card.getAttribute('data-status') || '';
      var show = (!q || hay.indexOf(q) !== -1) && (!status || st === status);
      card.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    if ($('specEditReqVisible')) $('specEditReqVisible').textContent = visible + ' طلب';
  }

  function loadList() {
    var status = $('specEditReqStatus')?.value || '';
    var search = $('specEditReqSearch')?.value || '';
    var params = new URLSearchParams();
    if (status) params.set('status', status);
    if (search) params.set('search', search);

    jsonFetch('/admin/spec-edit-requests/list?' + params.toString())
      .then(function (data) {
        var list = $('specEditReqList');
        if (!list) return;
        var rows = data.data || [];
        if (!rows.length) {
          list.innerHTML = '<p class="text-center text-muted py-10">لا توجد طلبات.</p>';
        } else {
          list.innerHTML = rows.map(cardHtml).join('');
          bindCardActions();
        }
        if ($('specEditReqCount')) $('specEditReqCount').textContent = rows.length + ' طلب';
        applyFilters();
      })
      .catch(function () {
        toast('تعذّر تحميل الطلبات', true);
      });
  }

  $('specEditReqSearch')?.addEventListener('input', applyFilters);
  $('specEditReqStatus')?.addEventListener('change', loadList);
  $('specEditReqRefresh')?.addEventListener('click', loadList);
  bindCardActions();
})();
