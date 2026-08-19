/**
 * إعدادات عرض قوائم الأصناف — لكل دور.
 */
(function () {
    var bootEl = document.getElementById('catalogListSettingsBootstrap');
    if (!bootEl) return;

    var boot = JSON.parse(bootEl.textContent || '{}');
    var roles = boot.roles || [];
    var csrf = boot.csrf || '';
    var wrap = document.getElementById('catalogListSettingsWrap');
    var errEl = document.getElementById('catalogListSettingsError');
    var saveBtn = document.getElementById('btnSaveCatalogListSettings');

    var requiredByProfile = {
        admin_catalog: ['code', 'name'],
        inventory_overview: ['code', 'name'],
        stock_kits_picker: ['code', 'name'],
        technical_inventory: ['code', 'name'],
        spec_picker: ['code', 'name'],
        adjustments_picker: ['code', 'name'],
        doctor_picker: ['code', 'name'],
    };

    if (!wrap) return;

    function esc(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    function renderProfile(role, profile, sectionDisabled) {
        var enableId = 'cls-en-' + role.slug + '-' + profile.key;
        var disabledAttr = sectionDisabled ? ' disabled' : '';
        var html = '<div class="catalog-list-settings-profile' +
            (sectionDisabled ? ' is-section-off' : '') +
            '" data-role="' + esc(role.slug) + '" data-profile="' + esc(profile.key) + '">' +
            '<div class="catalog-list-settings-profile__head">' +
            '<label class="catalog-list-settings-profile__title" for="' + esc(enableId) + '">' +
            '<input type="checkbox" id="' + esc(enableId) + '" class="cls-enable"' +
            (profile.enabled ? ' checked' : '') + disabledAttr + '> ' +
            esc(profile.label_ar || profile.key) +
            '</label>' +
            '<span class="catalog-list-settings-profile__meta">' +
            esc(profile.dashboard || '') + ' / ' + esc(profile.page || '') +
            '</span></div>' +
            '<div class="catalog-list-settings-columns">';

        (profile.columns || []).forEach(function (col) {
            var colId = 'cls-col-' + role.slug + '-' + profile.key + '-' + col.key;
            var required = (requiredByProfile[profile.key] || []).indexOf(col.key) !== -1;
            var gated = !!col.gate;
            html += '<label class="catalog-list-settings-col' +
                (required ? ' is-required' : '') +
                (gated ? ' is-gated' : '') +
                '" for="' + esc(colId) + '">' +
                '<input type="checkbox" id="' + esc(colId) + '" class="cls-column"' +
                ' data-col="' + esc(col.key) + '"' +
                (col.visible || required ? ' checked' : '') +
                (required || sectionDisabled ? ' disabled' : '') + '>' +
                '<span>' + esc(col.label || col.key) +
                (gated ? ' <small>(صلاحية)</small>' : '') +
                '</span></label>';
        });

        html += '</div></div>';
        return html;
    }

    function renderSection(role, section) {
        var sectionId = 'cls-sec-' + role.slug + '-' + section.key;
        var html = '<div class="catalog-list-settings-section" data-role="' + esc(role.slug) +
            '" data-section="' + esc(section.key) + '">' +
            '<div class="catalog-list-settings-section__head">' +
            '<label class="catalog-list-settings-section__title" for="' + esc(sectionId) + '">' +
            '<input type="checkbox" id="' + esc(sectionId) + '" class="cls-section-enable"' +
            (section.enabled ? ' checked' : '') + '> ' +
            esc(section.label_ar || section.key) +
            '</label>' +
            '<span class="catalog-list-settings-section__hint">مفتاح رئيسي — يوقف كل قوائم القسم عند الإغلاق</span>' +
            '</div><div class="catalog-list-settings-section__profiles">';

        (section.profiles || []).forEach(function (profile) {
            html += renderProfile(role, profile, !section.enabled);
        });

        html += '</div></div>';
        return html;
    }

    function bindSectionToggles() {
        wrap.querySelectorAll('.cls-section-enable').forEach(function (input) {
            input.addEventListener('change', function () {
                var block = input.closest('.catalog-list-settings-section');
                if (!block) return;
                var off = !input.checked;
                block.querySelectorAll('.catalog-list-settings-profile').forEach(function (profileEl) {
                    profileEl.classList.toggle('is-section-off', off);
                    var profileKey = profileEl.getAttribute('data-profile');
                    var required = requiredByProfile[profileKey] || [];
                    profileEl.querySelectorAll('.cls-enable').forEach(function (el) {
                        el.disabled = off;
                    });
                    profileEl.querySelectorAll('.cls-column').forEach(function (el) {
                        var col = el.getAttribute('data-col');
                        el.disabled = off || required.indexOf(col) !== -1;
                    });
                });
            });
        });
    }

    function render() {
        var html = '';
        roles.forEach(function (role) {
            html += '<section class="catalog-list-settings-role">' +
                '<div class="catalog-list-settings-role__head">' + esc(role.label_ar || role.slug) + '</div>';

            (role.sections || []).forEach(function (section) {
                html += renderSection(role, section);
            });

            (role.profiles || []).forEach(function (profile) {
                html += renderProfile(role, profile, false);
            });

            html += '</section>';
        });

        wrap.innerHTML = html || '<p class="text-muted">لا توجد أدوار.</p>';
        bindSectionToggles();
    }

    function collectPayload() {
        var out = { sections: {}, roles: {} };

        wrap.querySelectorAll('.catalog-list-settings-section').forEach(function (block) {
            var roleSlug = block.getAttribute('data-role');
            var sectionKey = block.getAttribute('data-section');
            if (!out.sections[sectionKey]) out.sections[sectionKey] = { roles: {} };
            var enabledEl = block.querySelector('.cls-section-enable');
            out.sections[sectionKey].roles[roleSlug] = {
                enabled: !!(enabledEl && enabledEl.checked),
            };
        });

        wrap.querySelectorAll('.catalog-list-settings-profile').forEach(function (block) {
            var roleSlug = block.getAttribute('data-role');
            var profileKey = block.getAttribute('data-profile');
            if (!out.roles[roleSlug]) out.roles[roleSlug] = {};
            var enabledEl = block.querySelector('.cls-enable');
            var columns = [];
            block.querySelectorAll('.cls-column:checked').forEach(function (input) {
                columns.push(input.getAttribute('data-col'));
            });
            (requiredByProfile[profileKey] || []).forEach(function (req) {
                if (columns.indexOf(req) === -1) columns.unshift(req);
            });
            out.roles[roleSlug][profileKey] = {
                enabled: !!(enabledEl && enabledEl.checked),
                columns: columns,
            };
        });

        return out;
    }

    function showError(msg) {
        if (!errEl) return;
        errEl.textContent = msg || '';
        errEl.style.display = msg ? 'block' : 'none';
    }

    function saveSettings() {
        if (!saveBtn) return;
        showError('');
        saveBtn.disabled = true;

        fetch('/admin/catalog-list-settings', {
            method: 'PUT',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(collectPayload()),
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    if (!res.ok) throw data;
                    return data;
                });
            })
            .then(function (data) {
                if (data.roles) roles = data.roles;
                render();
                if (window.showToast) {
                    window.showToast(data.message || 'تم الحفظ');
                }
            })
            .catch(function (err) {
                var msg = (err && err.message) || (err && err.errors && Object.values(err.errors)[0][0]) ||
                    'تعذّر حفظ الإعدادات';
                showError(msg);
            })
            .finally(function () {
                if (saveBtn) saveBtn.disabled = false;
            });
    }

    render();
    if (saveBtn) saveBtn.addEventListener('click', saveSettings);
})();
