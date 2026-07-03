@php
    $queue = $queue_appointments ?? collect();
    $queueDate = $queue_date ?? \App\Support\ClinicTime::todayDateString();
    $todayTotal = $queue_today_total ?? $queue->count();
    $receptionPendingCount = $queue_reception_pending_count ?? 0;
@endphp
<div class="stats-row">
      <div class="stat-mini">
        <div class="icon">📅</div>
        <div class="info">
          <div class="label">حالات اليوم</div>
          <div class="value" id="todayCount">{{ $todayTotal }}</div>
        </div>
      </div>
      <div class="stat-mini">
        <div class="icon" style="background:rgba(217,119,6,0.12);">🏥</div>
        <div class="info">
          <div class="label">في الاستقبال — لم يُحوَّلوا</div>
          <div class="value" id="receptionPendingCount" style="color:#d97706;">{{ $receptionPendingCount }}</div>
        </div>
      </div>
    </div>

<div class="section-view" id="section-queue">
    <div class="panel">
      <div class="panel-header">
        <h3>📋 قائمة الانتظار الرقمية</h3>
        <span class="count-badge" id="queueBadge">{{ $queue->count() }}</span>
      </div>
      <div class="data-toolbar">
        <input type="text" id="queueSearch" placeholder="🔍 بحث بالاسم أو الجهة...">
        <span class="toolbar-count" id="queueCount">{{ $queue->count() }} مريض</span>
        <div class="export-btns">
          <button class="btn-export excel" onclick="exportQueue('excel')">📊 Excel</button>
          <button class="btn-export pdf" onclick="exportQueue('pdf')">📄 PDF</button>
        </div>
      </div>
      <div class="panel-body">
        <table data-paginate="10">
          <thead>
            <tr>
              <th>#</th>
              <th>اسم المريض</th>
              <th>الجهة</th>
              <th>وقت الانتظار</th>
              <th>وقت التحويل</th>
              <th>إجراء</th>
            </tr>
          </thead>
          <tbody id="queueTable" data-server-rendered="1">
            @forelse ($queue as $appt)
              @php
                  $pt = $appt->patient_type ?? 'civilian';
                  $entitySearch = $appt->displayEntity();
              @endphp
              <tr class="queue-row-clickable"
                  data-appointment-id="{{ $appt->id }}"
                  data-search="{{ $appt->patient_name }} {{ $entitySearch }}">
                <td>{{ $loop->iteration }}</td>
                <td>
                  <strong>{{ $appt->patient_name }}</strong>
                  <span class="patient-type-badge {{ $pt === 'military' ? 'military' : 'civilian' }}">
                    {{ $pt === 'military' ? 'عسكري' : 'مدني' }}
                  </span>
                </td>
                <td>@include('partials.patient-entity-cell', ['subject' => $appt, 'column' => true])</td>
                <td><span class="wait-time">{{ $appt->clinicWaitLabel() }}</span></td>
                <td>{{ $appt->transferredAtFormatted() }}</td>
                <td>
                  <button type="button" class="btn-action primary doctor-exam-open-btn" data-appointment-id="{{ $appt->id }}" onclick="event.stopPropagation()">
                    📝 فحص
                  </button>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" style="text-align:center;color:var(--text-muted);padding:24px;">
                  لا يوجد مرضى في قائمة الانتظار اليوم.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    </div>
