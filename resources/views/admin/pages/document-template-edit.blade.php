<div class="section-view" id="section-document-template-edit">
    <div class="panel">
        <div class="panel-header" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;">
            <div>
                <h3>✏️ تخصيص وثيقة: {{ $documentTitle }}</h3>
                <p style="margin:6px 0 0;font-size:13px;color:var(--text-muted);">{{ $documentDescription }}</p>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="{{ $hubUrl }}" class="btn-action">← مركز الوثائق</a>
                <a href="{{ $previewUrl }}" target="_blank" rel="noopener" class="btn-action primary">👁️ معاينة</a>
            </div>
        </div>

        @if (!empty($isCustomDocument))
            <div class="panel-body" style="max-width:920px;padding-top:0;">
                <div style="border:1px solid var(--border);border-radius:10px;padding:14px 16px;margin-bottom:16px;">
                    <h4 style="margin:0 0 10px;font-size:14px;">📎 النموذج المرجعي</h4>
                    @if (!empty($referenceUrl))
                        <div style="margin-bottom:12px;">
                            @if (!empty($referenceIsImage))
                                <img src="{{ $referenceUrl }}" alt="نموذج مرجعي" style="max-width:100%;max-height:320px;border:1px solid var(--border);border-radius:8px;">
                            @else
                                <a href="{{ $referenceUrl }}" target="_blank" rel="noopener" class="btn-action">فتح PDF المرجعي</a>
                            @endif
                        </div>
                    @else
                        <p style="font-size:13px;color:var(--text-muted);margin:0 0 10px;">لم يُرفع نموذج بعد — ارفع PDF أو صورة لنسخ التنسيق.</p>
                    @endif
                    @if (auth()->user()?->isSuperAdmin() && !empty($customDocumentId))
                        <form id="customReferenceForm" enctype="multipart/form-data" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                            <input type="file" name="reference" accept=".pdf,.jpg,.jpeg,.png,.webp" class="form-control" style="max-width:280px;">
                            <button type="submit" class="btn-action">رفع / استبدال النموذج</button>
                        </form>
                        <p id="customReferenceMessage" style="margin:8px 0 0;font-size:12px;"></p>
                    @endif
                </div>
            </div>
        @endif

        <form id="documentTemplateForm" class="panel-body" style="max-width:920px;">
            <input type="hidden" name="document_key" value="{{ $documentKey }}">

            <p style="font-size:13px;color:var(--text-muted);margin:0 0 16px;line-height:1.6;">
                عدّل العنوان، الترويسة، التوقيعات، وخيارات الشكل لهذه الوثيقة فقط.
                الترويسة العامة (شعار المؤسسة وأسطر الجهة) من
                <a href="{{ url('/admin/branding-settings') }}">الهوية البصرية</a>.
            </p>

            <div class="doc-template-grid">
                @foreach ($fields as $field)
                    @php
                        $key = $field['key'];
                        $val = old($key, $values[$key] ?? '');
                        $type = $field['type'];
                    @endphp
                    <div class="doc-template-field {{ in_array($type, ['textarea', 'html'], true) ? 'full' : '' }}">
                        <label for="tpl_{{ $key }}">{{ $field['label'] }}</label>
                        @if ($type === 'bool')
                            <label class="doc-template-check">
                                <input type="checkbox" id="tpl_{{ $key }}" name="{{ $key }}" value="1"
                                    {{ filter_var($val, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}>
                                مفعّل
                            </label>
                        @elseif ($type === 'textarea' || $type === 'html')
                            <textarea id="tpl_{{ $key }}" name="{{ $key }}" rows="{{ $type === 'html' ? 8 : 3 }}" class="form-control">{{ $val }}</textarea>
                        @else
                            <input type="text" id="tpl_{{ $key }}" name="{{ $key }}" class="form-control"
                                   value="{{ $val }}" maxlength="500">
                        @endif
                        @if (!empty($field['help']))
                            <small class="field-help">{{ $field['help'] }}</small>
                        @endif
                    </div>
                @endforeach
            </div>

            <div id="documentTemplateMessage" style="margin-top:12px;font-size:13px;"></div>

            <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap;">
                <button type="submit" class="btn-action success" id="btnSaveDocumentTemplate">💾 حفظ التخصيص</button>
                <a href="{{ $previewUrl }}" target="_blank" rel="noopener" class="btn-action">🖨️ معاينة بعد الحفظ</a>
                @if (!empty($isCustomDocument) && auth()->user()?->isSuperAdmin() && !empty($customDocumentId))
                    <button type="button" class="btn-action danger" id="btnDeleteCustomDocument">🗑️ حذف الوثيقة</button>
                @endif
            </div>
        </form>
    </div>
</div>

<style>
.doc-template-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 14px 16px;
}
.doc-template-field.full { grid-column: 1 / -1; }
.doc-template-field label {
    display: block;
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 6px;
}
.doc-template-field .form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-family: inherit;
}
.doc-template-check {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 600;
}
.field-help {
    display: block;
    margin-top: 4px;
    font-size: 11px;
    color: var(--text-muted);
}
</style>

