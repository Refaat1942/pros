@php
    $appt = $selected_appointment ?? null;
    $patient = $selected_patient ?? $appt?->patient;
    $draft = $draft_record ?? null;
@endphp
<div class="section-view" id="section-diagnosis">
      <div id="analytics-diagnosis">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '📝', 'label' => 'تقارير اليوم', 'value' => '0', 'color' => '#059669', 'bg' => 'rgba(5,150,105,0.1)'],
        ['icon' => '📦', 'label' => 'أصناف المخزون', 'value' => '0', 'bg' => 'rgba(14,116,144,0.1)'],
        ['icon' => '💊', 'label' => 'توصيات', 'value' => '0', 'bg' => 'rgba(14,116,144,0.1)'],
        ['icon' => '📦', 'label' => 'محول للمخزون', 'value' => '0', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
      ]])</div>
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
            <h4 id="selectedPatientName">{{ $patient->name }}</h4>
            <p id="selectedPatientInfo">
              {{ $patient->patient_code }} — {{ $patient->company_name ?? '—' }}
              — {{ $patient->patient_type === 'military' ? '🪖 عسكري' : '🌐 مدني' }}
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
                💾 حفظ واعتماد التقرير
              </button>
              <a href="{{ route('doctor.queue') }}" class="btn btn-secondary">إلغاء</a>
            </div>
          </form>
          @endif
        </div>
      </div>
    </div>
