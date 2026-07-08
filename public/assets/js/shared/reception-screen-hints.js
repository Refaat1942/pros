(function () {
  if (document.body.dataset.dashboard !== 'reception') return;

  var page = document.body.dataset.activePage || '';
  var mountId = 'receptionScreenHintMount';
  var dismissedKey = 'reception_hint_dismissed_' + page + '_' + new Date().toDateString();

  function esc(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }

  function mountHint(data) {
    if (!data || !data.message) return;
    if (sessionStorage.getItem(dismissedKey) === '1') return;

    var host = document.querySelector('.section-view') || document.querySelector('[data-active-page]') || document.querySelector('main');
    if (!host) return;

    var existing = document.getElementById(mountId);
    if (existing) existing.remove();

    var el = document.createElement('div');
    el.id = mountId;
    el.className = 'reception-screen-hint';
    el.setAttribute('role', 'status');

    var badge = data.count != null && data.count > 0
      ? '<span class="reception-screen-hint__badge">' + esc(data.count) + '</span>'
      : '<span class="reception-screen-hint__badge">!</span>';

    var linkHtml = data.link
      ? '<div class="reception-screen-hint__actions"><a class="reception-screen-hint__link" href="' + esc(data.link) + '">' + esc(data.link_label || 'فتح') + ' →</a></div>'
      : '';

    el.innerHTML = badge +
      '<div class="reception-screen-hint__body">' +
        '<p class="reception-screen-hint__title">' + esc(data.title || 'تنبيه') + '</p>' +
        '<p class="reception-screen-hint__msg">' + esc(data.message) + '</p>' +
        linkHtml +
      '</div>' +
      '<button type="button" class="reception-screen-hint__dismiss" title="إخفاء اليوم" aria-label="إخفاء">&times;</button>';

    el.querySelector('.reception-screen-hint__dismiss').addEventListener('click', function () {
      sessionStorage.setItem(dismissedKey, '1');
      el.remove();
    });

    host.insertBefore(el, host.firstChild);
  }

  if (!window.axios || !page) return;

  axios.get('/reception/screen-hints', { params: { page: page } })
    .then(function (res) { mountHint(res.data); })
    .catch(function () { /* optional */ });
})();