@push('scripts')
<script>
(function () {
  var form = document.getElementById('documentTemplateForm');
  if (!form) return;

  var key = form.querySelector('[name="document_key"]').value;
  var msg = document.getElementById('documentTemplateMessage');
  var csrf = document.querySelector('meta[name="csrf-token"]');
  var customDocId = {{ !empty($customDocumentId) ? (int) $customDocumentId : 0 }};

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var payload = {};
    form.querySelectorAll('input[name], textarea[name]').forEach(function (el) {
      if (el.name === 'document_key') return;
      if (el.type === 'checkbox') {
        payload[el.name] = el.checked;
      } else {
        payload[el.name] = el.value;
      }
    });

    var btn = document.getElementById('btnSaveDocumentTemplate');
    if (btn) btn.disabled = true;

    fetch('/admin/documents-hub/' + encodeURIComponent(key), {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : '',
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    })
      .then(function (r) { return r.ok ? r.json() : r.json().then(function (j) { throw new Error(j.message || 'فشل الحفظ'); }); })
      .then(function (data) {
        if (msg) {
          msg.style.color = '#059669';
          msg.textContent = data.message || 'تم الحفظ';
        }
      })
      .catch(function (err) {
        if (msg) {
          msg.style.color = '#dc2626';
          msg.textContent = err.message || 'تعذّر الحفظ';
        }
      })
      .finally(function () {
        if (btn) btn.disabled = false;
      });
  });

  var refForm = document.getElementById('customReferenceForm');
  var refMsg = document.getElementById('customReferenceMessage');
  if (refForm && customDocId) {
    refForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var fd = new FormData(refForm);
      fetch('/admin/documents-hub/custom/' + customDocId + '/reference', {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : '',
        },
        credentials: 'same-origin',
        body: fd,
      })
        .then(function (r) { return r.ok ? r.json() : r.json().then(function (j) { throw new Error(j.message || 'فشل الرفع'); }); })
        .then(function (data) {
          if (refMsg) {
            refMsg.style.color = '#059669';
            refMsg.textContent = data.message || 'تم الرفع';
          }
          setTimeout(function () { window.location.reload(); }, 600);
        })
        .catch(function (err) {
          if (refMsg) {
            refMsg.style.color = '#dc2626';
            refMsg.textContent = err.message || 'تعذّر الرفع';
          }
        });
    });
  }

  var delBtn = document.getElementById('btnDeleteCustomDocument');
  if (delBtn && customDocId) {
    delBtn.addEventListener('click', function () {
      if (!confirm('حذف هذه الوثيقة المخصصة؟')) return;
      fetch('/admin/documents-hub/custom/' + customDocId, {
        method: 'DELETE',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : '',
        },
        credentials: 'same-origin',
      })
        .then(function (r) { return r.ok ? r.json() : r.json().then(function (j) { throw new Error(j.message || 'فشل الحذف'); }); })
        .then(function () {
          window.location.href = '{{ $hubUrl }}';
        })
        .catch(function (err) {
          alert(err.message || 'تعذّر الحذف');
        });
    });
  }
})();
</script>
@endpush
