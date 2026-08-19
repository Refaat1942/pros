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
        technical_inventory: ['code', 'name'],
        spec_picker: ['code', 'name'],
        adjustments_picker: ['code', 'name'],
        doctor_picker: ['code', 'name'],
    };

    if (!wrap) return;

    function esc(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    function render() {
        var html = '';
        roles.forEach(function (role) {
            html += '<section class="catalog-list-settings-role">' +
                '<div class="catalog-list-settings-role__head">' + esc(role.label_ar || role.slug) + '</div>';

            (role.profiles || []).forEach(function (profile) {
                var enableId = 'cls-en-' + role.slug + '-' + profile.key;
                html += '<div class="catalog-list-settings-profile" data-role="' + esc(role.slug) + '" data-profile="' + esc(profile.key) + '">' +
                    '<div class="catalog-list-settings-profile__head">' +
                    '<label class="catalog-list-settings-profile__title" for="' + esc(enableId) + '">' +
                    '<input type="checkbox" id="' + esc(enableId) + '" class="cls-enable"' +
                    (profile.enabled ? ' checked' : '') + '> ' +
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
                        (required ? ' disabled' : '') + '>' +
                        '<span>' + esc(col.label || col.key) +
                        (gated ? ' <small>(صلاحية)</small>' : '') +
                        '</span></label>';
                });

                html += '</div></div>';
            });

            html += '</section>';
        });

        wrap.innerHTML = html || '<p class="text-muted">لا توجد أدوار.</p>';
    }

    function collectPayload() {
        var out = { roles: {} };
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
