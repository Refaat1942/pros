/**
 * إدارة موظفي القسم — نافذة الإضافة/التعديل، الحذف، وإعادة تعيين كلمة المرور.
 */
(function () {
  var cfg = window.__DEPT_STAFF || {};
  var baseUrl = (cfg.baseUrl || '').replace(/\/$/, '');

  function $(id) {
    return document.getElementById(id);
  }

  function bindEmployeeModal() {
    var modal = $('employeeModal');
    var form = $('employeeForm');
    if (!modal || !form) return;

    function closeEmployeeModal() {
      modal.classList.remove('open');
    }

    function setEmployeeAddMode() {
      var url = new URL(window.location.href);
      if (url.searchParams.has('edit')) {
        url.searchParams.delete('edit');
        window.history.replaceState({}, '', url.pathname + url.search);
      }

      var methodInput = form.querySelector('input[name="_method"]');
      if (methodInput) methodInput.remove();

      form.action = form.dataset.storeUrl || form.getAttribute('action');
      form.reset();

      var title = $('employeeModalTitle');
      if (title) title.textContent = form.dataset.addTitle || '➕ إضافة موظف';

      var pwRequired = $('employeePasswordRequired');
      var pwHint = $('employeePasswordHint');
      if (pwRequired) pwRequired.style.display = '';
      if (pwHint) pwHint.style.display = 'none';

      var pw = form.querySelector('[name="password"]');
      if (pw) pw.setAttribute('data-v-rules', 'required,password');
    }

    var addBtn = $('btnAddEmployee');
    if (addBtn) {
      addBtn.addEventListener('click', function () {
        setEmployeeAddMode();
        modal.classList.add('open');
      });
    }

    ['closeEmployeeModal', 'cancelEmployeeModal'].forEach(function (id) {
      var btn = $(id);
      if (btn) btn.addEventListener('click', closeEmployeeModal);
    });

    modal.addEventListener('click', function (e) {
      if (e.target === modal) closeEmployeeModal();
    });

    if (modal.classList.contains('open')) {
      /* تعديل من السيرفر */
    }
  }

  window.deleteEmployee = function (id, name) {
    if (!confirm('حذف الموظف «' + name + '»؟ لا يمكن التراجع عن هذا الإجراء.')) return;
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';
    fetch(baseUrl + '/' + encodeURIComponent(id), {
      method: 'DELETE',
      headers: {
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })
      .then(function (r) {
        return r.ok ? r.json() : r.json().then(function (j) { throw j; });
      })
      .then(function () {
        window.location.href = cfg.staffRoute || window.location.pathname;
      })
      .catch(function (err) {
        alert((err && err.message) ? err.message : 'تعذّر حذف الموظف.');
      });
  };

  window.resetEmployeePassword = function (id, name) {
    var modal = $('employeePasswordResetModal');
    var form = $('employeePasswordResetForm');
    if (!modal || !form) return;

    var resetUserId = id;
    var hint = $('employeePasswordResetHint');
    if (hint) hint.textContent = 'إعادة تعيين كلمة مرور الموظف «' + name + '».';
    form.reset();
    modal.classList.add('open');

    function closeModal() {
      modal.classList.remove('open');
      form.reset();
    }

    ['closeEmployeePasswordResetModal', 'cancelEmployeePasswordResetModal'].forEach(function (id) {
      var btn = $(id);
      if (btn) btn.addEventListener('click', closeModal, { once: true });
    });

    modal.addEventListener('click', function onOverlay(e) {
      if (e.target === modal) {
        closeModal();
        modal.removeEventListener('click', onOverlay);
      }
    });

    form.addEventListener('submit', function onSubmit(e) {
      e.preventDefault();
      if (window.FormValidation && !window.FormValidation.validateForm(form)) return;

      var csrfMeta = document.querySelector('meta[name="csrf-token"]');
      var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';
      var fd = new FormData(form);

      fetch(baseUrl + '/' + encodeURIComponent(resetUserId) + '/reset-password', {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: fd,
      })
        .then(function (r) {
          return r.ok ? r.json() : r.json().then(function (j) { throw j; });
        })
        .then(function (res) {
          closeModal();
          alert((res && res.message) ? res.message : 'تم إعادة تعيين كلمة المرور.');
        })
        .catch(function (err) {
          var msg = (err && err.message) ? err.message : 'تعذّر إعادة تعيين كلمة المرور.';
          if (err && err.errors) {
            var first = Object.values(err.errors)[0];
            if (Array.isArray(first) && first[0]) msg = first[0];
          }
          alert(msg);
        });
    }, { once: true });
  };

  bindEmployeeModal();

  var search = $('empSearch');
  var tbody = $('employeesTableFull');
  if (search && tbody) {
    search.addEventListener('input', function () {
      var q = search.value.trim().toLowerCase();
      tbody.querySelectorAll('tr').forEach(function (row) {
        var text = row.textContent.toLowerCase();
        row.style.display = !q || text.indexOf(q) !== -1 ? '' : 'none';
      });
    });
  }
})();
