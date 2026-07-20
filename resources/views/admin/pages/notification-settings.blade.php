@php
    $alerts = $notification_alerts ?? app(\App\Services\SettingService::class)->notificationAlerts();
@endphp
<div class="section-view" id="section-notification-settings">
    <div class="panel">
        <div class="panel-header">
            <h3>🔔 إعدادات التنبيه الصوتي</h3>
        </div>

        <p class="notification-settings-hint">
            يتحكم السوبر أدمن في <strong>التنبيه الصوتي</strong> لجميع المستخدمين: صوت عند وصول إشعار جديد،
            و<strong>تكرار التنبيه</strong> كل عدد دقائق محدد ما دامت هناك إشعارات غير مقروءة ولم يفتح المستخدم صفحة الإشعارات.
        </p>

        <form id="notificationSettingsForm" class="notification-settings-form">
            <label class="notification-settings-field notification-settings-toggle">
                <input type="checkbox" name="sound_enabled" value="1" @checked($alerts['sound_enabled'] ?? true)>
                <span>تفعيل التنبيه الصوتي</span>
            </label>

            <label class="notification-settings-field">
                <span>تكرار التنبيه كل (دقيقة)</span>
                <div class="notification-settings-input-wrap">
                    <input type="number"
                           name="reminder_minutes"
                           min="1"
                           max="60"
                           step="1"
                           value="{{ $alerts['reminder_minutes'] ?? 1 }}"
                           required>
                    <span>د</span>
                </div>
                <small class="notification-settings-note">من 1 إلى 60 دقيقة — يتوقف التكرار عند فتح صفحة الإشعارات أو قراءة الكل.</small>
            </label>

            <div id="notificationSettingsError" class="notification-settings-error" style="display:none;"></div>

            <div class="notification-settings-actions">
                <button type="submit" class="btn-action success">💾 حفظ الإعدادات</button>
            </div>
        </form>
    </div>
</div>

<style>
    #section-notification-settings .notification-settings-hint {
        margin: 0 16px 16px;
        padding: 12px 14px;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 8px;
        color: #1e40af;
        font-size: 13px;
        line-height: 1.7;
    }
    .notification-settings-form {
        display: grid;
        gap: 16px;
        padding: 0 16px 16px;
        max-width: 480px;
    }
    .notification-settings-field {
        display: grid;
        gap: 6px;
        font-size: 13px;
        font-weight: 700;
        color: #475569;
    }
    .notification-settings-toggle {
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        font-weight: 600;
    }
    .notification-settings-toggle input {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    .notification-settings-input-wrap {
        display: flex;
        align-items: center;
        gap: 8px;
        max-width: 160px;
    }
    .notification-settings-input-wrap input {
        flex: 1;
        padding: 10px;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-family: inherit;
        font-weight: 400;
    }
    .notification-settings-note {
        color: #64748b;
        font-weight: 400;
        line-height: 1.5;
    }
    .notification-settings-error {
        padding: 10px 12px;
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 8px;
        color: #b91c1c;
        font-size: 13px;
    }
    .notification-settings-actions { padding-top: 4px; }
</style>

<script>
(function () {
    var form = document.getElementById('notificationSettingsForm');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var err = document.getElementById('notificationSettingsError');
        if (err) err.style.display = 'none';

        var fd = new FormData(form);
        fd.set('sound_enabled', form.querySelector('[name="sound_enabled"]')?.checked ? '1' : '0');
        fd.append('_method', 'PUT');

        fetch('/admin/notification-settings', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: fd,
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
          .then(function (res) {
              if (!res.ok) {
                  if (err) {
                      err.style.display = 'block';
                      err.textContent = res.data.message || (res.data.errors && Object.values(res.data.errors).flat().join(' ')) || 'تعذّر الحفظ';
                  }
                  return;
              }
              if (res.data.notification_alerts) {
                  window.__NOTIF_SOUND_ENABLED = !!res.data.notification_alerts.sound_enabled;
                  window.__NOTIF_REMINDER_MS = Math.max(1, parseInt(res.data.notification_alerts.reminder_minutes, 10) || 1) * 60000;
              }
              alert(res.data.message || 'تم الحفظ');
          });
    });
})();
</script>
