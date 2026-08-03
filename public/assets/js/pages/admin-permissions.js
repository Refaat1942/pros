/**
 * مصفوفة الصلاحيات — تبديل الدور + مزامنة المفاتيح المرئية مع المصفوفة المخفية.
 */
(function () {
    'use strict';

    const roleSelect = document.getElementById('permRoleSelect');
    const form = document.getElementById('permMatrixForm');
    if (!roleSelect || !form) return;

    let activeRoleId = roleSelect.value;

    let pageActionAliases = {};
    try {
        pageActionAliases = JSON.parse(form.dataset.pageActionAliases || '{}');
    } catch (e) {
        pageActionAliases = {};
    }

    /** slug عرض الصفحة ← slugs الإجراءات المرتبطة */
    const viewToActions = {};
    Object.keys(pageActionAliases).forEach((actionSlug) => {
        const pair = pageActionAliases[actionSlug];
        if (!Array.isArray(pair) || pair.length < 2) return;
        const viewSlug = pair[0] + '.' + pair[1] + '.view';
        if (!viewToActions[viewSlug]) {
            viewToActions[viewSlug] = [];
        }
        viewToActions[viewSlug].push(actionSlug);
    });

    function setSlugChecked(slug, checked) {
        const visible = form.querySelector('.perm-visible-cb[data-slug="' + slug + '"]');
        if (visible) {
            visible.checked = checked;
        }
        const hidden = form.querySelector(
            '.perm-hidden-cb[data-role="' + activeRoleId + '"][data-slug="' + slug + '"]'
        );
        if (hidden) {
            hidden.checked = checked;
        }
    }

    function syncLinkedActionsForView(viewSlug, checked) {
        (viewToActions[viewSlug] || []).forEach((actionSlug) => {
            setSlugChecked(actionSlug, checked);
        });
    }

    function hiddenCheckboxes(roleId) {
        return form.querySelectorAll(`.perm-hidden-cb[data-role="${roleId}"]`);
    }

    function visibleCheckboxes() {
        return form.querySelectorAll('.perm-visible-cb');
    }

    /** حفظ حالة الدور الحالي في المصفوفة المخفية قبل التبديل */
    function flushVisibleToHidden() {
        visibleCheckboxes().forEach((visible) => {
            const slug = visible.dataset.slug;
            const hidden = form.querySelector(
                `.perm-hidden-cb[data-role="${activeRoleId}"][data-slug="${slug}"]`
            );
            if (hidden) {
                hidden.checked = visible.checked;
            }
        });
    }

    /** تحميل صلاحيات الدور المختار إلى المفاتيح المرئية */
    function loadRoleToVisible(roleId) {
        visibleCheckboxes().forEach((visible) => {
            const slug = visible.dataset.slug;
            const hidden = form.querySelector(
                `.perm-hidden-cb[data-role="${roleId}"][data-slug="${slug}"]`
            );
            visible.checked = hidden ? hidden.checked : false;
        });
    }

    function setAllChecked(checked) {
        visibleCheckboxes().forEach((visible) => {
            visible.checked = checked;
            const slug = visible.dataset.slug;
            const hidden = form.querySelector(
                `.perm-hidden-cb[data-role="${activeRoleId}"][data-slug="${slug}"]`
            );
            if (hidden) {
                hidden.checked = checked;
            }
        });
    }

    const checkAllBtn = document.getElementById('permCheckAllBtn');
    if (checkAllBtn) {
        checkAllBtn.addEventListener('click', () => setAllChecked(true));
    }

    roleSelect.addEventListener('change', () => {
        flushVisibleToHidden();
        activeRoleId = roleSelect.value;
        loadRoleToVisible(activeRoleId);
        updateRoleBanner();
    });

    function updateRoleBanner() {
        const bannerName = document.getElementById('permRoleBannerName');
        const option = roleSelect.options[roleSelect.selectedIndex];
        if (bannerName && option) {
            bannerName.textContent = option.textContent.trim();
        }
    }

    visibleCheckboxes().forEach((visible) => {
        visible.addEventListener('change', () => {
            const slug = visible.dataset.slug;
            const hidden = form.querySelector(
                `.perm-hidden-cb[data-role="${activeRoleId}"][data-slug="${slug}"]`
            );
            if (hidden) {
                hidden.checked = visible.checked;
            }
            if (slug.endsWith('.view')) {
                syncLinkedActionsForView(slug, visible.checked);
            }
        });
    });

    form.addEventListener('submit', () => {
        flushVisibleToHidden();

        let jsonInput = form.querySelector('input[name="matrix_json"]');
        if (!jsonInput) {
            jsonInput = document.createElement('input');
            jsonInput.type = 'hidden';
            jsonInput.name = 'matrix_json';
            form.appendChild(jsonInput);
        }

        const payload = {};
        form.querySelectorAll('.perm-hidden-cb').forEach((cb) => {
            const roleId = cb.dataset.role;
            if (!payload[roleId]) {
                payload[roleId] = [];
            }
            if (cb.checked) {
                payload[roleId].push(parseInt(cb.value, 10));
            }
        });
        jsonInput.value = JSON.stringify(payload);

        form.querySelectorAll('.perm-hidden-cb').forEach((cb) => {
            cb.disabled = true;
        });
    });

    loadRoleToVisible(activeRoleId);
    updateRoleBanner();
})();
