@php
    $appt = $selected_appointment ?? null;
    $patient = $selected_patient ?? $appt?->patient;
    $draft = $draft_record ?? null;
@endphp
<div class="section-view" id="section-diagnosis">
      <div class="panel form-panel">
        <div class="panel-header">
          <h3>📝 التشخيص الطبي</h3>
        </div>
        <div class="panel-body">
          @if (!$patient)
            <p style="padding:24px;color:var(--text-muted);text-align:center;">
              اختر مريضاً من <a href="{{ route('doctor.queue') }}">قائمة الانتظار</a> لبدء الكشف.
            </p>
          @else
          <div class="patient-info-bar" id="patientBar">
            <h4 id="selectedPatientName">
              {{ $patient->name }}
              <span class="patient-type-badge {{ $patient->patient_type === 'military' ? 'military' : 'civilian' }}">
                {{ $patient->patient_type === 'military' ? '🪖 عسكري' : '🌐 مدني' }}
              </span>
            </h4>
            <p id="selectedPatientInfo">
              {{ $patient->patient_code }}
              — {{ $patient->patient_type === 'military' ? 'الجهة السيادية' : 'جهة التعاقد' }}: {{ $patient->displayEntity() }}
            </p>
          </div>

          @if ($patient->patient_type === 'military')
          <div class="silent-clinic-note" id="silentClinicNote">
            🪖 <span>مريض عسكري — <strong>عيادة صامتة</strong>: يُسجَّل الكشف والتوصيف ويتخطّى النظام عرض السعر والتحصيل.</span>
          </div>
          @endif

          <form method="POST" action="{{ route('doctor.diagnosis.store') }}" id="diagnosisForm" data-validate-form>
            @csrf
            <input type="hidden" name="form" value="diagnosis">
            <input type="hidden" name="lock" value="1">
            <input type="hidden" name="patient_id" value="{{ $patient->id }}">
            <input type="hidden" name="appointment_id" value="{{ $appt?->id }}">
            @if ($draft)
                <input type="hidden" name="medical_record_id" value="{{ $draft->id }}">
            @endif

            <div class="form-group">
              <label>التشخيص الدقيق <span class="required">*</span></label>
              <textarea class="form-control" name="diagnosis" id="diagnosis"
                        data-v-rules="required,min:3,max:5000" maxlength="5000"
                        placeholder="أدخل التشخيص الطبي التفصيلي...">{{ old('diagnosis', $draft?->diagnosis) }}</textarea>
            </div>

            <div class="form-group">
              <label>الروشتة الطبية</label>
              <textarea class="form-control" name="prescription" id="prescription"
                        data-v-rules="max:5000" maxlength="5000"
                        placeholder="الأدوية والإرشادات الطبية (اختياري)...">{{ old('prescription', $draft?->prescription) }}</textarea>
            </div>

            <div class="form-actions">
              <button type="submit" class="btn btn-primary" id="saveBtn">
                💾 حفظ
              </button>
              @can('skip-diagnosis')
                @if ($appt)
                  <button type="button" class="btn btn-secondary" id="skipDiagnosisBtn"
                          data-skip-url="{{ route('doctor.diagnosis.skip', $appt->id) }}"
                          title="الكشف اختياري — ادفع الحالة مباشرةً للتوصيف">
                    ⏭️ تخطّي الكشف ← التوصيف
                  </button>
                @endif
              @endcan
              <a href="{{ route('doctor.queue') }}" class="btn btn-secondary">إلغاء</a>
            </div>
          </form>
          @endif
        </div>
      </div>
    </div>

<script>
(function () {
    var btn = document.getElementById('skipDiagnosisBtn');
    if (!btn) return;

    btn.addEventListener('click', function () {
        if (!confirm('تخطّي الكشف الطبي ودفع الحالة مباشرةً للتوصيف؟ لن يُسجَّل تقرير طبي.')) {
            return;
        }

        var meta = document.querySelector('meta[name="csrf-token"]');
        btn.disabled = true;

        fetch(btn.getAttribute('data-skip-url'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': meta ? meta.getAttribute('content') : '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
        .then(function (r) { return r.ok ? r.json() : r.json().then(function (j) { throw j; }); })
        .then(function () {
            window.location.href = '{{ route('doctor.queue') }}';
        })
        .catch(function (e) {
            btn.disabled = false;
            alert((e && e.message) ? e.message : 'تعذّر تخطّي الكشف.');
        });
    });
})();
</script>
