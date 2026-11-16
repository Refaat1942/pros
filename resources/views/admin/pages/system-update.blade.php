@php
    $isSuper = auth()->user()?->isSuperAdmin() ?? false;
    $current = app(\App\Services\SystemUpdateService::class)->currentVersion();
@endphp
<div class="section-view" id="section-system-update">
    @unless ($isSuper)
        <div class="panel" style="padding:24px;text-align:center;color:#b91c1c;font-weight:700;">تحديث النظام من صلاحيات مدير النظام فقط.</div>
    @else
    <div class="panel" style="margin-bottom:16px;">
        <div class="panel-header"><h3>⬆️ تحديث النظام</h3>
            <span class="badge">الإصدار الحالي: {{ $current['version'] ?? 'غير معروف' }}</span>
        </div>
        <div style="padding:16px;line-height:1.9;font-size:14px;">
            <p style="margin:0 0 10px;">ارفع ملف التحديث الرسمي (ZIP) من أي جهاز على الشبكة — يتم التحديث على السيرفر نفسه.</p>
            <ul style="margin:0 0 12px;padding-inline-start:20px;color:var(--text-muted);font-size:13px;">
                <li>يُقبل فقط ملف تحديث رسمي <strong>موقّع</strong> — أي ملف معدَّل أو غير موقّع يُرفض.</li>
                <li>يتم أخذ نسخة احتياطية تلقائياً قبل التحديث، ويتوقف النظام دقيقة أو اثنتين أثناء التركيب.</li>
                <li>لا يمكن تركيب إصدار أقدم من الحالي.</li>
            </ul>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;align-items:end;">
                <div class="form-group" style="margin:0;">
                    <label for="suFile">ملف التحديث (ZIP)</label>
                    <input type="file" id="suFile" class="form-control" accept=".zip">
                </div>
                <div class="form-group" style="margin:0;">
                    <label for="suPassword">كلمة مرورك</label>
                    <input type="password" id="suPassword" class="form-control" autocomplete="current-password">
                </div>
                <div><button type="button" class="btn-action success" id="suStart">⬆️ رفع وتحديث</button></div>
            </div>
            <div id="suProgressWrap" style="display:none;margin-top:16px;">
                <div style="height:12px;border-radius:999px;background:#e2e8f0;overflow:hidden;"><div id="suBar" style="height:100%;width:0;background:#4f46e5;transition:width .3s;"></div></div>
                <p id="suStatus" style="margin:8px 0 0;font-weight:800;"></p>
                <pre id="suLog" style="display:none;margin-top:10px;max-height:280px;overflow:auto;background:#0f172a;color:#e2e8f0;padding:10px;border-radius:10px;font-size:12px;direction:ltr;text-align:left;white-space:pre-wrap;"></pre>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        function $(id) { return document.getElementById(id); }
        function status(text, color) { $('suStatus').textContent = text; $('suStatus').style.color = color || ''; }
        function bar(pct) { $('suBar').style.width = Math.max(0, Math.min(100, pct)) + '%'; }
        function api(url, opts) {
            opts = opts || {};
            opts.credentials = 'same-origin';
            opts.headers = Object.assign({ Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }, opts.headers || {});
            return fetch(url, opts).then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { if (!r.ok) throw j; return j; }); });
        }
        function poll(url) {
            $('suLog').style.display = 'block';
            var tries = 0;
            (function tick() {
                fetch(url, { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (s) {
                    if (s.log) { $('suLog').textContent = s.log; $('suLog').scrollTop = 1e9; }
                    if (s.done) { bar(100); status('✅ تم التحديث بنجاح — اطلب من المستخدمين الضغط على Ctrl+F5.', '#059669'); $('suStart').disabled = false; return; }
                    if (s.failed) { status('❌ توقف التحديث — النظام رجع يشتغل والنسخة الاحتياطية محفوظة. صوّر السجل وابعته.', '#b91c1c'); $('suStart').disabled = false; return; }
                    bar(60 + Math.min(35, tries));
                    status('⏳ جاري التركيب على السيرفر… ' + (s.step || ''), '#4338ca');
                    tries++; setTimeout(tick, 2000);
                }).catch(function () { tries++; setTimeout(tick, 3000); });
            })();
        }
        $('suStart').addEventListener('click', function () {
            var file = $('suFile').files[0];
            var pw = $('suPassword').value;
            if (!file) return status('اختر ملف التحديث.', '#b91c1c');
            if (!pw) return status('اكتب كلمة المرور.', '#b91c1c');
            if (!window.confirm('سيتم تحديث النظام الآن (مع نسخة احتياطية تلقائية) وسيتوقف لدقيقة أو اثنتين. متابعة؟')) return;
            var btn = this; btn.disabled = true;
            $('suProgressWrap').style.display = 'block'; bar(0); status('بدء الرفع…');
            api('/admin/system-update/start', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ password: pw, size: file.size, name: file.name }) })
                .then(function (s) {
                    var size = s.chunk_bytes, total = Math.ceil(file.size / size), i = 0;
                    function next() {
                        if (i >= total) {
                            status('🔐 التحقق من التوقيع وسلامة الملفات…'); bar(55);
                            return api('/admin/system-update/' + s.id + '/finish', { method: 'POST' })
                                .then(function (res) { status(res.message || 'جاري التحديث…', '#4338ca'); poll(res.status_url); });
                        }
                        return api('/admin/system-update/' + s.id + '/chunk?index=' + i, {
                            method: 'POST', headers: { 'Content-Type': 'application/octet-stream' }, body: file.slice(i * size, (i + 1) * size),
                        }).then(function () { i++; bar(i / total * 50); status('رفع الملف… ' + Math.round(i / total * 100) + '%'); return next(); });
                    }
                    return next();
                })
                .catch(function (e) {
                    status('❌ ' + ((e && e.errors) ? Object.values(e.errors)[0][0] : ((e && e.message) || 'تعذّر التحديث.')), '#b91c1c');
                    btn.disabled = false;
                });
        });
    })();
    </script>
    @endunless
</div>
