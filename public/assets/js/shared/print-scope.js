/*
 * print-scope.js — طباعة عنصر واحد من الصفحة دون طباعة لوحة التحكم كاملة.
 * الاستخدام: زر يحمل [data-print-scope] و data-print-target="<CSS selector>".
 */
(function () {
    'use strict';

    function printTarget(button) {
        var selector = button.getAttribute('data-print-target');
        var target = selector ? document.querySelector(selector) : null;

        if (!target) {
            window.print();
            return;
        }

        var body = document.body;
        var cleaned = false;

        function cleanup() {
            if (cleaned) {
                return;
            }
            cleaned = true;
            target.classList.remove('print-scope-target');
            body.classList.remove('print-scope-active');
            window.removeEventListener('afterprint', cleanup);
        }

        target.classList.add('print-scope-target');
        body.classList.add('print-scope-active');
        window.addEventListener('afterprint', cleanup);
        window.print();
        window.setTimeout(cleanup, 1500);
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-print-scope]');
        if (!button) {
            return;
        }
        event.preventDefault();
        printTarget(button);
    });
})();
