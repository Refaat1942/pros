/**
 * Dashboard Mobile — قائمة جانبية + شريط سفلي للهاتف
 */
(function () {
  function syncTitle() {
    var el = document.getElementById('dmMobileTitle');
    if (!el) return;
    var src = document.getElementById('pageTitle') || document.querySelector('.page-header h1');
    if (src) el.textContent = src.textContent.trim();
  }

  function closeNav() {
    document.body.classList.remove('dm-nav-open');
  }

  function init() {
    var sidebar = document.querySelector('.sidebar');
    if (!sidebar || document.getElementById('dmMobileBar')) return;

    var overlay = document.createElement('div');
    overlay.className = 'dm-overlay';
    overlay.id = 'dmOverlay';

    var bar = document.createElement('header');
    bar.className = 'dm-mobile-bar';
    bar.id = 'dmMobileBar';
    bar.innerHTML =
      '<button type="button" class="dm-menu-btn" id="dmMenuBtn" aria-label="فتح القائمة">☰</button>' +
      '<span class="dm-mobile-title" id="dmMobileTitle"></span>' +
      '<a href="/" class="dm-home-btn" aria-label="الصفحة الرئيسية">🏠</a>';

    var bottomNav = document.createElement('nav');
    bottomNav.className = 'dm-bottom-nav';
    bottomNav.id = 'dmBottomNav';
    bottomNav.setAttribute('aria-label', 'التنقل السريع');

    var navLinks = sidebar.querySelectorAll('.nav-menu a');
    navLinks.forEach(function (link, index) {
      var item = document.createElement('a');
      item.href = '#';
      if (link.classList.contains('active')) item.classList.add('active');
      item.innerHTML = link.innerHTML;
      item.addEventListener('click', function (e) {
        e.preventDefault();
        link.click();
        closeNav();
        bottomNav.querySelectorAll('a').forEach(function (a, i) {
          a.classList.toggle('active', i === index);
        });
        setTimeout(syncTitle, 80);
      });
      bottomNav.appendChild(item);
    });

    document.body.appendChild(overlay);
    document.body.insertBefore(bar, document.body.firstChild);
    document.body.appendChild(bottomNav);

    document.getElementById('dmMenuBtn').addEventListener('click', function () {
      document.body.classList.toggle('dm-nav-open');
    });
    overlay.addEventListener('click', closeNav);

    sidebar.querySelectorAll('.nav-menu a').forEach(function (link, index) {
      link.addEventListener('click', function () {
        closeNav();
        bottomNav.querySelectorAll('a').forEach(function (a, i) {
          a.classList.toggle('active', i === index);
        });
        setTimeout(syncTitle, 80);
      });
    });

    syncTitle();

    var titleEl = document.getElementById('pageTitle');
    if (titleEl && window.MutationObserver) {
      new MutationObserver(syncTitle).observe(titleEl, { childList: true, characterData: true, subtree: true });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.addEventListener('resize', function () {
    if (window.innerWidth > 768) closeNav();
  });
})();
