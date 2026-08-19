(function () {
  var dash = document.body.dataset.dashboard;
  var page = document.body.dataset.activePage;
  var allowed = (dash === 'admin' && page === 'workshop-sections')
    || (dash === 'workshop' && page === 'sections');
  if (!allowed) return;

  var sectionsApi = (window.__WORKSHOP_SECTIONS_API || '/admin/workshop-sections').replace(/\/$/, '');
  var techniciansApi = (window.__WORKSHOP_TECHNICIANS_API || '/admin/workshop-technicians').replace(/\/$/, '');

  var csrf = document.querySelector('meta[name="csrf-token"]');
  function headers(json) {
    var h = { Accept: 'application/json', 'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : '', 'X-Requested-With': 'XMLHttpRequest' };
    if (json) h['Content-Type'] = 'application/json';
    return h;
  }

  function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;'); }

  function showError(elId, err) {
    var el = document.getElementById(elId);
    if (!el) return;
    var msg = (err && err.message) ? err.message : 'تعذّر الحفظ';
    if (err && err.errors) {
      var first = Object.values(err.errors)[0];
      if (Array.isArray(first) && first[0]) msg = first[0];
    }
    el.textContent = msg;
    el.style.display = 'block';
  }

  // ── Tabs ────────────────────────────────────────────────────────────────
  var tabBtns = document.querySelectorAll('[data-ws-tab]');
  tabBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var tab = btn.getAttribute('data-ws-tab');
      tabBtns.forEach(function (b) {
        var active = b.getAttribute('data-ws-tab') === tab;
        b.classList.toggle('active', active);
        b.classList.toggle('bg-white', active);
        b.classList.toggle('text-violet-900', active);
        b.classList.toggle('border-slate-200', active);
        b.classList.toggle('-mb-px', active);
        b.classList.toggle('border-b-0', active);
        b.classList.toggle('border-transparent', !active);
        b.classList.toggle('text-slate-600', !active);
      });
      document.getElementById('wsTabSections').classList.toggle('hidden', tab !== 'sections');
      document.getElementById('wsTabTechnicians').classList.toggle('hidden', tab !== 'technicians');
    });
  });

  // ── Sections ──────────────────────────────────────────────────────────────
  var sectionRows = window.__WORKSHOP_SECTIONS || [];

  function renderSections() {
    var tbody = document.getElementById('workshopSectionsTable');
    if (!tbody) return;
    var q = (document.getElementById('workshopSectionSearch') || {}).value || '';
    q = q.trim().toLowerCase();
    var filtered = sectionRows.filter(function (r) {
      return !q || String(r.name || '').toLowerCase().indexOf(q) !== -1 || String(r.code || '').toLowerCase().indexOf(q) !== -1;
    });
    var countEl = document.getElementById('workshopSectionCount');
    if (countEl) countEl.textContent = filtered.length + ' قسم';
    if (!filtered.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-12 text-center text-slate-400">لا توجد أقسام — اضغط «➕ إضافة قسم».</td></tr>';
      return;
    }
    tbody.innerHTML = filtered.map(function (r) {
      var desc = r.description ? esc(r.description) : '<span class="text-slate-400">—</span>';
      return '<tr class="hover:bg-slate-50">' +
        '<td class="px-4 py-3"><strong class="text-slate-800">' + esc(r.name) + '</strong></td>' +
        '<td class="px-4 py-3 font-mono text-xs text-slate-500">' + esc(r.code) + '</td>' +
        '<td class="px-4 py-3 text-slate-600 text-xs">' + desc + '</td>' +
        '<td class="px-4 py-3">' + (r.active ? '<span class="text-emerald-700 font-bold">✅ نشط</span>' : '<span class="text-slate-500">⏸️ متوقف</span>') + '</td>' +
        '<td class="px-4 py-3 whitespace-nowrap space-x-1 space-x-reverse">' +
          '<button type="button" class="btn-edit-section inline-flex items-center rounded-lg border border-violet-300 bg-violet-50 text-violet-900 px-3 py-1.5 text-xs font-bold hover:bg-violet-100" data-id="' + r.id + '">✏️ تعديل</button> ' +
          '<button type="button" class="btn-del-section inline-flex items-center rounded-lg border border-red-200 bg-red-50 text-red-700 px-2 py-1.5 text-xs font-bold hover:bg-red-100" data-id="' + r.id + '" title="حذف">🗑️</button>' +
        '</td></tr>';
    }).join('');
    tbody.querySelectorAll('.btn-edit-section').forEach(function (btn) {
      btn.addEventListener('click', function () { openSectionModal(parseInt(btn.getAttribute('data-id'), 10)); });
    });
    tbody.querySelectorAll('.btn-del-section').forEach(function (btn) {
      btn.addEventListener('click', function () { deleteSection(parseInt(btn.getAttribute('data-id'), 10)); });
    });
  }

  function openSectionModal(id) {
    var modal = document.getElementById('workshopSectionModal');
    var row = id ? sectionRows.find(function (r) { return r.id === id; }) : null;
    document.getElementById('workshopSectionId').value = row ? row.id : '';
    document.getElementById('workshopSectionModalTitle').textContent = row ? '✏️ تعديل القسم' : '➕ قسم إنتاج جديد';
    var hint = document.getElementById('workshopSectionModalHint');
    if (hint) hint.textContent = row ? 'عدّل بيانات القسم.' : 'أدخل بيانات القسم الجديد.';
    document.getElementById('workshopSectionName').value = row ? row.name : '';
    document.getElementById('workshopSectionCode').value = row ? (row.code || '') : '';
    document.getElementById('workshopSectionDescription').value = row ? (row.description || '') : '';
    document.getElementById('workshopSectionActive').checked = row ? !!row.active : true;
    document.getElementById('workshopSectionError').style.display = 'none';
    modal.classList.add('open');
  }

  function closeSectionModal() { document.getElementById('workshopSectionModal').classList.remove('open'); }

  function saveSection() {
    var id = document.getElementById('workshopSectionId').value;
    var payload = {
      name: document.getElementById('workshopSectionName').value.trim(),
      code: document.getElementById('workshopSectionCode').value.trim() || null,
      description: document.getElementById('workshopSectionDescription').value.trim() || null,
      active: document.getElementById('workshopSectionActive').checked,
    };
    var url = id ? sectionsApi + '/' + id : sectionsApi;
    fetch(url, { method: id ? 'PUT' : 'POST', headers: headers(true), credentials: 'same-origin', body: JSON.stringify(payload) })
      .then(function (r) { return r.ok ? r.json() : r.json().then(function (j) { throw j; }); })
      .then(function () { window.location.reload(); })
      .catch(function (err) { showError('workshopSectionError', err); });
  }

  function deleteSection(id) {
    if (!confirm('حذف القسم؟')) return;
    fetch(sectionsApi + '/' + id, { method: 'DELETE', headers: headers(), credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : r.json().then(function (j) { throw j; }); })
      .then(function () { window.location.reload(); })
      .catch(function (err) { alert((err && err.message) || 'تعذّر الحذف'); });
  }

  // ── Technicians ───────────────────────────────────────────────────────────
  var techRows = window.__WORKSHOP_TECHNICIANS || [];

  function fillSectionSelect(selected) {
    var sel = document.getElementById('workshopTechnicianSections');
    if (!sel) return;
    if (!sectionRows.length) {
      sel.innerHTML = '<option value="" disabled selected>لا توجد أقسام — أنشئ قسماً أولاً</option>';
      return;
    }
    sel.innerHTML = sectionRows.map(function (s) {
      var on = (selected || []).indexOf(s.id) !== -1 ? ' selected' : '';
      return '<option value="' + s.id + '"' + on + '>' + esc(s.name) + '</option>';
    }).join('');
  }

  function renderTechnicians() {
    var tbody = document.getElementById('workshopTechniciansTable');
    if (!tbody) return;
    var q = (document.getElementById('workshopTechnicianSearch') || {}).value || '';
    q = q.trim().toLowerCase();
    var filtered = techRows.filter(function (r) {
      return !q || String(r.name || '').toLowerCase().indexOf(q) !== -1 || String(r.username || '').toLowerCase().indexOf(q) !== -1;
    });
    var countEl = document.getElementById('workshopTechnicianCount');
    if (countEl) countEl.textContent = filtered.length + ' فني';
    if (!filtered.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-12 text-center text-slate-400">لا يوجد فنيون — اضغط «➕ إضافة فني».</td></tr>';
      return;
    }
    tbody.innerHTML = filtered.map(function (r) {
      var secs = (r.sections || []);
      var secCell = secs.length
        ? '<span class="text-slate-800">' + secs.map(function (s) { return esc(s.name); }).join('، ') + '</span>'
        : '<span class="text-amber-700 font-bold text-xs">— غير مربوط بقسم —</span>';
      var active = r.active !== undefined ? r.active : (r.status === 'active');
      return '<tr class="hover:bg-slate-50">' +
        '<td class="px-4 py-3"><strong class="text-slate-800">' + esc(r.name) + '</strong></td>' +
        '<td class="px-4 py-3 font-mono text-xs text-slate-500" dir="ltr">' + esc(r.username) + '</td>' +
        '<td class="px-4 py-3">' + secCell + '</td>' +
        '<td class="px-4 py-3">' + (active ? '<span class="text-emerald-700 font-bold">✅ نشط</span>' : '<span class="text-slate-500">⏸️ متوقف</span>') + '</td>' +
        '<td class="px-4 py-3 whitespace-nowrap space-x-1 space-x-reverse">' +
          '<button type="button" class="btn-edit-tech inline-flex items-center rounded-lg border border-violet-300 bg-violet-50 text-violet-900 px-3 py-1.5 text-xs font-bold hover:bg-violet-100" data-id="' + r.id + '">✏️ تعديل</button> ' +
          '<button type="button" class="btn-del-tech inline-flex items-center rounded-lg border border-red-200 bg-red-50 text-red-700 px-2 py-1.5 text-xs font-bold hover:bg-red-100" data-id="' + r.id + '" title="حذف">🗑️</button>' +
        '</td></tr>';
    }).join('');
    tbody.querySelectorAll('.btn-edit-tech').forEach(function (btn) {
      btn.addEventListener('click', function () { openTechnicianModal(parseInt(btn.getAttribute('data-id'), 10)); });
    });
    tbody.querySelectorAll('.btn-del-tech').forEach(function (btn) {
      btn.addEventListener('click', function () { deleteTechnician(parseInt(btn.getAttribute('data-id'), 10)); });
    });
  }

  function openTechnicianModal(id) {
    var modal = document.getElementById('workshopTechnicianModal');
    var row = id ? techRows.find(function (r) { return r.id === id; }) : null;
    document.getElementById('workshopTechnicianId').value = row ? row.id : '';
    document.getElementById('workshopTechnicianModalTitle').textContent = row ? '✏️ تعديل الفني' : '➕ فني جديد';
    var hint = document.getElementById('workshopTechnicianModalHint');
    if (hint) hint.textContent = row ? 'عدّل بيانات الفني أو غيّر الأقسام المربوطة.' : 'أدخل بيانات الفني واربطه بالأقسام.';
    document.getElementById('workshopTechnicianName').value = row ? row.name : '';
    document.getElementById('workshopTechnicianUsername').value = row ? row.username : '';
    document.getElementById('workshopTechnicianPassword').value = '';
    var pwdReq = document.getElementById('workshopTechnicianPasswordRequired');
    if (pwdReq) pwdReq.style.display = row ? 'none' : 'inline';
    document.getElementById('workshopTechnicianPassword').placeholder = row ? 'اتركه فارغاً للإبقاء على كلمة المرور الحالية' : '6 أحرف على الأقل';
    var active = row ? (row.active !== undefined ? row.active : row.status === 'active') : true;
    document.getElementById('workshopTechnicianActive').checked = active;
    fillSectionSelect(row ? (row.section_ids || []) : []);
    document.getElementById('workshopTechnicianError').style.display = 'none';
    modal.classList.add('open');
  }

  function closeTechnicianModal() { document.getElementById('workshopTechnicianModal').classList.remove('open'); }

  function saveTechnician() {
    var id = document.getElementById('workshopTechnicianId').value;
    var sel = document.getElementById('workshopTechnicianSections');
    var sectionIds = Array.from(sel.selectedOptions).map(function (o) { return parseInt(o.value, 10); });
    var payload = {
      name: document.getElementById('workshopTechnicianName').value.trim(),
      username: document.getElementById('workshopTechnicianUsername').value.trim(),
      status: document.getElementById('workshopTechnicianActive').checked ? 'active' : 'inactive',
      section_ids: sectionIds,
    };
    var pwd = document.getElementById('workshopTechnicianPassword').value;
    if (pwd) payload.password = pwd;
    if (!id && !pwd) {
      showError('workshopTechnicianError', { message: 'كلمة المرور مطلوبة عند إضافة فني جديد.' });
      return;
    }
    var url = id ? techniciansApi + '/' + id : techniciansApi;
    fetch(url, { method: id ? 'PUT' : 'POST', headers: headers(true), credentials: 'same-origin', body: JSON.stringify(payload) })
      .then(function (r) { return r.ok ? r.json() : r.json().then(function (j) { throw j; }); })
      .then(function () { window.location.reload(); })
      .catch(function (err) { showError('workshopTechnicianError', err); });
  }

  function deleteTechnician(id) {
    if (!confirm('حذف الفني؟')) return;
    fetch(techniciansApi + '/' + id, { method: 'DELETE', headers: headers(), credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : r.json().then(function (j) { throw j; }); })
      .then(function () { window.location.reload(); })
      .catch(function (err) { alert((err && err.message) || 'تعذّر الحذف'); });
  }

  // ── Bind events ───────────────────────────────────────────────────────────
  document.getElementById('btnAddWorkshopSection').addEventListener('click', function () { openSectionModal(null); });
  document.getElementById('closeWorkshopSectionModal').addEventListener('click', closeSectionModal);
  document.getElementById('cancelWorkshopSectionModal').addEventListener('click', closeSectionModal);
  document.getElementById('saveWorkshopSectionBtn').addEventListener('click', saveSection);
  document.getElementById('workshopSectionSearch').addEventListener('input', renderSections);

  document.getElementById('btnAddWorkshopTechnician').addEventListener('click', function () { openTechnicianModal(null); });
  document.getElementById('closeWorkshopTechnicianModal').addEventListener('click', closeTechnicianModal);
  document.getElementById('cancelWorkshopTechnicianModal').addEventListener('click', closeTechnicianModal);
  document.getElementById('saveWorkshopTechnicianBtn').addEventListener('click', saveTechnician);
  document.getElementById('workshopTechnicianSearch').addEventListener('input', renderTechnicians);

  renderSections();
  renderTechnicians();
})();
