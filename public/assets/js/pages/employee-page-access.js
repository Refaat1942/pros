/**
 * مستوى صلاحية الموظف — مدير قسم vs موظف بصفحات محددة.
 */
(function () {
  var form = document.getElementById('employeeForm');
  if (!form || !form.dataset.rolePagesUrl) return;

  var block = document.getElementById('employeeAccessTierBlock');
  var wrap = document.getElementById('employeeAllowedPagesWrap');
  var hiddenInput = document.getElementById('employeeAllowedPagesInput');
  var roleSelect = document.getElementById('employeeRoleSelect');
  if (!block || !wrap || !hiddenInput || !roleSelect) return;

  var rolePagesUrl = form.dataset.rolePagesUrl;
  var editAllowed = [];
  try {
    editAllowed = JSON.parse(form.dataset.editAllowedPages || '[]');
  } catch (e) { editAllowed = []; }

  function esc(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }

  function selectedTier() {
    var checked = form.querySelector('input[name="access_tier"]:checked');
    return checked ? checked.value : 'department_admin';
  }

  function syncHidden() {
    if (selectedTier() !== 'department_staff') {
      hiddenInput.value = '';
      return;
    }
    var keys = [];
    wrap.querySelectorAll('.emp-page-chk:checked').forEach(function (chk) {
      keys.push(chk.getAttribute('data-page'));
    });
    hiddenInput.value = JSON.stringify(keys);
  }

  function renderPages(pages, defaults) {
    if (!pages.length) {
      wrap.innerHTML = '<p class="employee-catalog-visibility-hint" style="padding:12px;">لا توجد صفحات قابلة للتخصيص لهذا الدور.</p>';
      return;
    }

    var selected = editAllowed.length ? editAllowed : defaults;
    wrap.innerHTML = pages.map(function (p) {
      var on = selected.indexOf(p.key) !== -1 ? ' checked' : '';
      return '<label class="emp-page-row">' +
        '<input type="checkbox" class="emp-page-chk" data-page="' + esc(p.key) + '"' + on + '> ' +
        '<span>' + esc(p.icon) + ' ' + esc(p.label) + '</span></label>';
    }).join('');

    wrap.querySelectorAll('.emp-page-chk').forEach(function (chk) {
      chk.addEventListener('change', syncHidden);
    });
    syncHidden();
  }

  function loadPages(roleId) {
    if (!roleId) {
      block.style.display = 'none';
      return;
    }

    fetch(rolePagesUrl + '/' + roleId, { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.pages || !data.pages.length) {
          block.style.display = 'none';
          return;
        }
        block.style.display = '';
        renderPages(data.pages, data.staff_defaults || []);
        toggleStaffPages();
      })
      .catch(function () {
        block.style.display = 'none';
      });
  }

  function toggleStaffPages() {
    var isStaff = selectedTier() === 'department_staff';
    wrap.style.display = isStaff ? '' : 'none';
    syncHidden();
  }

  roleSelect.addEventListener('change', function () {
    editAllowed = [];
    loadPages(roleSelect.value);
  });

  form.querySelectorAll('input[name="access_tier"]').forEach(function (radio) {
    radio.addEventListener('change', toggleStaffPages);
  });

  form.addEventListener('submit', syncHidden);

  if (roleSelect.value) {
    loadPages(roleSelect.value);
  }
})();
