@php
    $queue = $queue_appointments ?? collect();
    $queueDate = $queue_date ?? now()->toDateString();
    $todayTotal = $queue_today_total ?? $queue->count();
    $waitingCount = $queue_waiting_count ?? $queue->count();
    $examinedCount = $queue_examined_count ?? 0;
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
        <div class="icon">⏳</div>
        <div class="info">
          <div class="label">في الانتظار</div>
          <div class="value" id="waitingCount">{{ $waitingCount }}</div>
        </div>
      </div>
      <div class="stat-mini">
        <div class="icon">✅</div>
        <div class="info">
          <div class="label">تم الفحص</div>
          <div class="value" id="examinedCount">{{ $examinedCount }}</div>
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
                  $diagnosisUrl = route('doctor.diagnosis', ['appointment' => $appt->id]);
                  $pt = $appt->patient_type ?? 'civilian';
                  $entity = $appt->displayEntity();
              @endphp
              <tr class="queue-row-clickable"
                  data-href="{{ $diagnosisUrl }}"
                  data-search="{{ $appt->patient_name }} {{ $entity }}">
                <td>{{ $loop->iteration }}</td>
                <td>
                  <strong>{{ $appt->patient_name }}</strong>
                  <span class="patient-type-badge {{ $pt === 'military' ? 'military' : 'civilian' }}">
                    {{ $pt === 'military' ? '🪖 عسكري' : '🌐 مدني' }}
                  </span>
                </td>
                <td>{{ $entity }}</td>
                <td><span class="wait-time">{{ $appt->receptionWaitLabel() }}</span></td>
                <td>{{ $appt->transferredAtFormatted() }}</td>
                <td>
                  <a href="{{ $diagnosisUrl }}" class="btn-action" onclick="event.stopPropagation()">
                    📝 فحص
                  </a>
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
