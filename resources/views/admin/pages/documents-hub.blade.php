<div class="section-view" id="section-documents-hub">
    <div class="panel">
        <div class="panel-header" style="display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:12px;">
            <div>
                <h3>📄 مركز الوثائق والطباعة</h3>
                <p style="margin:8px 0 0;font-size:13px;color:var(--text-muted);">
                    اختر وثيقة للطباعة أو لتخصيص شكلها ومحتواها. يمكنك أيضاً
                    <strong>إضافة وثيقة جديدة</strong> ورفع نموذج PDF/صورة لنسخ تنسيقه.
                    الشعار وأسطر الجهة من <a href="{{ url('/admin/branding-settings') }}">الهوية البصرية</a>.
                </p>
            </div>
            @if (auth()->user()?->isSuperAdmin())
                <button type="button" id="btnOpenCustomDocumentModal" class="btn-action success">➕ إضافة وثيقة جديدة</button>
            @endif
        </div>
        @if (empty($customDocumentsTableReady))
            <div class="panel-body" style="padding-top:0;">
                <div style="border:1px solid #f59e0b;background:#fffbeb;border-radius:10px;padding:12px 16px;font-size:13px;line-height:1.6;">
                    <strong>تنبيه:</strong> جدول الوثائق المخصصة غير جاهز على هذا السيرفر.
                    الوثائق الأساسية (عرض سعر، إيصال، إذن صرف…) تعمل بعد التحديث، لكن «إضافة وثيقة جديدة» تحتاج:
                    <code style="display:block;margin-top:8px;">bash deploy.sh</code>
                    أو <code>php artisan migrate --force</code>
                </div>
            </div>
        @endif
        <div class="panel-body" style="display:grid;gap:20px;">
            @foreach ($documents as $group)
                <section>
                    <h4 style="margin:0 0 12px;font-size:15px;">{{ $group['group'] }}</h4>
                    <div style="display:grid;gap:10px;">
                        @foreach ($group['items'] as $doc)
                            <div style="border:1px solid var(--border);border-radius:10px;padding:14px 16px;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;">
                                <div>
                                    <strong>{{ $doc['title'] }}</strong>
                                    @if (!empty($doc['is_custom']))
                                        <span style="font-size:11px;background:#e0f2fe;color:#0369a1;padding:2px 8px;border-radius:999px;margin-right:6px;">مخصصة</span>
                                    @endif
                                    <p style="margin:4px 0 0;font-size:13px;color:var(--text-muted);">{{ $doc['description'] }}</p>
                                </div>
                                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                    @if (!empty($doc['preview_url']))
                                        <a href="{{ $doc['preview_url'] }}" target="_blank" rel="noopener" class="btn-action">👁️ معاينة</a>
                                    @endif
                                    @if (!empty($doc['print_url']))
                                        <a href="{{ $doc['print_url'] }}" target="_blank" rel="noopener" class="btn-action primary">🖨️ طباعة</a>
                                    @endif
                                    @if (!empty($doc['edit_url']))
                                        <a href="{{ $doc['edit_url'] }}" class="btn-action success">✏️ تخصيص الشكل والمحتوى</a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</div>

