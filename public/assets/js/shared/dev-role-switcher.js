/**
 * شريط تنقّل الإدارة — إخفاء كامل + سحب للتحريك.
 */
(function () {
    var OPEN_KEY = 'prosthetics-admin-role-switcher-open';
    var POS_KEY = 'prosthetics-admin-role-switcher-pos';

    var root = document.querySelector('.dev-role-switcher');
    if (!root) return;

    var panel = root.querySelector('.dev-role-switcher__panel');
    var fab = root.querySelector('[data-role-switcher-show]');
    var hideBtn = root.querySelector('[data-role-switcher-hide]');
    var movedDuringDrag = false;

    function readPos() {
        try {
            var raw = localStorage.getItem(POS_KEY);
            if (!raw) return null;
            var parsed = JSON.parse(raw);
            if (typeof parsed.x !== 'number' || typeof parsed.y !== 'number') return null;
            return parsed;
        } catch (e) {
            return null;
        }
    }

    function savePos(x, y) {
        try {
            localStorage.setItem(POS_KEY, JSON.stringify({ x: x, y: y }));
        } catch (e) { /* ignore */ }
    }

    function activeSurface() {
        return root.classList.contains('is-hidden') ? fab : panel;
    }

    function applyPos(x, y) {
        root.classList.add('is-positioned');
        root.style.left = x + 'px';
        root.style.top = y + 'px';
        root.style.bottom = 'auto';
        root.style.transform = 'none';
    }

    function clampPos(x, y, width, height) {
        var pad = 8;
        var maxX = Math.max(pad, window.innerWidth - width - pad);
        var maxY = Math.max(pad, window.innerHeight - height - pad);
        return {
            x: Math.min(Math.max(pad, x), maxX),
            y: Math.min(Math.max(pad, y), maxY),
        };
    }

    function restorePosition() {
        var saved = readPos();
        if (!saved) return;

        var el = activeSurface();
        if (!el) return;

        var rect = el.getBoundingClientRect();
        var clamped = clampPos(saved.x, saved.y, rect.width || 44, rect.height || 44);
        applyPos(clamped.x, clamped.y);
    }

    function captureCurrentPos() {
        var el = activeSurface();
        if (!el) return;
        var rect = el.getBoundingClientRect();
        savePos(Math.round(rect.left), Math.round(rect.top));
    }

    function setHidden(hidden) {
        root.classList.toggle('is-hidden', hidden);
        try {
            localStorage.setItem(OPEN_KEY, hidden ? '0' : '1');
        } catch (e) { /* ignore */ }
        requestAnimationFrame(restorePosition);
    }

    function bindDrag(handle) {
        if (!handle) return;

        handle.addEventListener('pointerdown', function (event) {
            if (event.button !== 0) return;

            var el = activeSurface();
            if (!el) return;

            event.preventDefault();

            var rect = el.getBoundingClientRect();
            var offsetX = event.clientX - rect.left;
            var offsetY = event.clientY - rect.top;
            var startX = event.clientX;
            var startY = event.clientY;

            movedDuringDrag = false;
            root.classList.add('is-dragging');
            applyPos(rect.left, rect.top);

            function onMove(e) {
                if (Math.abs(e.clientX - startX) > 4 || Math.abs(e.clientY - startY) > 4) {
                    movedDuringDrag = true;
                }
                var size = el.getBoundingClientRect();
                var next = clampPos(e.clientX - offsetX, e.clientY - offsetY, size.width, size.height);
                applyPos(next.x, next.y);
            }

            function onUp() {
                root.classList.remove('is-dragging');
                if (movedDuringDrag) {
                    captureCurrentPos();
                }
                document.removeEventListener('pointermove', onMove);
                document.removeEventListener('pointerup', onUp);
                document.removeEventListener('pointercancel', onUp);
            }

            document.addEventListener('pointermove', onMove);
            document.addEventListener('pointerup', onUp);
            document.addEventListener('pointercancel', onUp);
        });
    }

    if (hideBtn) {
        hideBtn.addEventListener('click', function () {
            setHidden(true);
        });
    }

    if (fab) {
        fab.addEventListener('click', function () {
            if (movedDuringDrag) {
                movedDuringDrag = false;
                return;
            }
            setHidden(false);
        });
        bindDrag(fab);
    }

    root.querySelectorAll('[data-role-switcher-drag]').forEach(bindDrag);

    var storedOpen = null;
    try {
        storedOpen = localStorage.getItem(OPEN_KEY);
    } catch (e) { /* ignore */ }

    setHidden(storedOpen === null ? false : storedOpen !== '1');
    restorePosition();

    window.addEventListener('resize', restorePosition);
})();
