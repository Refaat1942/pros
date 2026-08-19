(function () {
  var dash = document.body.dataset.dashboard;
  var page = document.body.dataset.activePage;
  var allowed = (dash === 'admin' && page === 'workshop-sections')
    || (dash === 'workshop' && page === 'sections');
  if (!allowed) return;

  var apiBase = (window.__WORKSHOP_SECTIONS_API || '/admin/workshop-sections').replace(/\/$/, '');

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
    if (!technicians.length) {
      sel.innerHTML = '<option value="" disabled selected>لا يوجد فنيون — أضف موظفين بدور «قسم الإنتاج» من صفحة الموظفين</option>';
      return;
    }
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
    var countEl = document.getElementById('workshopSectionCount');
    if (countEl) countEl.textContent = filtered.length + ' قسم';
    if (!filtered.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-12 text-center text-slate-400">لا توجد أقسام — اضغط «➕ إضافة قسم» أعلاه.</td></tr>';
      return;
    }
    tbody.innerHTML = filtered.map(function (r) {
      var techList = (r.technicians || []);
      var techs = techList.map(function (t) { return esc(t.name); }).join('، ');
      var techCell = techList.length
        ? '<span class="text-slate-800">' + techs + '</span>'
        : '<span class="text-amber-700 font-bold text-xs">— لم يُربَط فني — اضغط تعديل</span>';
      return '<tr class="hover:bg-slate-50">' +
        '<td class="px-4 py-3"><strong class="text-slate-800">' + esc(r.name) + '</strong></td>' +
        '<td class="px-4 py-3 font-mono text-xs text-slate-500">' + esc(r.code) + '</td>' +
        '<td class="px-4 py-3">' + techCell + '</td>' +
        '<td class="px-4 py-3">' + (r.active ? '<span class="text-emerald-700 font-bold">✅ نشط</span>' : '<span class="text-slate-500">⏸️ متوقف</span>') + '</td>' +
        '<td class="px-4 py-3 whitespace-nowrap space-x-1 space-x-reverse">' +
          '<button type="button" class="btn-edit-section inline-flex items-center rounded-lg border border-violet-300 bg-violet-50 text-violet-900 px-3 py-1.5 text-xs font-bold hover:bg-violet-100" data-id="' + r.id + '">✏️ تعديل / ربط فنيين</button> ' +
          '<button type="button" class="btn-del-section inline-flex items-center rounded-lg border border-red-200 bg-red-50 text-red-700 px-2 py-1.5 text-xs font-bold hover:bg-red-100" data-id="' + r.id + '" title="حذف">🗑️</button>' +
        '</td></tr>';
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
    document.getElementById('workshopSectionModalTitle').textContent = row ? '✏️ تعديل القسم وربط الفنيين' : '➕ قسم إنتاج جديد';
    var hint = document.getElementById('workshopSectionModalHint');
    if (hint) {
      hint.textContent = row
        ? 'عدّل بيانات القسم أو غيّر الفنيين المربوطين به.'
        : 'أدخل بيانات القسم واختر الفنيين المسؤولين عنه.';
    }
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
    var url = id ? apiBase + '/' + id : apiBase;
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
    fetch(apiBase + '/' + id, { method: 'DELETE', headers: headers(), credentials: 'same-origin' })
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
