/**
 * شريط تنقّل الإدارة بين الأقسام — إظهار / إخفاء مع حفظ التفضيل.
 */
(function () {
    var STORAGE_KEY = 'prosthetics-admin-role-switcher-open';
    var bar = document.querySelector('.dev-role-switcher');
    if (!bar) return;

    var toggles = bar.querySelectorAll('[data-role-switcher-toggle]');
    var track = bar.querySelector('.dev-role-switcher__track');
    var label = bar.querySelector('.dev-role-switcher__label');

    function setOpen(open) {
        document.body.classList.toggle('has-dev-role-switcher', open);
        bar.classList.toggle('is-collapsed', !open);
        toggles.forEach(function (btn) {
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            btn.setAttribute('title', open ? 'إخفاء شريط التنقّل' : 'إظهار شريط التنقّل');
        });
        if (track) track.hidden = !open;
        if (label) label.hidden = !open;
        try {
            localStorage.setItem(STORAGE_KEY, open ? '1' : '0');
        } catch (e) { /* ignore */ }
    }

    var stored = null;
    try {
        stored = localStorage.getItem(STORAGE_KEY);
    } catch (e) { /* ignore */ }

    setOpen(stored === null ? true : stored === '1');

    toggles.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var open = !document.body.classList.contains('has-dev-role-switcher');
            setOpen(open);
        });
    });
})();
