@php
    $rateRows = $overhead_rate_definitions ?? [];
    $ratesSum = $overhead_rates_sum ?? 0;
@endphp
<div class="section-view" id="section-costing-settings">
    <div class="panel">
        <div class="panel-header">
            <h3>⚙️ إعدادات التكاليف الإضافية</h3>
            <span class="badge" id="costingSettingsSumBadge">{{ rtrim(rtrim(number_format((float) $ratesSum, 2, '.', ''), '0'), '.') }}%</span>
        </div>

        <p class="costing-settings-hint">
            النسب التالية تُطبَّق فوق <strong>تكلفة المواد (WAC)</strong> لتحديد سعر الجمهور قبل خصم جهة التعاقد.
            مجموع النسب يجب أن يساوي <strong>100%</strong>.
        </p>

        <form id="costingSettingsForm" class="costing-settings-form">
            @foreach ($rateRows as $row)
                <label class="costing-settings-field">
                    <span>{{ $row['label'] }}</span>
                    <div class="costing-settings-input-wrap">
                        <input type="number"
                               name="{{ $row['key'] }}"
                               min="0"
                               max="100"
                               step="0.01"
                               value="{{ $row['rate'] }}"
                               required>
                        <span>%</span>
                    </div>
                </label>
            @endforeach

            <div id="costingSettingsError" class="costing-settings-error" style="display:none;"></div>

            <div class="costing-settings-actions">
                <button type="submit" class="btn-action success">💾 حفظ الإعدادات</button>
            </div>
        </form>
    </div>
</div>

<style>
    #section-costing-settings .costing-settings-hint {
        margin: 0 16px 16px;
        padding: 12px 14px;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 8px;
        color: #1e40af;
        font-size: 13px;
        line-height: 1.7;
    }
    #section-costing-settings .costing-settings-form {
        display: grid;
        gap: 14px;
        padding: 0 16px 16px;
        max-width: 720px;
    }
    #section-costing-settings .costing-settings-field {
        display: grid;
        gap: 6px;
        font-size: 13px;
        font-weight: 700;
    }
    #section-costing-settings .costing-settings-input-wrap {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    #section-costing-settings .costing-settings-input-wrap input {
        width: 120px;
        padding: 10px;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-family: inherit;
    }
    #section-costing-settings .costing-settings-error {
        padding: 10px 12px;
        background: #fee2e2;
        border-radius: 8px;
        color: #dc2626;
        font-size: 13px;
    }
    #section-costing-settings .costing-settings-actions {
        display: flex;
        justify-content: flex-end;
    }
</style>

<script>
(function () {
    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function updateSumBadge() {
        var form = document.getElementById('costingSettingsForm');
        var badge = document.getElementById('costingSettingsSumBadge');
        if (!form || !badge) return;

        var sum = 0;
        form.querySelectorAll('input[type="number"]').forEach(function (input) {
            sum += parseFloat(input.value || '0') || 0;
        });

        badge.textContent = sum.toFixed(2).replace(/\.?0+$/, '') + '%';
        badge.style.background = Math.abs(sum - 100) < 0.01 ? '#dcfce7' : '#fee2e2';
        badge.style.color = Math.abs(sum - 100) < 0.01 ? '#166534' : '#dc2626';
    }

    document.getElementById('costingSettingsForm')?.addEventListener('input', updateSumBadge);

    document.getElementById('costingSettingsForm')?.addEventListener('submit', function (e) {
        e.preventDefault();

        var form = e.target;
        var err = document.getElementById('costingSettingsError');
        var payload = {};

        form.querySelectorAll('input[type="number"]').forEach(function (input) {
            payload[input.name] = parseFloat(input.value || '0');
        });

        fetch('/admin/costing-settings', {
            method: 'PUT',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        })
        .then(function (r) { return r.ok ? r.json() : r.json().then(function (j) { throw j; }); })
        .then(function (data) {
            if (err) err.style.display = 'none';
            updateSumBadge();
            if (window.DashboardToast) {
                window.DashboardToast.show(data.message || 'تم الحفظ', { id: 'toast' });
            }
        })
        .catch(function (e) {
            var msg = (e && e.message) ? e.message : 'تعذّر الحفظ.';
            if (e && e.errors) {
                msg = Object.values(e.errors)[0][0] || msg;
            }
            if (err) {
                err.textContent = msg;
                err.style.display = 'block';
            }
        });
    });

    updateSumBadge();
})();
</script>
