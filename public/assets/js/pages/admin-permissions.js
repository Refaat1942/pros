/**
 * مصفوفة الصلاحيات — تبديل الدور + مزامنة المفاتيح المرئية مع المصفوفة المخفية.
 */
(function () {
    'use strict';

    const roleSelect = document.getElementById('permRoleSelect');
    const form = document.getElementById('permMatrixForm');
    if (!roleSelect || !form) return;

    let activeRoleId = roleSelect.value;

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
        });
    });

    form.addEventListener('submit', () => {
        flushVisibleToHidden();
    });

    loadRoleToVisible(activeRoleId);
    updateRoleBanner();
})();
