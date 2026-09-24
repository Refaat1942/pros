/**
 * Login form — show/hide password, and disable submit after first click to prevent double POST.
 */
(function () {
  'use strict';

  var form = document.getElementById('dashboardLoginForm');
  if (!form) return;

  var passwordInput = document.getElementById('password');
  var toggle = document.getElementById('passwordToggle');
  if (passwordInput && toggle) {
    toggle.addEventListener('click', function () {
      var show = passwordInput.type === 'password';
      passwordInput.type = show ? 'text' : 'password';
      toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
      var label = show ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور';
      toggle.setAttribute('aria-label', label);
      toggle.setAttribute('title', label);
      passwordInput.focus();
    });

    form.addEventListener('submit', function () {
      passwordInput.type = 'password';
    });
  }

  var btn = form.querySelector('.btn-login');
  if (!btn) return;

  var defaultLabel = btn.textContent.trim();
  var submitting = false;

  function setSubmitting(on) {
    submitting = on;
    btn.disabled = on;
    btn.setAttribute('aria-busy', on ? 'true' : 'false');
    btn.textContent = on ? 'جاري الدخول...' : defaultLabel;
  }

  form.addEventListener('submit', function () {
    if (submitting) return;
    setSubmitting(true);
  });

  window.addEventListener('pageshow', function (e) {
    if (e.persisted) setSubmitting(false);
  });
})();
