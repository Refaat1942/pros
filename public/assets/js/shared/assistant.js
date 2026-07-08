/*
 * assistant.js — المساعد الذكي الإرشادي (أوفلاين).
 * زر في ترويسة اللوحة يفتح لوحة بحث تستدعي /assistant/search
 * وتعرض إجابات بالعامية حسب اللوحة/الصفحة الحالية وصلاحيات المستخدم.
 */
(function () {
    'use strict';

    var config = window.__ASSISTANT || {};
    var searchUrl = config.url || window.__ASSISTANT_SEARCH_URL;

    if (!searchUrl) {
        return;
    }

    var KNOWN_DASHBOARDS = [
        'reception', 'doctor', 'spec', 'adjustments', 'costing',
        'operations', 'cashier', 'workshop', 'technical', 'admin',
    ];

    var body = document.body;

    function detectDashboard() {
        var attr = body.getAttribute('data-dashboard');
        if (attr) {
            return attr;
        }
        var segment = (window.location.pathname.split('/').filter(Boolean)[0] || '');
        return KNOWN_DASHBOARDS.indexOf(segment) !== -1 ? segment : '';
    }

    var dashboard = detectDashboard();
    var page = body.getAttribute('data-active-page') || '';
    var trigger = document.getElementById('assistantTrigger');

    var overlay;
    var panel;
    var input;
    var resultsBox;
    var built = false;
    var debounceTimer = null;
    var lastToken = 0;

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function buildQueryUrl(query) {
        var params = new URLSearchParams();
        if (query) {
            params.set('q', query);
        }
        if (dashboard) {
            params.set('dashboard', dashboard);
        }
        if (page) {
            params.set('page', page);
        }
        var sep = searchUrl.indexOf('?') === -1 ? '?' : '&';
        return searchUrl + sep + params.toString();
    }

    function renderResults(results) {
        if (!results || !results.length) {
            resultsBox.innerHTML = '<div class="assistant-empty">مفيش نتيجة مطابقة. جرّب كلمة تانية زي «طباعة» أو «صرف مواد» أو «عرض السعر».</div>';
            return;
        }

        var html = '';
        results.forEach(function (item) {
            html += '<div class="assistant-card">';
            html += '<div class="assistant-card__title">💡 ' + escapeHtml(item.title) + '</div>';
            html += '<div class="assistant-card__answer">' + escapeHtml(item.answer) + '</div>';
            if (item.steps && item.steps.length) {
                html += '<ol class="assistant-card__steps">';
                item.steps.forEach(function (step) {
                    html += '<li>' + escapeHtml(step) + '</li>';
                });
                html += '</ol>';
            }
            html += '</div>';
        });

        resultsBox.innerHTML = html;
    }

    function fetchResults(query) {
        var token = ++lastToken;
        resultsBox.innerHTML = '<div class="assistant-loading">لحظة بدوّرلك…</div>';

        fetch(buildQueryUrl(query), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('bad status');
                }
                return response.json();
            })
            .then(function (data) {
                if (token !== lastToken) {
                    return;
                }
                renderResults(data.results || []);
            })
            .catch(function () {
                if (token !== lastToken) {
                    return;
                }
                resultsBox.innerHTML = '<div class="assistant-empty">حصلت مشكلة في التحميل. حاول تاني.</div>';
            });
    }

    function onInput() {
        var query = input.value.trim();
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }
        debounceTimer = setTimeout(function () {
            fetchResults(query);
        }, 220);
    }

    function build() {
        if (built) {
            return;
        }
        built = true;

        overlay = document.createElement('div');
        overlay.className = 'assistant-overlay';

        panel = document.createElement('div');
        panel.className = 'assistant-panel';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', 'المساعد الذكي');
        panel.innerHTML =
            '<div class="assistant-panel__head">' +
                '<span class="assistant-panel__title">🤖 المساعد الذكي</span>' +
                '<button type="button" class="assistant-panel__close" aria-label="إغلاق">&times;</button>' +
            '</div>' +
            '<div class="assistant-panel__search">' +
                '<input type="search" autocomplete="off" placeholder="اكتب سؤالك بالعامية… مثلاً: أطبع عرض السعر إزاي؟">' +
            '</div>' +
            '<div class="assistant-panel__body">' +
                '<div class="assistant-hint">اسألني عن أي شاشة أو خطوة، وأنا أرشدك خطوة بخطوة.</div>' +
                '<div class="assistant-results"></div>' +
            '</div>';

        document.body.appendChild(overlay);
        document.body.appendChild(panel);

        input = panel.querySelector('input');
        resultsBox = panel.querySelector('.assistant-results');

        panel.querySelector('.assistant-panel__close').addEventListener('click', close);
        overlay.addEventListener('click', close);
        input.addEventListener('input', onInput);
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && panel.classList.contains('is-open')) {
                close();
            }
        });
    }

    function open() {
        build();
        overlay.classList.add('is-open');
        panel.classList.add('is-open');
        fetchResults(input.value.trim());
        window.setTimeout(function () { input.focus(); }, 50);
    }

    function close() {
        if (!panel) {
            return;
        }
        overlay.classList.remove('is-open');
        panel.classList.remove('is-open');
    }

    function toggle() {
        if (panel && panel.classList.contains('is-open')) {
            close();
        } else {
            open();
        }
    }

    if (!trigger) {
        trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.id = 'assistantTrigger';
        trigger.className = 'assistant-fab';
        trigger.setAttribute('aria-label', 'افتح المساعد الذكي');
        trigger.setAttribute('title', 'مساعد ذكي');
        trigger.innerHTML = '<span aria-hidden="true">🤖</span>';
        document.body.appendChild(trigger);
    }

    trigger.addEventListener('click', toggle);
})();
