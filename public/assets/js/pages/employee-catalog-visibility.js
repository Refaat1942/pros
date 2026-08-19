/**
 * اختيار قوائم الأصناف عند إضافة/تعديل موظف — قبل الحفظ.
 */
(function () {
    var form = document.getElementById('employeeForm');
    if (!form || !form.dataset.catalogUrl) return;

    var block = document.getElementById('employeeCatalogVisibilityBlock');
    var wrap = document.getElementById('employeeCatalogVisibilityWrap');
    var loadingEl = document.getElementById('employeeCatalogVisibilityLoading');
    var hiddenInput = document.getElementById('employeeCatalogVisibilityInput');
    var roleSelect = document.getElementById('employeeRoleSelect');
    if (!block || !wrap || !roleSelect) return;

    var currentCatalog = null;
    var editUserId = form.dataset.editUserId || '';

    var requiredByProfile = {
        admin_catalog: ['code', 'name'],
        inventory_overview: ['code', 'name'],
        stock_kits_picker: ['code', 'name'],
        technical_inventory: ['code', 'name'],
        spec_picker: ['code', 'name'],
        adjustments_picker: ['code', 'name'],
        doctor_picker: ['code', 'name'],
    };

    function esc(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    function setLoading(on) {
        if (loadingEl) loadingEl.style.display = on ? 'block' : 'none';
        if (wrap) wrap.style.display = on ? 'none' : '';
    }

    function renderProfile(profile, sectionDisabled) {
        var disabledAttr = sectionDisabled ? ' disabled' : '';
        var html = '<div class="catalog-list-settings-profile' +
            (sectionDisabled ? ' is-section-off' : '') +
            '" data-profile="' + esc(profile.key) + '">' +
            '<div class="catalog-list-settings-profile__head">' +
            '<label class="catalog-list-settings-profile__title">' +
            '<input type="checkbox" class="emp-cls-enable"' +
            (profile.enabled ? ' checked' : '') + disabledAttr + '> ' +
            esc(profile.label_ar || profile.key) +
            '</label></div><div class="catalog-list-settings-columns">';

        (profile.columns || []).forEach(function (col) {
            var required = (requiredByProfile[profile.key] || []).indexOf(col.key) !== -1;
            html += '<label class="catalog-list-settings-col' +
                (required ? ' is-required' : '') +
                (col.gate ? ' is-gated' : '') + '">' +
                '<input type="checkbox" class="emp-cls-column" data-col="' + esc(col.key) + '"' +
                (col.visible || required ? ' checked' : '') +
                (required || sectionDisabled ? ' disabled' : '') + '>' +
                '<span>' + esc(col.label || col.key) + '</span></label>';
        });

        html += '</div></div>';
        return html;
    }

    function renderSection(section) {
        var html = '<div class="catalog-list-settings-section" data-section="' + esc(section.key) + '">' +
            '<div class="catalog-list-settings-section__head">' +
            '<label class="catalog-list-settings-section__title">' +
            '<input type="checkbox" class="emp-cls-section-enable"' +
            (section.enabled ? ' checked' : '') + '> ' +
            esc(section.label_ar || section.key) +
            '</label></div><div class="catalog-list-settings-section__profiles">';

        (section.profiles || []).forEach(function (profile) {
            html += renderProfile(profile, !section.enabled);
        });

        html += '</div></div>';
        return html;
    }

    function renderCatalog(catalog) {
        currentCatalog = catalog;
        if (!catalog || !catalog.has_profiles) {
            block.style.display = 'none';
            wrap.innerHTML = '';
            if (hiddenInput) hiddenInput.value = '';
            return;
        }

        block.style.display = '';
        var html = '';
        (catalog.sections || []).forEach(function (section) {
            html += renderSection(section);
        });
        (catalog.profiles || []).forEach(function (profile) {
            html += renderProfile(profile, false);
        });

        wrap.innerHTML = html || '<p class="employee-catalog-visibility-loading">لا توجد قوائم أصناف لهذا الدور.</p>';
        bindSectionToggles();
        syncHiddenInput();
    }

    function bindSectionToggles() {
        wrap.querySelectorAll('.emp-cls-section-enable').forEach(function (input) {
            input.addEventListener('change', function () {
                var sectionEl = input.closest('.catalog-list-settings-section');
                if (!sectionEl) return;
                var off = !input.checked;
                sectionEl.querySelectorAll('.catalog-list-settings-profile').forEach(function (profileEl) {
                    profileEl.classList.toggle('is-section-off', off);
                    var profileKey = profileEl.getAttribute('data-profile');
                    var required = requiredByProfile[profileKey] || [];
                    profileEl.querySelectorAll('.emp-cls-enable').forEach(function (el) {
                        el.disabled = off;
                    });
                    profileEl.querySelectorAll('.emp-cls-column').forEach(function (el) {
                        var col = el.getAttribute('data-col');
                        el.disabled = off || required.indexOf(col) !== -1;
                    });
                });
                syncHiddenInput();
            });
        });

        wrap.addEventListener('change', function (e) {
            if (e.target && (e.target.classList.contains('emp-cls-enable') ||
                e.target.classList.contains('emp-cls-column'))) {
                syncHiddenInput();
            }
        });
    }

    function collectPayload() {
        var out = { sections: {}, profiles: {} };

        wrap.querySelectorAll('.catalog-list-settings-section').forEach(function (sectionEl) {
            var sectionKey = sectionEl.getAttribute('data-section');
            var enabledEl = sectionEl.querySelector('.emp-cls-section-enable');
            out.sections[sectionKey] = {
                enabled: !!(enabledEl && enabledEl.checked),
            };
        });

        wrap.querySelectorAll('.catalog-list-settings-profile').forEach(function (profileEl) {
            var profileKey = profileEl.getAttribute('data-profile');
            var enabledEl = profileEl.querySelector('.emp-cls-enable');
            var columns = [];
            profileEl.querySelectorAll('.emp-cls-column:checked').forEach(function (input) {
                columns.push(input.getAttribute('data-col'));
            });
            (requiredByProfile[profileKey] || []).forEach(function (req) {
                if (columns.indexOf(req) === -1) columns.unshift(req);
            });
            out.profiles[profileKey] = {
                enabled: !!(enabledEl && enabledEl.checked),
                columns: columns,
            };
        });

        return out;
    }

    function syncHiddenInput() {
        if (!hiddenInput) return;
        hiddenInput.value = currentCatalog && currentCatalog.has_profiles
            ? JSON.stringify(collectPayload())
            : '';
    }

    function loadCatalogForRole(roleId, useEditUser) {
        if (!roleId) {
            renderCatalog(null);
            return;
        }

        setLoading(true);
        var url = form.dataset.catalogUrl +
            '?role_id=' + encodeURIComponent(roleId) +
            (useEditUser && editUserId ? '&user_id=' + encodeURIComponent(editUserId) : '');

        fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (res) {
                return res.ok ? res.json() : Promise.reject(res);
            })
            .then(function (data) {
                renderCatalog(data.catalog || null);
            })
            .catch(function () {
                wrap.innerHTML = '<p class="employee-catalog-visibility-loading">تعذّر تحميل قوائم الأصناف.</p>';
            })
            .finally(function () {
                setLoading(false);
            });
    }

    roleSelect.addEventListener('change', function () {
        editUserId = '';
        loadCatalogForRole(roleSelect.value, false);
    });

    form.addEventListener('submit', function () {
        syncHiddenInput();
    });

    window.resetEmployeeCatalogVisibility = function () {
        currentCatalog = null;
        editUserId = '';
        if (hiddenInput) hiddenInput.value = '';
        if (wrap) wrap.innerHTML = '';
        if (block) block.style.display = 'none';
    };

    window.refreshEmployeeCatalogVisibility = function () {
        if (roleSelect.value) {
            loadCatalogForRole(roleSelect.value, !!editUserId);
        }
    };

    if (roleSelect.value) {
        loadCatalogForRole(roleSelect.value, !!editUserId);
    }
})();
