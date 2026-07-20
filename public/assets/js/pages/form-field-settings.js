/**
 * إلزامية حقول النماذج — لوحة مصمم المسار.
 */
(function () {
    var bootEl = document.getElementById('pathwayDesignerBootstrap');
    if (!bootEl) return;

    var boot = JSON.parse(bootEl.textContent || '{}');
    var policies = boot.form_field_policies || {};
    var labels = boot.form_field_feature_labels || {};
    var csrf = boot.csrf || '';
    var wrap = document.getElementById('formFieldSettingsWrap');
    var errEl = document.getElementById('formFieldSettingsError');
    var saveBtn = document.getElementById('btnSaveFormFields');

    if (!wrap) return;

    function esc(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    function render() {
        var html = '';
        Object.keys(policies).forEach(function (feature) {
            var fields = policies[feature] || [];
            if (!fields.length) return;

            html += '<section class="form-field-settings-feature">' +
                '<h4 class="form-field-settings-feature__title">' + esc(labels[feature] || feature) + '</h4>' +
                '<div class="form-field-settings-grid">';

            fields.forEach(function (field) {
                var id = 'ff-' + feature + '-' + field.field;
                html += '<label class="form-field-settings-item" for="' + esc(id) + '">' +
                    '<input type="checkbox" id="' + esc(id) + '"' +
                    ' data-feature="' + esc(feature) + '"' +
                    ' data-field="' + esc(field.field) + '"' +
                    (field.required ? ' checked' : '') + '>' +
                    '<span>' + esc(field.label_ar) + '</span>' +
                    '</label>';
            });

            html += '</div></section>';
        });

        wrap.innerHTML = html || '<p class="text-muted">لا توجد حقول قابلة للتخصيص.</p>';
    }

    function collectPayload() {
        var out = {};
        wrap.querySelectorAll('input[type="checkbox"][data-feature]').forEach(function (input) {
            var feature = input.getAttribute('data-feature');
            var field = input.getAttribute('data-field');
            if (!out[feature]) out[feature] = {};
            out[feature][field] = input.checked;
        });
        return out;
    }

    function showError(msg) {
        if (!errEl) return;
        errEl.textContent = msg || '';
        errEl.style.display = msg ? 'block' : 'none';
    }

    function saveFields() {
        if (!saveBtn) return;
        showError('');
        saveBtn.disabled = true;

        fetch('/admin/form-field-settings', {
            method: 'PUT',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ fields: collectPayload() }),
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    if (!res.ok) throw data;
                    return data;
                });
            })
            .then(function (data) {
                if (data.fields) {
                    policies = data.fields;
                    render();
                }
                if (window.showToast) {
                    window.showToast(data.message || 'تم حفظ إعدادات الحقول');
                }
            })
            .catch(function (err) {
                var msg = (err && err.message) || (err && err.errors && Object.values(err.errors)[0][0]) ||
                    'تعذّر حفظ إعدادات الحقول';
                showError(msg);
            })
            .finally(function () {
                if (saveBtn) saveBtn.disabled = false;
            });
    }

    render();

    if (saveBtn) {
        saveBtn.addEventListener('click', saveFields);
    }
})();