@if (auth()->user()?->isSuperAdmin())
<div id="customDocumentModal" style="display:none;position:fixed;inset:0;z-index:9999;align-items:center;justify-content:center;padding:16px;background:rgba(15,23,42,.45);">
    <div style="background:#fff;border-radius:16px;box-shadow:0 20px 40px rgba(0,0,0,.15);width:100%;max-width:520px;padding:24px;" role="dialog" aria-modal="true" aria-labelledby="customDocumentModalTitle">
        <h4 id="customDocumentModalTitle" style="margin:0 0 12px;font-size:18px;">إضافة وثيقة جديدة</h4>
        <p style="font-size:13px;color:var(--text-muted);margin:0 0 16px;">
            ارفع نموذج (PDF أو صورة) ثم عدّل المحتوى والتنسيق في شاشة التخصيص.
        </p>
        <form id="customDocumentForm" enctype="multipart/form-data">
            <div style="display:grid;gap:12px;">
                <div>
                    <label for="custom_doc_title" style="display:block;font-size:13px;font-weight:700;margin-bottom:4px;">اسم الوثيقة</label>
                    <input type="text" id="custom_doc_title" name="title" required maxlength="200" class="form-control" style="width:100%;" placeholder="مثال: خطاب موافقة جهة">
                </div>
                <div>
                    <label for="custom_doc_group" style="display:block;font-size:13px;font-weight:700;margin-bottom:4px;">المجموعة</label>
                    <input type="text" id="custom_doc_group" name="group_label" required maxlength="120" class="form-control" style="width:100%;" placeholder="مثال: المالية والاستقبال">
                </div>
                <div>
                    <label for="custom_doc_description" style="display:block;font-size:13px;font-weight:700;margin-bottom:4px;">وصف مختصر</label>
                    <input type="text" id="custom_doc_description" name="description" maxlength="2000" class="form-control" style="width:100%;">
                </div>
                <div>
                    <label for="custom_doc_reference" style="display:block;font-size:13px;font-weight:700;margin-bottom:4px;">رفع نموذج (PDF / صورة)</label>
                    <input type="file" id="custom_doc_reference" name="reference" accept=".pdf,.jpg,.jpeg,.png,.webp" class="form-control" style="width:100%;">
                </div>
                <div>
                    <label for="custom_doc_body" style="display:block;font-size:13px;font-weight:700;margin-bottom:4px;">محتوى أولي (اختياري — HTML)</label>
                    <textarea id="custom_doc_body" name="body_html" rows="4" class="form-control" style="width:100%;" placeholder="يمكنك لصق نص من النموذج هنا"></textarea>
                </div>
            </div>
            <p id="customDocumentFormMessage" style="margin:12px 0 0;font-size:13px;"></p>
            <div style="margin-top:16px;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" id="btnCloseCustomDocumentModal" class="btn-action">إلغاء</button>
                <button type="submit" id="btnSaveCustomDocument" class="btn-action success">إنشاء وتخصيص</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
  var modal = document.getElementById('customDocumentModal');
  var form = document.getElementById('customDocumentForm');
  var openBtn = document.getElementById('btnOpenCustomDocumentModal');
  var closeBtn = document.getElementById('btnCloseCustomDocumentModal');
  var msg = document.getElementById('customDocumentFormMessage');
  var csrf = document.querySelector('meta[name="csrf-token"]');

  function openModal() {
    if (!modal) return;
    modal.style.display = 'flex';
    if (form) form.reset();
    if (msg) msg.textContent = '';
  }
  function closeModal() {
    if (modal) modal.style.display = 'none';
  }

  if (openBtn) openBtn.addEventListener('click', openModal);
  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  if (modal) {
    modal.addEventListener('click', function (e) {
      if (e.target === modal) closeModal();
    });
  }

  if (!form) return;

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = document.getElementById('btnSaveCustomDocument');
    if (btn) btn.disabled = true;

    fetch('/admin/documents-hub/custom', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : '',
      },
      credentials: 'same-origin',
      body: new FormData(form),
    })
      .then(function (r) {
        return r.ok ? r.json() : r.json().then(function (j) { throw new Error(j.message || 'فشل الإنشاء'); });
      })
      .then(function (data) {
        if (data.document && data.document.edit_url) {
          window.location.href = data.document.edit_url;
        }
      })
      .catch(function (err) {
        if (msg) {
          msg.style.color = '#dc2626';
          msg.textContent = err.message || 'تعذّر إنشاء الوثيقة';
        }
      })
      .finally(function () {
        if (btn) btn.disabled = false;
      });
  });
})();
</script>
@endauth
