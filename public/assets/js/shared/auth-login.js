/**
 * Login form — disable submit button after first click to prevent double POST.
 */
(function () {
  'use strict';

  var form = document.getElementById('dashboardLoginForm');
  if (!form) return;

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
