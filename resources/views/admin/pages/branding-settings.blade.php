@php
    $branding = $branding ?? app(\App\Services\SettingService::class)->branding();
    $logoRel = $branding['logo_path'] ?? '';
    $logoExists = $logoRel !== '' && is_file(public_path($logoRel));
@endphp
<div class="section-view" id="section-branding-settings">
    <div class="panel">
        <div class="panel-header">
            <h3>🎨 الهوية البصرية</h3>
        </div>

        <p class="branding-settings-hint">
            الشعار والترويسة تظهر في <strong>صفحة تسجيل الدخول</strong> و<strong>المطبوعات</strong> (عروض الأسعار، أوامر الشغل، بطاقة المريض).
        </p>

        <form id="brandingSettingsForm" class="branding-settings-form" enctype="multipart/form-data">
            <div class="branding-preview">
                @include('partials.org-brand-mark', ['branding' => $branding, 'size' => 'lg'])
            </div>

            <label class="branding-field">
                <span>اسم المركز (مختصر)</span>
                <input type="text" name="center_name" value="{{ $branding['center_name'] }}" required maxlength="120">
            </label>

            <label class="branding-field">
                <span>أسطر الترويسة (سطر لكل سطر)</span>
                <textarea name="header_lines" rows="5" required>{{ implode("\n", $branding['lines']) }}</textarea>
            </label>

            <label class="branding-field">
                <span>شعار المركز</span>
                <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp,.svg,image/*">
                @if ($logoExists)
                    <small class="branding-file-note">الشعار الحالي: {{ $logoRel }}</small>
                @endif
            </label>

            <div id="brandingSettingsError" class="branding-settings-error" style="display:none;"></div>

            <div class="branding-settings-actions">
                <button type="submit" class="btn-action success">💾 حفظ الهوية البصرية</button>
            </div>
        </form>
    </div>
</div>

<style>
    #section-branding-settings .branding-settings-hint {
        margin: 0 16px 16px;
        padding: 12px 14px;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 8px;
        color: #1e40af;
        font-size: 13px;
        line-height: 1.7;
    }
    .branding-settings-form {
        display: grid;
        gap: 14px;
        padding: 0 16px 16px;
        max-width: 640px;
    }
    .branding-preview {
        padding: 16px;
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        border-radius: 12px;
    }
    .branding-field {
        display: grid;
        gap: 6px;
        font-size: 13px;
        font-weight: 700;
        color: #475569;
    }
    .branding-field input,
    .branding-field textarea {
        padding: 10px;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-family: inherit;
        font-weight: 400;
    }
    .branding-file-note { color: #64748b; font-weight: 400; }
    .branding-settings-error {
        padding: 10px 12px;
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 8px;
        color: #b91c1c;
        font-size: 13px;
    }
    .branding-settings-actions { padding-top: 4px; }
</style>

<script>
(function () {
    var form = document.getElementById('brandingSettingsForm');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var err = document.getElementById('brandingSettingsError');
        if (err) err.style.display = 'none';

        var fd = new FormData(form);
        fd.append('_method', 'PUT');
        fetch('/admin/branding-settings', {
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
              alert(res.data.message || 'تم الحفظ');
              window.location.reload();
          });
    });
})();
</script>
