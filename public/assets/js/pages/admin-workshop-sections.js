(function () {
  if (document.body.dataset.dashboard !== 'admin') return;
  if (document.body.dataset.activePage !== 'workshop-sections') return;

  var csrf = document.querySelector('meta[name="csrf-token"]');
  function headers(json) {
    var h = { Accept: 'application/json', 'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : '', 'X-Requested-With': 'XMLHttpRequest' };
    if (json) h['Content-Type'] = 'application/json';
    return h;
  }

  var technicians = window.__WORKSHOP_TECHNICIANS || [];
  var rows = window.__WORKSHOP_SECTIONS || [];

  function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;'); }

  function fillTechnicians(selected) {
    var sel = document.getElementById('workshopSectionTechnicians');
    if (!sel) return;
    sel.innerHTML = technicians.map(function (t) {
      var on = (selected || []).indexOf(t.id) !== -1 ? ' selected' : '';
      return '<option value="' + t.id + '"' + on + '>' + esc(t.name) + '</option>';
    }).join('');
  }

  function render() {
    var tbody = document.getElementById('workshopSectionsTable');
    var q = (document.getElementById('workshopSectionSearch') || {}).value || '';
    q = q.trim().toLowerCase();
    var filtered = rows.filter(function (r) {
      return !q || String(r.name || '').toLowerCase().indexOf(q) !== -1 || String(r.code || '').toLowerCase().indexOf(q) !== -1;
    });
    document.getElementById('workshopSectionCount').textContent = filtered.length + ' قسم';
    if (!filtered.length) {
      tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:24px;color:#64748b;">لا توجد أقسام.</td></tr>';
      return;
    }
    tbody.innerHTML = filtered.map(function (r) {
      var techs = (r.technicians || []).map(function (t) { return esc(t.name); }).join('، ') || '—';
      return '<tr><td><strong>' + esc(r.name) + '</strong></td><td>' + esc(r.code) + '</td><td>' + techs + '</td><td>' +
        (r.active ? '✅ نشط' : '⏸️ متوقف') + '</td><td><button type="button" class="btn-action btn-edit-section" data-id="' + r.id + '">✏️</button> ' +
        '<button type="button" class="btn-action danger btn-del-section" data-id="' + r.id + '">🗑️</button></td></tr>';
    }).join('');
    tbody.querySelectorAll('.btn-edit-section').forEach(function (btn) {
      btn.addEventListener('click', function () { openModal(parseInt(btn.getAttribute('data-id'), 10)); });
    });
    tbody.querySelectorAll('.btn-del-section').forEach(function (btn) {
      btn.addEventListener('click', function () { deleteSection(parseInt(btn.getAttribute('data-id'), 10)); });
    });
  }

  function openModal(id) {
    var modal = document.getElementById('workshopSectionModal');
    var row = id ? rows.find(function (r) { return r.id === id; }) : null;
    document.getElementById('workshopSectionId').value = row ? row.id : '';
    document.getElementById('workshopSectionModalTitle').textContent = row ? '✏️ تعديل قسم' : '➕ قسم ورشة';
    document.getElementById('workshopSectionName').value = row ? row.name : '';
    document.getElementById('workshopSectionCode').value = row ? (row.code || '') : '';
    document.getElementById('workshopSectionDescription').value = row ? (row.description || '') : '';
    document.getElementById('workshopSectionActive').checked = row ? !!row.active : true;
    fillTechnicians(row ? (row.technician_ids || []) : []);
    document.getElementById('workshopSectionError').style.display = 'none';
    modal.classList.add('open');
  }

  function closeModal() { document.getElementById('workshopSectionModal').classList.remove('open'); }

  function saveSection() {
    var id = document.getElementById('workshopSectionId').value;
    var sel = document.getElementById('workshopSectionTechnicians');
    var techIds = Array.from(sel.selectedOptions).map(function (o) { return parseInt(o.value, 10); });
    var payload = {
      name: document.getElementById('workshopSectionName').value.trim(),
      code: document.getElementById('workshopSectionCode').value.trim() || null,
      description: document.getElementById('workshopSectionDescription').value.trim() || null,
      active: document.getElementById('workshopSectionActive').checked,
      technician_ids: techIds,
    };
    var url = id ? '/admin/workshop-sections/' + id : '/admin/workshop-sections';
    fetch(url, { method: id ? 'PUT' : 'POST', headers: headers(true), credentials: 'same-origin', body: JSON.stringify(payload) })
      .then(function (r) { return r.ok ? r.json() : r.json().then(function (j) { throw j; }); })
      .then(function () { window.location.reload(); })
      .catch(function (err) {
        var el = document.getElementById('workshopSectionError');
        el.textContent = (err && err.message) ? err.message : 'تعذّر الحفظ';
        el.style.display = 'block';
      });
  }

  function deleteSection(id) {
    if (!confirm('حذف القسم؟')) return;
    fetch('/admin/workshop-sections/' + id, { method: 'DELETE', headers: headers(), credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : r.json().then(function (j) { throw j; }); })
      .then(function () { window.location.reload(); })
      .catch(function (err) { alert((err && err.message) || 'تعذّر الحذف'); });
  }

  document.getElementById('btnAddWorkshopSection').addEventListener('click', function () { openModal(null); });
  document.getElementById('closeWorkshopSectionModal').addEventListener('click', closeModal);
  document.getElementById('cancelWorkshopSectionModal').addEventListener('click', closeModal);
  document.getElementById('saveWorkshopSectionBtn').addEventListener('click', saveSection);
  document.getElementById('workshopSectionSearch').addEventListener('input', render);
  render();
})();
